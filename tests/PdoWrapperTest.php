<?php

declare(strict_types=1);

namespace tests;

use flight\database\PdoWrapper;
use flight\core\EventDispatcher;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PdoWrapperTest extends TestCase
{
    private PdoWrapper $pdo_wrapper;

    protected function setUp(): void
    {
        $this->pdo_wrapper = new PdoWrapper('sqlite::memory:');
        // create a test table and insert 3 rows of data
        $this->pdo_wrapper->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo_wrapper->exec('INSERT INTO test (name) VALUES ("one")');
        $this->pdo_wrapper->exec('INSERT INTO test (name) VALUES ("two")');
        $this->pdo_wrapper->exec('INSERT INTO test (name) VALUES ("three")');
    }

    protected function tearDown(): void
    {
        // delete the test table
        $this->pdo_wrapper->exec('DROP TABLE test');
    }

    public function testRunQuerySelectAllStatement(): void
    {
        $statement = $this->pdo_wrapper->runQuery('SELECT * FROM test');
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertCount(3, $statement->fetchAll());
    }

    public function testRunQuerySelectOneStatement(): void
    {
        $statement = $this->pdo_wrapper->runQuery('SELECT * FROM test WHERE id = 1');
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertCount(1, $statement->fetchAll());
    }

    public function testRunQueryInsertStatement(): void
    {
        $statement = $this->pdo_wrapper->runQuery('INSERT INTO test (name) VALUES ("four")');
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertEquals(1, $statement->rowCount());
    }

    public function testRunQueryUpdateStatement(): void
    {
        $statement = $this->pdo_wrapper->runQuery('UPDATE test SET name = "something" WHERE name LIKE ?', ['%t%']);
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertEquals(2, $statement->rowCount());
    }

    public function testRunQueryDeleteStatement(): void
    {
        $statement = $this->pdo_wrapper->runQuery('DELETE FROM test WHERE name LIKE ?', ['%t%']);
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertEquals(2, $statement->rowCount());
    }

    public function testFetchField(): void
    {
        $id = $this->pdo_wrapper->fetchField('SELECT id FROM test WHERE name = ?', ['two']);
        $this->assertEquals(2, $id);
    }

    public function testFetchRow(): void
    {
        $row = $this->pdo_wrapper->fetchRow('SELECT * FROM test WHERE name = ?', ['two']);
        $this->assertEquals(2, $row['id']);
        $this->assertEquals('two', $row['name']);
    }

    public function testFetchAll(): void
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT * FROM test');
        $this->assertCount(3, $rows);
        $this->assertEquals(1, $rows[0]['id']);
        $this->assertEquals('one', $rows[0]['name']);
        $this->assertEquals(2, $rows[1]['id']);
        $this->assertEquals('two', $rows[1]['name']);
        $this->assertEquals(3, $rows[2]['id']);
        $this->assertEquals('three', $rows[2]['name']);
    }

    public function testFetchAllNoRows(): void
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT * FROM test WHERE 1 = 2');
        $this->assertCount(0, $rows);
        $this->assertSame([], $rows);
    }

    public function testFetchAllWithNamedParams(): void
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT * FROM test WHERE name = :name', [ 'name' => 'two']);
        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows[0]['id']);
        $this->assertEquals('two', $rows[0]['name']);
    }

    public function testFetchAllWithInInt(): void
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT id FROM test WHERE id IN(?   )', [ [1,2 ]]);
        $this->assertEquals(2, count($rows));
    }

    public function testFetchAllWithInString(): void
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT id FROM test WHERE name IN(?)', [ ['one','two' ]]);
        $this->assertEquals(2, count($rows));
    }

    public function testFetchAllWithInStringCommas(): void
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT id FROM test WHERE id > ? AND name IN( ?)  ', [ 0, 'one,two' ]);
        $this->assertEquals(2, count($rows));
    }

    public function testPullDataFromDsn(): void
    {
        // Testing protected method using reflection
        $reflection = new ReflectionClass($this->pdo_wrapper);
        $method = $reflection->getMethod('pullDataFromDsn');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        // Test SQLite DSN
        $sqliteDsn = 'sqlite::memory:';
        $sqliteResult = $method->invoke($this->pdo_wrapper, $sqliteDsn);
        $this->assertEquals([
            'engine' => 'sqlite',
            'database' => ':memory:',
            'host' => 'localhost'
        ], $sqliteResult);

        // Test MySQL DSN
        $mysqlDsn = 'mysql:host=localhost;dbname=testdb;charset=utf8';
        $mysqlResult = $method->invoke($this->pdo_wrapper, $mysqlDsn);
        $this->assertEquals([
            'engine' => 'mysql',
            'database' => 'testdb',
            'host' => 'localhost'
        ], $mysqlResult);

        // Test PostgreSQL DSN
        $pgsqlDsn = 'pgsql:host=127.0.0.1;dbname=postgres';
        $pgsqlResult = $method->invoke($this->pdo_wrapper, $pgsqlDsn);
        $this->assertEquals([
            'engine' => 'pgsql',
            'database' => 'postgres',
            'host' => '127.0.0.1'
        ], $pgsqlResult);
    }

    public function testLogQueries(): void
    {
        // Create a new PdoWrapper with tracking enabled
        $trackingPdo = new PdoWrapper('sqlite::memory:', null, null, null, true);

        // Create test table
        $trackingPdo->exec('CREATE TABLE test_log (id INTEGER PRIMARY KEY, name TEXT)');

        // Run some queries to populate metrics
        $trackingPdo->runQuery('INSERT INTO test_log (name) VALUES (?)', ['test1']);
        $trackingPdo->fetchAll('SELECT * FROM test_log');

        // Setup event listener to capture triggered event
        $eventTriggered = false;
        $connectionData = null;
        $queriesData = null;

        $dispatcher = EventDispatcher::getInstance();
        $dispatcher->on('flight.db.queries', function ($conn, $queries) use (&$eventTriggered, &$connectionData, &$queriesData) {
            $eventTriggered = true;
            $connectionData = $conn;
            $queriesData = $queries;
        });

        // Call the logQueries method
        $trackingPdo->logQueries();

        // Assert that event was triggered
        $this->assertTrue($eventTriggered);
        $this->assertIsArray($connectionData);
        $this->assertEquals('sqlite', $connectionData['engine']);
        $this->assertIsArray($queriesData);
        $this->assertCount(2, $queriesData); // Should have 2 queries (INSERT and SELECT)

        // Verify query metrics structure for the first query
        $this->assertArrayHasKey('sql', $queriesData[0]);
        $this->assertArrayHasKey('params', $queriesData[0]);
        $this->assertArrayHasKey('execution_time', $queriesData[0]);
        $this->assertArrayHasKey('row_count', $queriesData[0]);
        $this->assertArrayHasKey('memory_usage', $queriesData[0]);

        // Clean up
        $trackingPdo->exec('DROP TABLE test_log');

        // Verify metrics are reset after logging
        $reflection = new ReflectionClass($trackingPdo);
        $property = $reflection->getProperty('queryMetrics');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $this->assertCount(0, $property->getValue($trackingPdo));
    }
}

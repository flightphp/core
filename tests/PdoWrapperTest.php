<?php

declare(strict_types=1);

namespace tests;

use flight\database\PdoWrapper;
use PDOStatement;
use PHPUnit\Framework\TestCase;

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

    public function testRunQuerySelectAllStatement()
    {
        $statement = $this->pdo_wrapper->runQuery('SELECT * FROM test');
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertCount(3, $statement->fetchAll());
    }

    public function testRunQuerySelectOneStatement()
    {
        $statement = $this->pdo_wrapper->runQuery('SELECT * FROM test WHERE id = 1');
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertCount(1, $statement->fetchAll());
    }

    public function testRunQueryInsertStatement()
    {
        $statement = $this->pdo_wrapper->runQuery('INSERT INTO test (name) VALUES ("four")');
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertEquals(1, $statement->rowCount());
    }

    public function testRunQueryUpdateStatement()
    {
        $statement = $this->pdo_wrapper->runQuery('UPDATE test SET name = "something" WHERE name LIKE ?', ['%t%']);
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertEquals(2, $statement->rowCount());
    }

    public function testRunQueryDeleteStatement()
    {
        $statement = $this->pdo_wrapper->runQuery('DELETE FROM test WHERE name LIKE ?', ['%t%']);
        $this->assertInstanceOf(PDOStatement::class, $statement);
        $this->assertEquals(2, $statement->rowCount());
    }

    public function testFetchField()
    {
        $id = $this->pdo_wrapper->fetchField('SELECT id FROM test WHERE name = ?', ['two']);
        $this->assertEquals(2, $id);
    }

    public function testFetchRow()
    {
        $row = $this->pdo_wrapper->fetchRow('SELECT * FROM test WHERE name = ?', ['two']);
        $this->assertEquals(2, $row['id']);
        $this->assertEquals('two', $row['name']);
    }

    public function testFetchAll()
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

    public function testFetchAllNoRows()
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT * FROM test WHERE 1 = 2');
        $this->assertCount(0, $rows);
        $this->assertSame([], $rows);
    }

    public function testFetchAllWithNamedParams()
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT * FROM test WHERE name = :name', [ 'name' => 'two']);
        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows[0]['id']);
        $this->assertEquals('two', $rows[0]['name']);
    }

    public function testFetchAllWithInInt()
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT id FROM test WHERE id IN(?   )', [ [1,2 ]]);
        $this->assertEquals(2, count($rows));
    }

    public function testFetchAllWithInString()
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT id FROM test WHERE name IN(?)', [ ['one','two' ]]);
        $this->assertEquals(2, count($rows));
    }

    public function testFetchAllWithInStringCommas()
    {
        $rows = $this->pdo_wrapper->fetchAll('SELECT id FROM test WHERE id > ? AND name IN( ?)  ', [ 0, 'one,two' ]);
        $this->assertEquals(2, count($rows));
    }
}

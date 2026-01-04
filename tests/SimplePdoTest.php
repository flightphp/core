<?php

declare(strict_types=1);

namespace tests;

use flight\database\SimplePdo;
use flight\util\Collection;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class SimplePdoTest extends TestCase
{
    private SimplePdo $db;

    protected function setUp(): void
    {
        $this->db = new SimplePdo('sqlite::memory:');
        // Create a test table and insert 3 rows of data
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $this->db->exec('INSERT INTO users (name, email) VALUES ("John", "john@example.com")');
        $this->db->exec('INSERT INTO users (name, email) VALUES ("Jane", "jane@example.com")');
        $this->db->exec('INSERT INTO users (name, email) VALUES ("Bob", "bob@example.com")');
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP TABLE users');
    }

    // =========================================================================
    // Constructor Tests
    // =========================================================================

    public function testDefaultFetchModeIsAssoc(): void
    {
        $fetchMode = $this->db->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
        $this->assertEquals(PDO::FETCH_ASSOC, $fetchMode);
    }

    public function testCustomFetchModeCanBeSet(): void
    {
        $db = new SimplePdo('sqlite::memory:', null, null, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ]);
        $fetchMode = $db->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
        $this->assertEquals(PDO::FETCH_OBJ, $fetchMode);
    }

    public function testApmTrackingOffByDefault(): void
    {
        // APM is off by default, so logQueries should not trigger events
        // We test this indirectly by calling logQueries and ensuring no error
        $this->db->logQueries();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testApmTrackingCanBeEnabled(): void
    {
        $db = new SimplePdo('sqlite::memory:', null, null, null, [
            'trackApmQueries' => true
        ]);
        $db->exec('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        $db->runQuery('SELECT * FROM test');
        $db->logQueries(); // Should work without error
        $this->assertTrue(true);
    }

    // =========================================================================
    // runQuery Tests
    // =========================================================================

    public function testRunQueryReturnsStatement(): void
    {
        $stmt = $this->db->runQuery('SELECT * FROM users');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    public function testRunQueryWithParams(): void
    {
        $stmt = $this->db->runQuery('SELECT * FROM users WHERE name = ?', ['John']);
        $rows = $stmt->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertEquals('John', $rows[0]['name']);
    }

    public function testRunQueryWithoutParamsWithMaxQueryMetrics(): void
    {
        $db = new class ('sqlite::memory:', null, null, null, ['maxQueryMetrics' => 2, 'trackApmQueries' => true]) extends SimplePdo {
            public function getQueryMetrics(): array
            {
                return $this->queryMetrics;
            }
        };
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $db->exec('INSERT INTO users (name, email) VALUES ("John", "john@example.com")');
        $db->exec('INSERT INTO users (name, email) VALUES ("Jane", "jane@example.com")');
        $db->exec('INSERT INTO users (name, email) VALUES ("Bob", "bob@example.com")');

        $db->runQuery('SELECT * FROM users WHERE 1 = 1');
        $db->runQuery('SELECT * FROM users WHERE 1 = 2');
        $this->assertEquals(2, count($db->getQueryMetrics()));
        $db->runQuery('SELECT * FROM users WHERE 1 = 3');
        $dbMetrics = $db->getQueryMetrics();
        $this->assertEquals(2, count($dbMetrics));
        $this->assertEquals('SELECT * FROM users WHERE 1 = 2', $dbMetrics[0]['sql']);
        $this->assertEquals('SELECT * FROM users WHERE 1 = 3', $dbMetrics[1]['sql']);
    }

    public function testRunQueryInsert(): void
    {
        $stmt = $this->db->runQuery('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
        $this->assertEquals(1, $stmt->rowCount());
    }

    public function testRunQueryThrowsOnPrepareFailure(): void
    {
        $this->expectException(PDOException::class);
        $this->db->runQuery('SELECT * FROM nonexistent_table');
    }

    // =========================================================================
    // fetchRow Tests
    // =========================================================================

    public function testFetchRowReturnsCollection(): void
    {
        $row = $this->db->fetchRow('SELECT * FROM users WHERE id = ?', [1]);
        $this->assertInstanceOf(Collection::class, $row);
        $this->assertEquals('John', $row['name']);
    }

    public function testFetchRowReturnsNullWhenNoResults(): void
    {
        $row = $this->db->fetchRow('SELECT * FROM users WHERE id = ?', [999]);
        $this->assertNull($row);
    }

    public function testFetchRowAddsLimitAutomatically(): void
    {
        // Even though there are 3 rows, fetchRow should only return 1
        $row = $this->db->fetchRow('SELECT * FROM users');
        $this->assertInstanceOf(Collection::class, $row);
        $this->assertEquals(1, $row['id']);
    }

    public function testFetchRowDoesNotDuplicateLimitClause(): void
    {
        // Query already has LIMIT - should not add another
        $row = $this->db->fetchRow('SELECT * FROM users ORDER BY id DESC LIMIT 1');
        $this->assertInstanceOf(Collection::class, $row);
        $this->assertEquals(3, $row['id']); // Should be Bob (id=3)
    }

    // =========================================================================
    // fetchAll Tests
    // =========================================================================

    public function testFetchAllReturnsArrayOfCollections(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users');
        $this->assertIsArray($rows);
        $this->assertCount(3, $rows);
        $this->assertInstanceOf(Collection::class, $rows[0]);
    }

    public function testFetchAllReturnsEmptyArrayWhenNoResults(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE 1 = 0');
        $this->assertIsArray($rows);
        $this->assertCount(0, $rows);
    }

    public function testFetchAllWithParams(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE name LIKE ?', ['J%']);
        $this->assertCount(2, $rows); // John and Jane
    }

    // =========================================================================
    // fetchColumn Tests
    // =========================================================================

    public function testFetchColumnReturnsFlatArray(): void
    {
        $names = $this->db->fetchColumn('SELECT name FROM users ORDER BY id');
        $this->assertIsArray($names);
        $this->assertEquals(['John', 'Jane', 'Bob'], $names);
    }

    public function testFetchColumnWithParams(): void
    {
        $ids = $this->db->fetchColumn('SELECT id FROM users WHERE name LIKE ?', ['J%']);
        $this->assertEquals([1, 2], $ids);
    }

    public function testFetchColumnReturnsEmptyArrayWhenNoResults(): void
    {
        $result = $this->db->fetchColumn('SELECT id FROM users WHERE 1 = 0');
        $this->assertEquals([], $result);
    }

    // =========================================================================
    // fetchPairs Tests
    // =========================================================================

    public function testFetchPairsReturnsKeyValueArray(): void
    {
        $pairs = $this->db->fetchPairs('SELECT id, name FROM users ORDER BY id');
        $this->assertEquals([1 => 'John', 2 => 'Jane', 3 => 'Bob'], $pairs);
    }

    public function testFetchPairsWithParams(): void
    {
        $pairs = $this->db->fetchPairs('SELECT id, email FROM users WHERE name = ?', ['John']);
        $this->assertEquals([1 => 'john@example.com'], $pairs);
    }

    public function testFetchPairsReturnsEmptyArrayWhenNoResults(): void
    {
        $pairs = $this->db->fetchPairs('SELECT id, name FROM users WHERE 1 = 0');
        $this->assertEquals([], $pairs);
    }

    // =========================================================================
    // IN Statement Processing Tests
    // =========================================================================

    public function testInStatementWithArrayOfIntegers(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE id IN(?)', [[1, 2]]);
        $this->assertCount(2, $rows);
    }

    public function testInStatementWithArrayOfStrings(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE name IN(?)', [['John', 'Jane']]);
        $this->assertCount(2, $rows);
    }

    public function testInStatementWithEmptyArray(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE id IN(?)', [[]]);
        $this->assertCount(0, $rows); // IN(NULL) matches nothing
    }

    public function testInStatementWithSingleValue(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE id IN(?)', [1]);
        $this->assertCount(1, $rows);
    }

    public function testMultipleInStatements(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM users WHERE id IN(?) AND name IN(?)',
            [[1, 2, 3], ['John', 'Bob']]
        );
        $this->assertCount(2, $rows); // John (id=1) and Bob (id=3)
    }

    public function testInStatementWithOtherParams(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM users WHERE id > ? AND name IN(?)',
            [0, ['John', 'Jane']]
        );
        $this->assertCount(2, $rows);
    }

    // =========================================================================
    // insert() Tests
    // =========================================================================

    public function testInsertSingleRow(): void
    {
        $id = $this->db->insert('users', ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->assertEquals('4', $id);

        $row = $this->db->fetchRow('SELECT * FROM users WHERE id = ?', [$id]);
        $this->assertEquals('Alice', $row['name']);
        $this->assertEquals('alice@example.com', $row['email']);
    }

    public function testInsertBulkRows(): void
    {
        $id = $this->db->insert('users', [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Charlie', 'email' => 'charlie@example.com'],
        ]);

        // Last insert ID should be 5 (Charlie)
        $this->assertEquals('5', $id);

        // Verify both rows were inserted
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE id > 3 ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
        $this->assertEquals('Charlie', $rows[1]['name']);
    }

    public function testInsertBulkWithEmptyArrayThrows(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Cannot perform bulk insert with empty data array');

        $this->db->insert('users', [[]]);
    }

    public function testInsertBulkWithMismatchedColumnCountThrows(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('columns');

        $this->db->insert('users', [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Charlie'], // Missing email column
        ]);
    }

    // =========================================================================
    // update() Tests
    // =========================================================================

    public function testUpdateReturnsAffectedRowCount(): void
    {
        $count = $this->db->update('users', ['name' => 'Updated'], 'name LIKE ?', ['J%']);
        $this->assertEquals(2, $count); // John and Jane

        $rows = $this->db->fetchAll('SELECT * FROM users WHERE name = ?', ['Updated']);
        $this->assertCount(2, $rows);
    }

    public function testUpdateSingleRow(): void
    {
        $count = $this->db->update('users', ['email' => 'newemail@example.com'], 'id = ?', [1]);
        $this->assertEquals(1, $count);

        $row = $this->db->fetchRow('SELECT * FROM users WHERE id = ?', [1]);
        $this->assertEquals('newemail@example.com', $row['email']);
    }

    public function testUpdateNoMatchingRows(): void
    {
        $count = $this->db->update('users', ['name' => 'Nobody'], 'id = ?', [999]);
        $this->assertEquals(0, $count);
    }

    // =========================================================================
    // delete() Tests
    // =========================================================================

    public function testDeleteReturnsDeletedRowCount(): void
    {
        $count = $this->db->delete('users', 'name LIKE ?', ['J%']);
        $this->assertEquals(2, $count); // John and Jane

        $rows = $this->db->fetchAll('SELECT * FROM users');
        $this->assertCount(1, $rows);
        $this->assertEquals('Bob', $rows[0]['name']);
    }

    public function testDeleteSingleRow(): void
    {
        $count = $this->db->delete('users', 'id = ?', [1]);
        $this->assertEquals(1, $count);

        $rows = $this->db->fetchAll('SELECT * FROM users');
        $this->assertCount(2, $rows);
    }

    public function testDeleteNoMatchingRows(): void
    {
        $count = $this->db->delete('users', 'id = ?', [999]);
        $this->assertEquals(0, $count);
    }

    // =========================================================================
    // transaction() Tests
    // =========================================================================

    public function testTransactionCommitsOnSuccess(): void
    {
        $result = $this->db->transaction(function ($db) {
            $db->runQuery('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
            return $db->lastInsertId();
        });

        $this->assertEquals('4', $result);

        $row = $this->db->fetchRow('SELECT * FROM users WHERE id = ?', [4]);
        $this->assertEquals('Alice', $row['name']);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->db->transaction(function ($db) {
                $db->runQuery('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
                throw new \RuntimeException('Something went wrong');
            });
        } catch (\RuntimeException $e) {
            $this->assertEquals('Something went wrong', $e->getMessage());
        }

        // Verify the insert was rolled back
        $rows = $this->db->fetchAll('SELECT * FROM users');
        $this->assertCount(3, $rows); // Still only the original 3 rows
    }

    public function testTransactionReturnsCallbackValue(): void
    {
        $result = $this->db->transaction(function () {
            return 'hello world';
        });

        $this->assertEquals('hello world', $result);
    }

    public function testTransactionRethrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Test exception');

        $this->db->transaction(function () {
            throw new \InvalidArgumentException('Test exception');
        });
    }

    // =========================================================================
    // fetchField (inherited from PdoWrapper) Tests
    // =========================================================================

    public function testFetchFieldReturnsValue(): void
    {
        $name = $this->db->fetchField('SELECT name FROM users WHERE id = ?', [1]);
        $this->assertEquals('John', $name);
    }

    public function testFetchFieldReturnsFirstColumn(): void
    {
        $id = $this->db->fetchField('SELECT id, name FROM users WHERE id = ?', [1]);
        $this->assertEquals(1, $id);
    }

}

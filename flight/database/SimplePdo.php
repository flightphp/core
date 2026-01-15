<?php

declare(strict_types=1);

namespace flight\database;

use PDO;
use PDOStatement;
use PDOException;
use flight\util\Collection;

class SimplePdo extends PdoWrapper
{
    protected int $maxQueryMetrics = 1000;

    /**
     * Constructor for the SimplePdo class.
     *
     * @param string $dsn The Data Source Name (DSN) for the database connection.
     * @param string|null $username The username for the database connection.
     * @param string|null $password The password for the database connection.
     * @param array<int|string, mixed>|null $pdoOptions An array of options for the PDO connection.
     * @param array<string, mixed> $options An array of options for the SimplePdo class
     */
    public function __construct(
        ?string $dsn = null,
        ?string $username = null,
        ?string $password = null,
        ?array $pdoOptions = null,
        array $options = []
    ) {
        // Set default fetch mode if not provided in pdoOptions
        if (isset($pdoOptions[PDO::ATTR_DEFAULT_FETCH_MODE]) === false) {
            $pdoOptions = $pdoOptions ?? [];
            $pdoOptions[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        }

        // Pass to parent (PdoWrapper) constructor
        parent::__construct($dsn, $username, $password, $pdoOptions, false); // APM off by default here

        // Modern defaults – override parent's behavior where needed
        $defaults = [
            'trackApmQueries' => false,     // still optional
            'maxQueryMetrics' => 1000,
        ];

        $options = array_merge($defaults, $options);

        $this->trackApmQueries = (bool) $options['trackApmQueries'];
        $this->maxQueryMetrics = (int) $options['maxQueryMetrics'];

        // If APM is enabled, pull connection metrics (same as parent)
        if ($this->trackApmQueries && $dsn !== null) {
            $this->connectionMetrics = $this->pullDataFromDsn($dsn);
        }
    }

    /**
     * Pulls one row from the query
     *
     * Ex: $row = $db->fetchRow("SELECT * FROM table WHERE something = ?", [ $something ]);
     *
     * @param string $sql   - Ex: "SELECT * FROM table WHERE something = ?"
     * @param array<int|string,mixed> $params - Ex: [ $something ]
     *
     * @return ?Collection
     */
    public function fetchRow(string $sql, array $params = []): ?Collection
    {
        // Smart LIMIT 1 addition (avoid if already present at end or complex query)
        if (!preg_match('/\sLIMIT\s+\d+(?:\s+OFFSET\s+\d+)?\s*$/i', trim($sql))) {
            $sql .= ' LIMIT 1';
        }

        $results = $this->fetchAll($sql, $params);

        return $results ? $results[0] : null;
    }

    /**
     * Don't worry about this guy. Converts stuff for IN statements
     *
     * Ex: $row = $db->fetchAll("SELECT * FROM table WHERE id = ? AND something IN(?), [ $id, [1,2,3] ]);
     *      Converts this to "SELECT * FROM table WHERE id = ? AND something IN(?,?,?)"
     *
     * @param string $sql    the sql statement
     * @param array<int|string,mixed>  $params the params for the sql statement
     *
     * @return array<string,string|array<int|string,mixed>>
     */
    protected function processInStatementSql(string $sql, array $params = []): array
    {
        // First, find all placeholders (?) in the original SQL and their positions
        // We need to track which are IN(?) patterns vs regular ?
        $originalSql = $sql;
        $newParams = [];
        $paramIndex = 0;

        // Find all ? positions and whether they're part of IN(?)
        $pattern = '/IN\s*\(\s*\?\s*\)/i';
        $inPositions = [];
        if (preg_match_all($pattern, $originalSql, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $inPositions[] = $match[1];
            }
        }

        // Process from right to left so string positions don't shift
        $inPositions = array_reverse($inPositions);

        // First, figure out which param indices correspond to IN(?) patterns
        $questionMarkPositions = [];
        $pos = 0;
        while (($pos = strpos($originalSql, '?', $pos)) !== false) {
            $questionMarkPositions[] = $pos;
            $pos++;
        }

        // Map each ? position to whether it's inside an IN()
        $inParamIndices = [];
        foreach ($inPositions as $inPos) {
            // Find which ? is inside this IN()
            foreach ($questionMarkPositions as $idx => $qPos) {
                if ($qPos > $inPos && $qPos < $inPos + 20) { // IN(?) is typically under 20 chars
                    $inParamIndices[$idx] = true;
                    break;
                }
            }
        }

        // Now build the new SQL and params
        $newSql = $originalSql;
        $offset = 0;

        // Process each param
        for ($i = 0; $i < count($params); $i++) {
            if (isset($inParamIndices[$i])) {
                $value = $params[$i];

                // Find the next IN(?) in the remaining SQL
                if (preg_match($pattern, $newSql, $match, PREG_OFFSET_CAPTURE, $offset)) {
                    $matchPos = $match[0][1];
                    $matchLen = strlen($match[0][0]);

                    if (!is_array($value)) {
                        // Single value, keep as-is
                        $newParams[] = $value;
                        $newSql = substr_replace($newSql, 'IN(?)', $matchPos, $matchLen);
                        $offset = $matchPos + 5;
                    } elseif (count($value) === 0) {
                        // Empty array
                        $newSql = substr_replace($newSql, 'IN(NULL)', $matchPos, $matchLen);
                        $offset = $matchPos + 8;
                    } else {
                        // Expand array
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $replacement = "IN($placeholders)";
                        $newSql = substr_replace($newSql, $replacement, $matchPos, $matchLen);
                        $newParams = array_merge($newParams, $value);
                        $offset = $matchPos + strlen($replacement);
                    }
                }
            } else {
                $newParams[] = $params[$i];
            }
        }

        return ['sql' => $newSql, 'params' => $newParams];
    }

    /**
     * Use this for INSERTS, UPDATES, or if you plan on using a SELECT in a while loop
     *
     * Ex: $statement = $db->runQuery("SELECT * FROM table WHERE something = ?", [ $something ]);
     *      while($row = $statement->fetch()) {
     *          // ...
     *      }
     *
     *  $db->runQuery("INSERT INTO table (name) VALUES (?)", [ $name ]);
     *  $db->runQuery("UPDATE table SET name = ? WHERE id = ?", [ $name, $id ]);
     *
     * @param string $sql       - Ex: "SELECT * FROM table WHERE something = ?"
     * @param array<int|string,mixed> $params   - Ex: [ $something ]
     *
     * @return PDOStatement
     */
    public function runQuery(string $sql, array $params = []): PDOStatement
    {
        $processed = $this->processInStatementSql($sql, $params);
        $sql = $processed['sql'];
        $params = $processed['params'];

        $start = $this->trackApmQueries ? microtime(true) : 0;
        $memoryStart = $this->trackApmQueries ? memory_get_usage() : 0;

        $stmt = $this->prepare($sql);
        if ($stmt === false) {
            throw new PDOException(
                "Prepare failed: " . ($this->errorInfo()[2] ?? 'Unknown error')
            );
        }

        $stmt->execute($params);

        if ($this->trackApmQueries) {
            $this->queryMetrics[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => microtime(true) - $start,
                'row_count' => $stmt->rowCount(),
                'memory_usage' => memory_get_usage() - $memoryStart
            ];

            // Cap to prevent memory leak in long-running processes
            if (count($this->queryMetrics) > $this->maxQueryMetrics) {
                array_shift($this->queryMetrics);
            }
        }

        return $stmt;
    }

    /**
     * Pulls all rows from the query
     *
     * Ex: $rows = $db->fetchAll("SELECT * FROM table WHERE something = ?", [ $something ]);
     * foreach($rows as $row) {
     *      // ...
     * }
     *
     * @param string $sql   - Ex: "SELECT * FROM table WHERE something = ?"
     * @param array<int|string,mixed> $params   - Ex: [ $something ]
     *
     * @return array<int,Collection|array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->runQuery($sql, $params); // Already processes IN statements and tracks metrics
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => new Collection($row), $results);
    }

    /**
     * Fetch a single column as an array
     *
     * Ex: $ids = $db->fetchColumn("SELECT id FROM users WHERE active = ?", [1]);
     *
     * @param string $sql
     * @param array<int|string,mixed> $params
     *
     * @return array<int,mixed>
     */
    public function fetchColumn(string $sql, array $params = []): array
    {
        $stmt = $this->runQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Fetch results as key-value pairs (first column as key, second as value)
     *
     * Ex: $userNames = $db->fetchPairs("SELECT id, name FROM users");
     *
     * @param string $sql
     * @param array<int|string,mixed> $params
     *
     * @return array<string|int,mixed>
     */
    public function fetchPairs(string $sql, array $params = []): array
    {
        $stmt = $this->runQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Execute a callback within a transaction
     *
     * Ex: $db->transaction(function($db) {
     *         $db->runQuery("INSERT INTO users (name) VALUES (?)", ['John']);
     *         $db->runQuery("INSERT INTO logs (action) VALUES (?)", ['user_created']);
     *         return $db->lastInsertId();
     *     });
     *
     * @param callable $callback
     *
     * @return mixed The return value of the callback
     *
     * @throws \Throwable
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Insert one or more rows and return the last insert ID
     *
     * Single insert:
     *   $id = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
     *
     * Bulk insert:
     *   $id = $db->insert('users', [
     *       ['name' => 'John', 'email' => 'john@example.com'],
     *       ['name' => 'Jane', 'email' => 'jane@example.com'],
     *   ]);
     *
     * @param string $table
     * @param array<string,mixed>|array<int,array<string,mixed>> $data Single row or array of rows
     *
     * @return string Last insert ID (for single insert or last row of bulk insert)
     */
    public function insert(string $table, array $data): string
    {
        // Detect if this is a bulk insert (array of arrays)
        $isBulk = isset($data[0]) && is_array($data[0]);

        if ($isBulk) {
            // Bulk insert
            if (empty($data[0])) {
                throw new PDOException("Cannot perform bulk insert with empty data array");
            }

            // Use first row to determine columns
            $firstRow = $data[0];
            $columns = array_keys($firstRow);
            $columnCount = count($columns);

            // Validate all rows have same columns
            foreach ($data as $index => $row) {
                if (count($row) !== $columnCount) {
                    throw new PDOException(
                        "Row $index has " . count($row) . " columns, expected $columnCount"
                    );
                }
            }

            // Build placeholders for multiple rows: (?,?), (?,?), (?,?)
            $rowPlaceholder = '(' . implode(',', array_fill(0, $columnCount, '?')) . ')';
            $allPlaceholders = implode(', ', array_fill(0, count($data), $rowPlaceholder));

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES %s",
                $table,
                implode(', ', $columns),
                $allPlaceholders
            );

            // Flatten all row values into a single params array
            $params = [];
            foreach ($data as $row) {
                $params = array_merge($params, array_values($row));
            }

            $this->runQuery($sql, $params);
        } else {
            // Single insert
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($data), '?');

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $this->runQuery($sql, array_values($data));
        }

        return $this->lastInsertId();
    }

    /**
     * Update rows and return the number of affected rows
     *
     * Ex: $affected = $db->update('users', ['name' => 'Jane'], 'id = ?', [1]);
     *
     * Note: SQLite's rowCount() returns the number of rows where data actually changed.
     * If you UPDATE a row with the same values it already has, rowCount() will return 0.
     * This differs from MySQL's behavior when using PDO::MYSQL_ATTR_FOUND_ROWS.
     *
     * @param string $table
     * @param array<string,mixed> $data
     * @param string $where - e.g., "id = ?"
     * @param array<int|string,mixed> $whereParams
     *
     * @return int Number of affected rows (rows where data actually changed)
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "$column = ?";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $sets),
            $where
        );

        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->runQuery($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows and return the number of deleted rows
     *
     * Ex: $deleted = $db->delete('users', 'id = ?', [1]);
     *
     * @param string $table
     * @param string $where - e.g., "id = ?"
     * @param array<int|string,mixed> $whereParams
     *
     * @return int Number of deleted rows
     */
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->runQuery($sql, $whereParams);
        return $stmt->rowCount();
    }
}

<?php

declare(strict_types=1);

namespace flight\database;

use flight\util\Collection;
use PDO;
use PDOStatement;

class PdoWrapper extends PDO
{
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
        $processed_sql_data = $this->processInStatementSql($sql, $params);
        $sql = $processed_sql_data['sql'];
        $params = $processed_sql_data['params'];
        $statement = $this->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /**
     * Pulls one field from the query
     *
     * Ex: $id = $db->fetchField("SELECT id FROM table WHERE something = ?", [ $something ]);
     *
     * @param string $sql   - Ex: "SELECT id FROM table WHERE something = ?"
     * @param array<int|string,mixed> $params - Ex: [ $something ]
     *
     * @return mixed
     */
    public function fetchField(string $sql, array $params = [])
    {
        $result = $this->fetchRow($sql, $params);
        $data = $result->getData();
        return reset($data);
    }

    /**
     * Pulls one row from the query
     *
     * Ex: $row = $db->fetchRow("SELECT * FROM table WHERE something = ?", [ $something ]);
     *
     * @param string $sql   - Ex: "SELECT * FROM table WHERE something = ?"
     * @param array<int|string,mixed> $params - Ex: [ $something ]
     *
     * @return Collection
     */
    public function fetchRow(string $sql, array $params = []): Collection
    {
        $sql .= stripos($sql, 'LIMIT') === false ? ' LIMIT 1' : '';
        $result = $this->fetchAll($sql, $params);
        return count($result) > 0 ? $result[0] : new Collection();
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
     * @return array<int,Collection>
     */
    public function fetchAll(string $sql, array $params = [])
    {
        $processed_sql_data = $this->processInStatementSql($sql, $params);
        $sql = $processed_sql_data['sql'];
        $params = $processed_sql_data['params'];
        $statement = $this->prepare($sql);
        $statement->execute($params);
        $results = $statement->fetchAll();
        if (is_array($results) === true && count($results) > 0) {
            foreach ($results as &$result) {
                $result = new Collection($result);
            }
        } else {
            $results = [];
        }
        return $results;
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
        // Replace "IN(?)" with "IN(?,?,?)"
        $sql = preg_replace('/IN\s*\(\s*\?\s*\)/i', 'IN(?)', $sql);

        $current_index = 0;
        while (($current_index = strpos($sql, 'IN(?)', $current_index)) !== false) {
            $preceeding_count = substr_count($sql, '?', 0, $current_index - 1);

            $param = $params[$preceeding_count];
            $question_marks = '?';

            if (is_string($param) || is_array($param)) {
                $params_to_use = $param;
                if (is_string($param)) {
                    $params_to_use = explode(',', $param);
                }

                foreach ($params_to_use as $key => $value) {
                    if (is_string($value)) {
                        $params_to_use[$key] = trim($value);
                    }
                }

                $question_marks = join(',', array_fill(0, count($params_to_use), '?'));
                $sql = substr_replace($sql, $question_marks, $current_index + 3, 1);

                array_splice($params, $preceeding_count, 1, $params_to_use);
            }

            $current_index += strlen($question_marks) + 4;
        }

        return ['sql' => $sql, 'params' => $params];
    }
}

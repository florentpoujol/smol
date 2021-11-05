<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Database;

use FlorentPoujol\SmolFramework\SmolFrameworkException;
use PDO;
use PDOStatement;
use Stringable;

final class QueryBuilder implements Stringable
{
    public function __construct(PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        }
    }

    public const ACTION_INSERT = 'INSERT';
    public const ACTION_INSERT_REPLACE = 'INSERT OR REPLACE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_SELECT = 'SElECT';

    private string $action = '';

    /**
     * @var array<string>
     */
    private array $fields = [];

    /**
     * @param iterable<mixed> $rows
     */
    public function insertMany(iterable $rows, string $actionType = self::ACTION_INSERT): bool
    {
        $this->action = $actionType;

        if (is_array($rows)) {
            $rowsArray = $rows;
        } else {
            $rowsArray = iterator_to_array($rows);
        }

        $this->setInputParams($rowsArray);

        return $this->pdo
            ->prepare($this->toSql())
            ->execute($rowsArray);
    }

    public function insertSingle(mixed $row): bool
    {
        return $this->insertMany([$row]);
    }

    /**
     * @param iterable<mixed> $rows
     */
    public function insertOrReplaceMany(iterable $rows): bool
    {
        return $this->insertMany($rows, self::ACTION_INSERT_REPLACE);
    }

    public function insertOrReplaceSingle(mixed $row): bool
    {
        return $this->insertMany([$row], self::ACTION_INSERT_REPLACE);
    }

    private function buildInsertQueryString(): string
    {
        $fields = $this->fields === [] ? $this->fieldsFromInput : $this->fields;
        if ($fields === []) {
            throw new SmolFrameworkException('No field is set for INSERT action');
        }

        // build a single row
        $fieldsCount = count($fields);
        $rowParts = str_repeat('?, ', $fieldsCount);
        if ($this->inputIsAssoc) {
            $rowParts = '';
            foreach ($fields as $field) {
                $rowParts .= ":$field, ";
            }
        }
        $row = '(' . substr($rowParts, 0, -2) . '), ';

        // build multiple row if needed
        $rows = $row; // for when inputParams contain only a single row
        $rowCount = (int) (count($this->boundParams) / $fieldsCount);
        if ($rowCount >= 2) { // multiple rows are inserted
            $rows = str_repeat($row, $rowCount);
        }

        return "$this->action INTO $this->table (" . implode(', ', $fields) .
            ') VALUES ' . substr($rows, 0, -2);
    }

    /**
     * @param iterable<mixed> $rows
     */
    public function updateMany(iterable $rows): bool
    {
        return $this->insertMany($rows, self::ACTION_UPDATE);
    }

    public function updateSingle(mixed $row): bool
    {
        return $this->insertMany([$row], self::ACTION_UPDATE);
    }

    private function buildUpdateQueryString(): string
    {
        $fields = $this->fields === [] ? $this->fieldsFromInput : $this->fields;
        if ($fields === []) {
            throw new SmolFrameworkException('No field is set for UPDATE action');
        }

        $query = "UPDATE $this->table SET ";

        foreach ($fields as $field) {
            if ($this->inputIsAssoc) {
                $query .= "$field = :$field, ";
            } else {
                $query .= "$field = ?, ";
            }
        }

        $query = substr($query, 0, -2) . ' ' . $this->buildWhereQueryString();

        return rtrim($query);
    }

    public function delete(): bool
    {
        $this->action = self::ACTION_DELETE;

        return $this->pdo
            ->prepare($this->toSql())
            ->execute($this->boundParams);
    }

    /**
     * @param array<string>|string $fields
     */
    public function selectMany(array|string $fields = []): mixed
    {
        $this->action = self::ACTION_SELECT;
        $this->fields = (array) $fields;

        $statement = $this->pdo->prepare($this->toSql());
        $statement->execute($this->boundParams);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string>|string $fields
     */
    public function selectSingle(array|string $fields = []): mixed
    {
        $this->limit(1);

        $this->action = self::ACTION_SELECT;
        $this->fields = (array) $fields;

        $statement = $this->pdo->prepare($this->toSql());
        $statement->execute($this->boundParams);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function buildSelectQueryString(): string
    {
        $fields = $this->fields;
        if ($fields === []) {
            $fields = '*';
        } else {
            $fields = implode(', ', $fields);
        }
        // do not force to prefix the fields with the table name because it is not necessarily what is wanted

        $query = "SELECT $fields FROM $this->table ";
        $query .= $this->buildJoinQueryString();
        $query .= $this->buildWhereQueryString();
        $query .= $this->buildGroupByQueryString();
        $query .= $this->buildHavingQueryString();
        $query .= $this->buildOrderByQueryString();
        $query .= $this->limit;
        $query .= $this->offset;

        return rtrim($query);
    }

    // --------------------------------------------------

    public function count(): int
    {
        // calling selectMany() here since selectSingle() add a LIMIT clase
        return $this->selectMany('COUNT(*) as _count')[0]['_count'] ?? 0;
    }

    // --------------------------------------------------
    // table

    private string $table = '';

    public function fromTable(string $tableName): self
    {
        $this->table = $tableName;

        return $this;
    }

    public function inTable(string $tableName): self
    {
        $this->table = $tableName;

        return $this;
    }

    // --------------------------------------------------
    // join

    /** @var array<int, string> Table name by join id */
    private array $join = [];

    private int $lastJoinId = -1;

    /** @var array<int, array<array<string, mixed>>> "on" clauses by join id */
    private array $onClauses = [];
    // unlike where and having
    // on is an array or conditional arrays

    private function buildJoinQueryString(): string
    {
        $str = '';
        foreach ($this->join as $id => $joinTable) {
            $str .= $joinTable . 'ON ';

            if (! isset($this->onClauses[$id]) || $this->onClauses[$id] === []) {
                throw new SmolFrameworkException("Join statement without any ON clause: $joinTable");
            }

            $str .= $this->buildConditionalQueryString($this->onClauses[$id]) . ' ';
        }

        return $str;
    }

    public function join(string $tableName, string $alias = null, string $joinType = 'INNER'): self
    {
        if ($alias !== null) {
            $tableName .= " AS $alias";
        }

        $joinType .= ' JOIN';

        $this->join[] = "$joinType $tableName ";
        ++$this->lastJoinId;

        return $this;
    }

    public function leftJoin(string $tableName, string $alias = null): self
    {
        return $this->join($tableName, $alias, 'LEFT');
    }

    public function rightJoin(string $tableName, string $alias = null): self
    {
        return $this->join($tableName, $alias, 'RIGHT');
    }

    public function fullJoin(string $tableName, string $alias = null): self
    {
        return $this->join($tableName, $alias, 'FULL');
    }

    public function on(callable|string $field, string $sign = null, int|string $value = null, string $cond = 'AND'): self
    {
        $this->onClauses[$this->lastJoinId] ??= [];

        return $this->addConditionalClause($this->onClauses[$this->lastJoinId], $field, $sign, $value, $cond);
    }

    public function orOn(callable|string $field, string $sign = null, int|string $value = null): self
    {
        return $this->on($field, $sign, $value, 'OR');
    }

    // --------------------------------------------------
    // where

    /** @var array<array{condition: string, expression: string}>|array<array{condition: string, clauses: array<string, string|array>}> */
    private array $whereClauses = [];

    private function buildWhereQueryString(): string
    {
        $where = $this->buildConditionalQueryString($this->whereClauses);
        if ($where !== '') {
            $where = "WHERE $where ";
        }

        return $where;
    }

    public function whereRaw(string $raw): self
    {
        return $this->addConditionalClause($this->whereClauses, $raw);
    }

    /**
     * @param array<string, bool|int|string>|callable|string $field
     */
    public function where(array|callable|string $field, string $sign = null, mixed $value = null, string $cond = 'AND'): self
    {
        return $this->addConditionalClause($this->whereClauses, $field, $sign, $value, $cond);
    }

    public function orWhere(callable|string $field, string $sign = null, mixed $value = null): self
    {
        return $this->where($field, $sign, $value, 'OR');
    }

    public function whereNull(string $field): self
    {
        return $this->where("$field IS NULL");
    }

    public function orWhereNull(string $field): self
    {
        return $this->orWhere("$field IS NULL");
    }

    public function whereNotNull(string $field): self
    {
        return $this->where("$field IS NOT NULL");
    }

    public function orWhereNotNull(string $field): self
    {
        return $this->orWhere("$field IS NOT NULL");
    }

    public function whereBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->where("$field BETWEEN $min AND $max");
    }

    public function orWhereBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->orWhere("$field BETWEEN $min AND $max");
    }

    public function whereNotBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->where("$field NOT BETWEEN $min AND $max");
    }

    public function orWhereNotBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->orWhere("$field NOT BETWEEN $min AND $max");
    }

    /**
     * @param array<mixed> $values
     */
    public function whereIn(string $field, array $values): self
    {
        $values = implode(', ', $values);

        return $this->where("$field IN ($values)");
    }

    /**
     * @param array<mixed> $values
     */
    public function orWhereIn(string $field, array $values): self
    {
        $values = implode(', ', $values);

        return $this->orWhere("$field IN ($values)");
    }

    /**
     * @param array<mixed> $values
     */
    public function whereNotIn(string $field, array $values): self
    {
        $values = implode(', ', $values);

        return $this->where("$field NOT IN ($values)");
    }

    /**
     * @param array<mixed> $values
     */
    public function orWhereNotIn(string $field, array $values): self
    {
        $values = implode(', ', $values);

        return $this->orWhere("$field NOT IN ($values)");
    }

    // --------------------------------------------------
    // order by, group by, having

    /** @var array<string> */
    private array $orderBy = [];

    private function buildOrderByQueryString(): string
    {
        if ($this->orderBy === []) {
            return '';
        }

        return 'ORDER BY ' . implode(', ', $this->orderBy) . ' ';
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        $this->orderBy[] = "$field $direction";

        return $this;
    }

    public function smallestFirst(string $field): self
    {
        return $this->orderBy($field, 'ASC');
    }

    public function oldestFirst(string $field): self
    {
        return $this->orderBy($field, 'ASC');
    }

    public function biggestFirst(string $field): self
    {
        return $this->orderBy($field, 'DESC');
    }

    public function mostRecentFirst(string $field): self
    {
        return $this->orderBy($field, 'DESC');
    }

    /** @var array<string> */
    private array $groupBy = [];

    private function buildGroupByQueryString(): string
    {
        if ($this->groupBy === []) {
            return '';
        }

        return 'GROUP BY ' . implode(', ', $this->groupBy) . ' ';
    }

    /**
     * @param array<string>|string $fields
     */
    public function groupBy(array|string $fields): self
    {
        if (is_string($fields)) {
            $this->groupBy[] = $fields;
        } else {
            $this->groupBy = $fields;
        }

        return $this;
    }

    /** @var array<string> */
    private array $having = [];

    private function buildHavingQueryString(): string
    {
        $having = $this->buildConditionalQueryString($this->having);
        if ($having === '') {
            return '';
        }

        return "HAVING $having ";
    }

    public function having(string $field, string $sign = null, mixed $value = null, string $cond = 'AND'): self
    {
        return $this->addConditionalClause($this->having, $field, $sign, $value, $cond);
    }

    public function orHaving(string $field, string $sign = null, mixed $value = null): self
    {
        return $this->having($field, $sign, $value, 'OR');
    }

    // --------------------------------------------------
    // limit offset

    private string $limit = '';

    public function limit(int $limit): self
    {
        $this->limit = "LIMIT $limit ";

        return $this;
    }

    private string $offset = '';

    public function offset(int $offset): self
    {
        $this->offset = "OFFSET $offset ";

        return $this;
    }

    // --------------------------------------------------
    // non-query building methods

    /**
     * @param null|array<string, string>|array<string> $inputParams
     *                                                              - an associative array of named parameters
     *                                                              - or an in-order array of parameters, when placeholders are ?
     *                                                              - an array of these two kinds of array, which is useful to insert or update several rows with the same query
     *
     * @return bool|\PDOStatement|string
     *                                   - `false` when the query is unsuccessful
     *                                   - `true` when the query is successful and the action is `INSERT OR REPLACE`, `UPDATE` or `DELETE`.
     *                                   - the last inserted id when the action is `INSERT`.
     *                                   - the PDOStatement object when the action is `SELECT`.
     */
    public function execute(array $inputParams = null): bool|PDOStatement|string
    {
        if ($inputParams !== null) {
            $this->setInputParams($inputParams);
        }

        $statement = $this->pdo->prepare($this->toSql());
        $success = $statement->execute($this->boundParams);

        if (
            ! $success ||
            $this->action === self::ACTION_INSERT_REPLACE ||
            $this->action === self::ACTION_UPDATE ||
            $this->action === self::ACTION_DELETE
        ) {
            return $success;
        }

        if ($this->action === self::ACTION_INSERT) {
            return $this->pdo->lastInsertId();
        }

        return $statement; // ACTION_SELECT
    }

    public function __toString(): string
    {
        if ($this->action === self::ACTION_INSERT || $this->action === self::ACTION_INSERT_REPLACE) {
            return $this->buildInsertQueryString();
        }

        if ($this->action === self::ACTION_SELECT) {
            return $this->buildSelectQueryString();
        }

        if ($this->action === self::ACTION_UPDATE) {
            return $this->buildUpdateQueryString();
        }

        if ($this->action === self::ACTION_DELETE) {
            return rtrim("DELETE FROM $this->table " . $this->buildWhereQueryString());
        }

        throw new SmolFrameworkException('QueryBuilder::toString() error: no action has been set');
    }

    public function toSql(): string
    {
        return $this->__toString();
    }

    /**
     * @param array<array{condition: string, expression: string}>|array<array{condition: string, clauses: array<string, string|array>}> $clauses
     */
    private function buildConditionalQueryString(array $clauses): string
    {
        // each clause entry is an array
        /*
        [
            'condition' => 'AND' // 'OR'
            'expression' => 'expression'
        ]
        // or
        [
            'condition' => 'AND' // 'OR'
            'clauses' => [
                [
                    'condition' => 'AND'
                    'expression' => 'expression'
                ],
                ...
            ]
        ]
        */

        if ($clauses === []) {
            return '';
        }

        $str = '';
        /**
         * @var array{condition: string, expression: string}|array{condition: string, clauses: array<string, string|array>} $clause
         */
        foreach ($clauses as $id => $clause) {
            if ($id > 0) {
                $str .= $clause['condition'] . ' ';
            }

            if (isset($clause['expression'])) {
                $str .= $clause['expression'] . ' ';

                continue;
            }

            $str .= '(' . $this->buildConditionalQueryString($clause['clauses']) . ') '; // @phpstan-ignore-line
        }

        return rtrim($str);
    }

    /**
     * @param array<ConditionalClause|ConditionalGroup> $clauses
     * @param array<string, mixed>|callable|string      $field
     *
     * @raturn array<>|array
     */
    private function addConditionalClause(
        array $clauses,
        array|callable|string $field,
        string $sign = null,
        string|int|bool $value = null,
        string $condition = 'AND'
    ): ConditionalClause|ConditionalGroup {
        if (is_callable($field)) {
            $beforeCount = count($clauses);
            $field($this);
            $afterCount = count($clauses);
            if ($afterCount === $beforeCount) {
                return $this;
            }

            $clause = new ConditionalGroup();
            $clause->condition = $condition;
            $clause->clauses = array_splice($clauses, $beforeCount);

            return $this;
        }

        if (is_array($field)) {
            // an assoc array, like if the where() was called several times
            foreach ($field as $fieldName => $_value) {
                $this->addConditionalClause($clauses, "$fieldName = :$fieldName", condition: $condition);
            }

            $this->setInputParams($field);

            return $this;
        }

        $clause = new ConditionalClause();
        $clause->condition = $condition;

        if ($sign === null && $value === null) {
            $clause->expression = $field;
        } elseif ($sign !== null && $value === null) {
            $clause->expression = "$field = " . $this->escapeValue($sign);
        } elseif ($sign !== null && $value !== null) {
            $clause->expression = "$field $sign " . $this->escapeValue($value);
        }

        // $clauses[] = $clause;

        return $clause;
    }

    private function escapeValue(bool|int|string $value): int|string
    {
        if (
            $value === '?'
            || (is_string($value) && $value[0] === ':') // suppose named placeholder
        ) {
            return $value;
        }

        if (is_bool($value) || is_int($value)) {
            return (int) $value;
        }

        $quoted = $this->pdo->quote($value, PDO::PARAM_STR);
        if ($quoted === false) { // @phpstan-ignore-line (PHPStan thinks $quoted can't be null)
            throw new SmolFrameworkException("PDO can't quote value '$value' for current DB driver");
        }

        return $quoted;
    }

    /** @var array<string, string>|array<string> */
    private array $boundParams = [];

    /** @var array<string> */
    private array $fieldsFromInput = [];

    private bool $inputIsAssoc = true; // set to true by default so that it generates named placeholder from fields name when the user has not supplied an inputParams

    /**
     * @param array<string, string>|array<string> $inputParams
     *
     * @see self::execute();
     */
    private function setInputParams(array $inputParams): void
    {
        if ($inputParams === []) {
            $this->inputIsAssoc = true;
            $this->boundParams = [];
            $this->fieldsFromInput = [];

            return;
        }

        // get format of input
        // and flatten it when needed
        $formattedInput = $inputParams;

        $keys = array_keys($inputParams);
        $this->inputIsAssoc = is_string($keys[0]);

        if ($this->inputIsAssoc) {
            // save fields from input when data is assoc array, if we need them later
            $this->fieldsFromInput = $keys;
        } elseif (is_array($inputParams[0])) {
            $keys = array_keys($inputParams[0]);
            if (is_string($keys[0])) {
                $this->fieldsFromInput = $keys;
                // input is assoc but will be flatten in a regular array just after
                // so don't set inputIsAssoc = true here
            }

            // flatten input
            $formattedInput = [];
            foreach ($inputParams as $params) {
                $formattedInput[] = array_values($params);
            }

            $formattedInput = array_merge($formattedInput);
        }

        $this->boundParams = $formattedInput;
    }

    private PDO $pdo;

    public function setPdo(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }
}

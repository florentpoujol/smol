<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Database;

use Exception;
use FlorentPoujol\SmolFramework\SmolFrameworkException;
use PDO;

final class QueryBuilder
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function new(): self
    {
        return (new self($this->pdo))->inTable($this->table);
        // for some reason with SQLite memory, this iseems to reset the connexion, and thus the DB ?
    }

    public function reset(): self
    {
        $this->action = '';

        $this->fields = [];
        $this->insertedRowCount = 0;

        $this->join = [];
        $this->lastJoinId = -1;
        $this->onClauses = [];
        $this->joinBindings = [];

        $this->whereClauses = [];
        $this->whereBindings = [];

        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit = '';
        $this->offset = '';

        $this->havingClauses = [];
        $this->havingBindings = [];

        return $this;
    }

    public function getBindings(): array
    {
        return array_merge($this->joinBindings, $this->whereBindings, $this->havingBindings);
    }

    public const ACTION_INSERT = 'INSERT';
    public const ACTION_INSERT_REPLACE = 'INSERT OR REPLACE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_SELECT = 'SElECT';

    private string $action = '';

    // --------------------------------------------------
    // select

    /**
     * @param array<string>|string $fields
     *
     * @return array<array<string, bool|int|string>>
     */
    public function selectMany(array|string $fields = []): array
    {
        $this->action = self::ACTION_SELECT;
        $this->fields = (array) $fields;

        $statement = $this->pdo->prepare($this->toSql());
        $statement->execute(array_merge(
            $this->joinBindings,
            $this->whereBindings,
            $this->havingBindings
        ));

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($result === false) {
            $strFields = "'" . implode("', '", (array) $fields) . "'";
            throw new SmolFrameworkException("Select request for fields $strFields could not be performed.");
        }

        return $result;
    }

    /**
     * @param array<string>|string $fields
     */
    public function selectSingle(array|string $fields = []): ?array
    {
        $this->limit(1);

        $row = $this->selectMany($fields);

        if ($row === []) {
            return null;
        }

        return $row[0];
    }

    private function buildSelectQueryString(): string
    {
        $fields = '*';

        if ($this->fields !== []) {
            $fields = '';
            foreach ($this->fields as $field) {
                $fields .= $this->quote($field) . ', ';
            }
            $fields = substr($fields, 0, -2);
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

        return $query;
    }

    public function count(): int
    {
        // calling selectMany() here since selectSingle() add a LIMIT clause
        return $this->selectMany('COUNT(*) as _count')[0]['_count'] ?? 0;
    }

    // --------------------------------------------------
    // insert

    /**
     * @var array<string> Names of the fields to insert, extracted from the first value
     */
    private array $fields = [];
    private int $insertedRowCount = 0;

    /**
     * @param array<mixed> $rows
     */
    public function insertMany(array $rows, string $actionType = self::ACTION_INSERT): bool
    {
        $this->action = $actionType;

        $this->fields = array_keys((array) $rows[0]);
        $this->insertedRowCount = count($rows);

        // flatten all rows values, hopefully they are in the same order
        $rowValues = [];
        foreach ($rows as $row) {
            $rowValues[] = array_values((array) $row);  // this suppose here that all rows have the same key in the same order
        }

        return $this->pdo
            ->prepare($this->toSql())
            ->execute(array_merge(...$rowValues));
    }

    public function insertSingle(mixed $row): bool
    {
        return $this->insertMany([$row]);
    }

    /**
     * @param array<mixed> $rows
     */
    public function insertOrReplaceMany(array $rows): bool
    {
        return $this->insertMany($rows, self::ACTION_INSERT_REPLACE);
    }

    public function insertOrReplaceSingle(mixed $row): bool
    {
        return $this->insertMany([$row], self::ACTION_INSERT_REPLACE);
    }

    private function buildInsertQueryString(): string
    {
        if ($this->fields === []) {
            throw new SmolFrameworkException('No field is set for INSERT action');
        }

        $fields = $this->fields;
        foreach ($fields as $i => $field) {
            $fields[$i] = $this->quote($field);
        }

        $rowPlaceholders = str_repeat('?, ', count($fields));
        $row = '(' . substr($rowPlaceholders, 0, -2) . '), ';
        $rows = str_repeat($row, $this->insertedRowCount);

        return "$this->action INTO $this->table (" . implode(', ', $fields) . ')' .
            ' VALUES ' . substr($rows, 0, -2);
    }

    // --------------------------------------------------
    // update

    /**
     * @param array<string, bool|int|string> $newData
     */
    public function update(array $newData): bool
    {
        $this->action = self::ACTION_UPDATE;

        $this->fields = array_keys($newData);

        return $this->pdo
            ->prepare($this->toSql())
            ->execute(array_merge(array_values($newData), $this->whereBindings));
    }

    private function buildUpdateQueryString(): string
    {
        if ($this->fields === []) {
            throw new SmolFrameworkException('No field is set for UPDATE action');
        }

        $sql = "UPDATE $this->table SET ";

        foreach ($this->fields as $field) {
            $sql .= $this->quote($field) . ' = ?, ';
        }

        $sql = substr($sql, 0, -2) . ' ' . $this->buildWhereQueryString();

        return rtrim($sql);
    }

    // --------------------------------------------------
    // delete

    public function delete(): bool
    {
        $this->action = self::ACTION_DELETE;

        return $this->pdo
            ->prepare($this->toSql())
            ->execute($this->whereBindings);
    }

    // --------------------------------------------------
    // table

    private string $table = '';

    public function fromTable(string $tableName): self
    {
        $this->table = $this->quote($tableName);

        return $this;
    }

    public function inTable(string $tableName): self
    {
        $this->table = $this->quote($tableName);

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

    /** @var array<bool|int|string> All the values of the or clauses */
    private array $joinBindings = [];

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

    /** @var array<ConditionalClause|ConditionalGroup> */
    private array $whereClauses = [];

    /** @var array<bool|int|string> All the values of the where clauses that match the placeholders */
    private array $whereBindings = [];

    private function buildWhereQueryString(): string
    {
        $where = $this->buildConditionalQueryString($this->whereClauses);
        if ($where !== '') {
            $where = "WHERE $where ";
        }

        return $where;
    }

    public function whereRaw(string $raw, bool $or = false): self
    {
        $clause = new ConditionalClause();
        $clause->condition = $or ? 'OR' : 'AND';
        $clause->expression = $raw;

        $this->whereClauses[] = $clause;

        return $this;
    }

    public function orWhereRaw(string $raw): self
    {
        return $this->whereRaw($raw, true);
    }

    /** @var array<string> */
    private array $comparisonOperators = [
        '=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE',
    ];

    /**
     * @throws \FlorentPoujol\SmolFramework\SmolFrameworkException
     *
     * @return void|never-return
     */
    private function sanitizeComparisonOperator(string $operator): void
    {
        if (! in_array($operator, $this->comparisonOperators, true)) {
            throw new SmolFrameworkException("Comparison operator '$operator' is not allowed.");
        }
    }

    public function where(string $field, string $operator, bool|int|string $value, bool $or = false): self
    {
        $this->whereBindings[] = $value;

        $operator = strtoupper($operator);
        $this->sanitizeComparisonOperator($operator);

        // $sql = $this->quote($field) . " $operator ?"; // SQLite doesn't like when the field in the where clause are quoted
        $sql = "$field $operator ?";

        $this->whereRaw($sql, $or);

        return $this;
    }

    public function orWhere(string $field, string $sign, bool|int|string $value): self
    {
        return $this->where($field, $sign, $value, true);
    }

    public function whereNull(string $field): self
    {
        return $this->whereRaw($this->quote($field) . ' IS NULL');
    }

    public function orWhereNull(string $field): self
    {
        return $this->whereRaw($this->quote($field) . ' IS NULL', true);
    }

    public function whereNotNull(string $field): self
    {
        return $this->whereRaw($this->quote($field) . ' IS NOT NULL');
    }

    public function orWhereNotNull(string $field): self
    {
        return $this->whereRaw($this->quote($field) . ' IS NOT NULL', true);
    }

    public function whereBetween(string $field, int|string $min, int|string $max, bool $or = false, bool $not = false): self
    {
        $this->whereBindings[] = $min;
        $this->whereBindings[] = $max;

        // "'field' BETWEEN ? AND ?"
        $sql = $this->quote($field) . ($not ? ' NOT' : '') . ' BETWEEN ? AND ?';

        return $this->whereRaw($sql, $or);
    }

    public function orWhereBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->whereBetween($field, $min, $max, true);
    }

    public function whereNotBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->whereBetween($field, $min, $max, false, true);
    }

    public function orWhereNotBetween(string $field, int|string $min, int|string $max): self
    {
        return $this->whereBetween($field, $min, $max, true, true);
    }

    /**
     * @param array<mixed> $values
     */
    public function whereIn(string $field, array $values, bool $or = false, bool $not = false): self
    {
        $this->bindings = array_merge($this->bindings, $values);

        $placeholders = substr(str_repeat('?, ', count($values)), 0, -2);

        // "'field' IN (?,?)"
        $sql = $this->quote($field) . ($not ? ' NOT' : '') . " IN ($placeholders)";

        return $this->whereRaw($sql, $or);
    }

    /**
     * @param array<mixed> $values
     */
    public function orWhereIn(string $field, array $values): self
    {
        return $this->whereIn($field, $values, true);
    }

    /**
     * @param array<mixed> $values
     */
    public function whereNotIn(string $field, array $values): self
    {
        return $this->whereIn($field, $values, false, true);
    }

    /**
     * @param array<mixed> $values
     */
    public function orWhereNotIn(string $field, array $values): self
    {
        return $this->whereIn($field, $values, true, true);
    }

    // --------------------------------------------------
    // order by

    /** @var array<string> */
    private array $orderBy = [];

    private function buildOrderByQueryString(): string
    {
        if ($this->orderBy === []) {
            return '';
        }

        return 'ORDER BY ' . implode(', ', $this->orderBy) . ' ';
    }

    public function orderBy(string $field, bool $ascending = true): self
    {
        $this->orderBy[] = $this->quote($field) . ($ascending ? ' ASC' : ' DESC');

        return $this;
    }

    public function smallestFirst(string $field): self
    {
        return $this->orderBy($field);
    }

    public function oldestFirst(string $field): self
    {
        return $this->orderBy($field);
    }

    public function biggestFirst(string $field): self
    {
        return $this->orderBy($field, false);
    }

    public function mostRecentFirst(string $field): self
    {
        return $this->orderBy($field, false);
    }

    // --------------------------------------------------
    // group by

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
        foreach ((array) $fields as $field) {
            $this->groupBy[] = $this->quote($field);
        }

        return $this;
    }

    // --------------------------------------------------
    // having

    /** @var array<string> */
    private array $havingClauses = [];

    /** @var array<bool|int|string> All the values of the having clauses that match the placeholders */
    private array $havingBindings = [];

    private function buildHavingQueryString(): string
    {
        $having = $this->buildConditionalQueryString($this->havingClauses);
        if ($having === '') {
            return '';
        }

        return "HAVING $having ";
    }

    public function having(string $field, string $sign = null, mixed $value = null, string $cond = 'AND'): self
    {
        return $this->addConditionalClause($this->havingClauses, $field, $sign, $value, $cond);
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

    public function toSql(): string
    {
        if ($this->action === self::ACTION_SELECT) {
            return $this->buildSelectQueryString();
        }

        if ($this->action === self::ACTION_INSERT || $this->action === self::ACTION_INSERT_REPLACE) {
            return $this->buildInsertQueryString();
        }

        if ($this->action === self::ACTION_UPDATE) {
            return $this->buildUpdateQueryString();
        }

        if ($this->action === self::ACTION_DELETE) {
            return "DELETE FROM $this->table " .
                $this->buildWhereQueryString() .
                $this->buildOrderByQueryString() .
                $this->limit;
        }

        throw new SmolFrameworkException('QueryBuilder::toString() error: no action has been set');
    }

    /**
     * @param array<ConditionalClause|ConditionalGroup> $clauses
     */
    private function buildConditionalQueryString(array $clauses): string
    {
        if ($clauses === []) {
            return '';
        }

        $str = '';
        foreach ($clauses as $id => $clause) {
            if ($id > 0) {
                $str .= $clause->condition . ' ';
            }

            if ($clause instanceof ConditionalClause) {
                $str .= $clause->expression . ' ';

                continue;
            }

            $str .= '(' . $this->buildConditionalQueryString($clause->clauses) . ') ';
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
            $clause->expression = "$field = " . $this->quote($sign);
        } elseif ($sign !== null && $value !== null) {
            $clause->expression = "$field $sign " . $this->quote($value);
        }

        // $clauses[] = $clause;

        return $clause;
    }

    public function quote(bool|int|string $value, int $type = PDO::PARAM_STR): string
    {
        if (is_bool($value)) {
            $type = PDO::PARAM_BOOL;
        } elseif (is_int($value)) {
            $type = PDO::PARAM_INT;
        }

        $quoted = $this->pdo->quote((string) $value, $type);
        if ($quoted === false) { // @phpstan-ignore-line (PHPStan thinks $quoted can't be null)
            throw new Exception("PDO can't quote value '$value' for current DB driver");
        }

        return $quoted;
    }
}

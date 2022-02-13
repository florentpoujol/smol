<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Repositories;

use FlorentPoujol\Smol\Components\Database\QueryBuilder;
use PDO;

abstract class PdoRepository
{
    protected string $table;
    protected string $entityFqcn;
    protected string $primaryKey = 'id';

    public function __construct(
        private PDO $pdo
    ) {
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->table($this->table)
            ->hydrate($this->entityFqcn);
    }

    public function find(mixed $value, string $key = null): ?object
    {
        return $this->getQueryBuilder()->where($key ?? $this->primaryKey, '=', $value)->selectSingle();
    }

    public function insert(object $entity): bool
    {
        $qb = $this->getQueryBuilder();
        $success = $qb->insertSingle($entity);

        if ($success) {
            $entity->{$this->primaryKey} = $qb->lastInsertedId(); // TODO: support non-incrementing PK
        }

        return $success;
    }

    public function update(object $entity, array $keys = []): bool
    {
        $array = (array) $entity;

        if ($keys !== []) {
            $array = array_intersect_key($array, array_fill_keys($keys, null));
        }

        return $this->getQueryBuilder()
            ->where($this->primaryKey, '=', $entity->{$this->primaryKey})
            ->update($array);
    }

    public function whereKey(mixed $value): QueryBuilder
    {
        return $this->getQueryBuilder()->where($this->primaryKey, '=', (string) $value);
    }
}

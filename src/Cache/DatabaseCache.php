<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Cache;

use FlorentPoujol\SmolFramework\Database\QueryBuilder;

final class DatabaseCache implements CacheInterface
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private string $prefix = '',
        private string $tableName = 'smol_cache',
    ) {
        $this->queryBuilder->fromTable($this->tableName);
    }

    public function set(string $key, mixed $value, int $ttlInSeconds = null): void
    {
        $expirationTimestamp = $ttlInSeconds === null ? 2145913200 : time() + $ttlInSeconds; // 2145913200 = "2038-01-01 00:00:00"

        $this->queryBuilder->reset()
            ->where('key', '=', $this->prefix . $key)
            ->upsertSingle([
                'key' => $this->prefix . $key,
                'value' => serialize($value),
                'expire_at' => date('Y-m-d H:i:s', $expirationTimestamp),
            ], ['key']);
    }

    public function has(string $key): bool
    {
        return $this->queryBuilder->reset()
            ->where('key', '=', $this->prefix . $key)
            ->exists();
    }

    public function keys(string $prefix = ''): array
    {
        return array_column(
            $this->queryBuilder->reset()
                ->where('key', 'LIKE', $this->prefix . $prefix . '%')
                ->where('expire_at', '>=', date('Y-m-d H:i:s'))
                ->selectMany(['key']),
            'key'
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->prefix . $key;

        /** @var null|array $item */
        $item = $this->queryBuilder->reset()
            ->where('key', '=', $key)
            ->selectSingle();

        if ($item === null) {
            return $default;
        }

        if (strtotime($item['expire_at']) <= time()) {
            return unserialize($item['value']);
        }

        $this->queryBuilder->reset()
            ->where('key', '=', $key)
            ->delete();

        return $default;
    }

    public function delete(string $key): void
    {
        $this->queryBuilder->reset()
            ->where('key', '=', $this->prefix . $key)
            ->delete();
    }

    public function flush(string $prefix = ''): int
    {
        if ($prefix === '') {
            $count = $this->queryBuilder->reset()->count();
            $this->queryBuilder->delete();

            return $count;
        }

        $prefix = $this->prefix . $prefix;

        $count = $this->queryBuilder->reset()
            ->where('key', 'LIKE', "$prefix%")
            ->count();

        $this->queryBuilder->delete();

        return $count;
    }

    public function flushExpiredValues(): int
    {
        $count = $this->queryBuilder->reset()
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->count();

        $this->queryBuilder->delete();

        return $count;
    }
}

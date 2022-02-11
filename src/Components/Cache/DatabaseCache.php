<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Cache;

use FlorentPoujol\Smol\Components\Database\QueryBuilder;

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
                'expire_at' => $expirationTimestamp,
            ], ['key']);
    }

    public function increment(string $key, int $initialValue = 0, int $ttlInSeconds = null): int
    {
        return $this->offsetInteger($key, 1, $initialValue, $ttlInSeconds);
    }

    public function decrement(string $key, int $initialValue = 0, int $ttlInSeconds = null): int
    {
        return $this->offsetInteger($key, -1, $initialValue, $ttlInSeconds);
    }

    public function offsetInteger(string $key, int $offset, int $initialValue = 0, ?int $ttlInSeconds = null): int
    {
        $existingValue = $this->get($key);

        if ($existingValue === null) {
            $initialValue += $offset;
            $expirationTimestamp = $ttlInSeconds === null ? 2145913200 : time() + $ttlInSeconds;

            $this->queryBuilder->reset()
                ->where('key', '=', $this->prefix . $key)
                ->upsertSingle([
                    'key' => $this->prefix . $key,
                    'value' => serialize($initialValue),
                    'expire_at' => $expirationTimestamp,
                ], ['key']);

            return $initialValue;
        }

        $existingValue += $offset;
        $this->queryBuilder->reset()
            ->where('key', '=', $this->prefix . $key)
            ->update([
                'value' => serialize($existingValue),
            ]);

        return $existingValue;
    }

    public function has(string $key): bool
    {
        return $this->queryBuilder->reset()
            ->where('key', '=', $this->prefix . $key)
            ->where('expire_at', '>', time())
            ->exists();
    }

    public function keys(string $prefix = ''): array
    {
        return array_column(
            $this->queryBuilder->reset()
                ->where('key', 'LIKE', $this->prefix . $prefix . '%')
                ->where('expire_at', '>', time())
                ->selectMany(['key']),
            'key'
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->prefix . $key;

        /** @var null|array<string, string> $item */
        $item = $this->queryBuilder->reset()
            ->where('key', '=', $key)
            ->selectSingle();

        if ($item === null) {
            return $default;
        }

        if ((int) $item['expire_at'] > time()) {
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
            ->where('expire_at', '<', time())
            ->count();

        $this->queryBuilder->delete();

        return $count;
    }
}

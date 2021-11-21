<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Cache;

use Memcached;

final class MemcachedCache implements CacheInterface
{
    public function __construct(
        private Memcached $memcached,
        private string $prefix = ''
    ) {
    }

    public function set(string $key, mixed $value, int $ttlInSeconds = null): void
    {
        if ($ttlInSeconds !== null && $ttlInSeconds > 0) {
            $this->memcached->set($this->prefix . $key, serialize($value), time() + $ttlInSeconds);
        } else {
            $this->memcached->set($this->prefix . $key, serialize($value));
        }
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
        if ($offset > 0) {
            return (int) $this->memcached->increment($this->prefix . $key, $offset, $initialValue, $ttlInSeconds ?? 0);
        }

        return (int) $this->memcached->decrement($this->prefix . $key, $offset, $initialValue, $ttlInSeconds ?? 0);
    }

    public function has(string $key): bool
    {
        return $this->memcached->get($this->prefix . $key) !== false;
    }

    public function keys(string $prefix = ''): array
    {
        $keys = $this->memcached->getAllKeys();
        if ($keys === false) {
            return [];
        }

        $prefix = $this->prefix . $prefix;

        return array_values(array_filter(
            $keys,
            fn (string $key): bool => str_starts_with($key, $prefix)
        ));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->memcached->get($this->prefix . $key, $default);
        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function delete(string $key): void
    {
        $this->memcached->delete($this->prefix . $key);
    }

    public function flush(string $prefix = ''): int
    {
        return count(array_filter($this->memcached->deleteMulti($this->keys($prefix))));
    }
}

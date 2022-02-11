<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Cache;

use Redis;

final class RedisCache implements CacheInterface
{
    public function __construct(
        private Redis $redis,
        private string $prefix = ''
    ) {
    }

    public function set(string $key, mixed $value, int $ttlInSeconds = null): void
    {
        if ($ttlInSeconds !== null) {
            $this->redis->setex($this->prefix . $key, $ttlInSeconds, serialize($value));
        } else {
            $this->redis->set($this->prefix . $key, serialize($value));
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
        if ($ttlInSeconds !== null && ! $this->has($key)) {
            $initialValue += $offset;
            $this->redis->setex($this->prefix . $key, $ttlInSeconds, (string) $initialValue);

            return $initialValue;
        }

        if ($offset > 0) {
            return $this->redis->incrBy($this->prefix . $key, $offset);
        }

        return $this->redis->decrBy($this->prefix . $key, $offset);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function keys(string $prefix = ''): array
    {
        return $this->redis->keys($this->prefix . $prefix . '*');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        if ($value === false) {
            return $default;
        }

        try {
            return unserialize($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->prefix . $key);
    }

    public function flush(string $prefix = ''): int
    {
        $keys = $this->redis->keys($this->prefix . $prefix . '*');

        return $this->redis->del($keys);
    }
}

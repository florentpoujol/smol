<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Cache;

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
            $this->redis->setEx($this->prefix . $key, $ttlInSeconds, serialize($value));
        } else {
            $this->redis->set($this->prefix . $key, serialize($value));
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function flushValues(string $prefix = ''): int
    {
        $keys = $this->redis->keys($this->prefix . $prefix . '*');

        return $this->redis->del($keys);
    }
}

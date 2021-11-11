<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Cache;

interface CacheInterface
{
    public function set(string $key, mixed $value, int $ttlInSeconds = null): void;

    public function get(string $key, mixed $default = null): mixed;

    /**
     * @return int The number of deleted entries
     */
    public function flushValues(string $prefix = ''): int;
}

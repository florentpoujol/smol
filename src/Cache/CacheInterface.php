<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Cache;

interface CacheInterface
{
    public function set(string $key, mixed $value, int $ttlInSeconds = null): void;

    public function has(string $key): bool;

    /**
     * @return array<string>
     */
    public function keys(string $prefix = ''): array;

    public function get(string $key, mixed $default = null): mixed;

    public function delete(string $key): void;

    /**
     * @return int The number of deleted entries
     */
    public function flushValues(string $prefix = ''): int;
}

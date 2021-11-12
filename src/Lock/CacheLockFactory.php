<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Lock;

use FlorentPoujol\SmolFramework\Cache\CacheInterface;

final class CacheLockFactory
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function make(string $name, int $ttlInSeconds): CacheLock
    {
        return new CacheLock(
            $name,
            $ttlInSeconds,
            $this->cache
        );
    }
}

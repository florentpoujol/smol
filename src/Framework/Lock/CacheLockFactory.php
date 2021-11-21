<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\Lock;

use FlorentPoujol\SmolFramework\Components\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Components\Lock\CacheLock;

final class CacheLockFactory
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function make(string $name, int $ttlInSeconds): CacheLock
    {
        return new CacheLock(
            $this->cache,
            $name,
            $ttlInSeconds,
        );
    }
}

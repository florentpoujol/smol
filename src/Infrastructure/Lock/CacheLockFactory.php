<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Infrastructure\Lock;

use FlorentPoujol\Smol\Components\Cache\CacheInterface;
use FlorentPoujol\Smol\Components\Cache\CacheLock;

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

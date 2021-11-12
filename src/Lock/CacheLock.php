<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Lock;

use FlorentPoujol\SmolFramework\Cache\CacheInterface;

final class CacheLock
{
    public function __construct(
        private string $name,
        private int $ttlInSeconds,
        private CacheInterface $cache,
    ) {
    }

    public function acquire(): bool
    {
        $key = "locks:$this->name";
        if ($this->cache->has($key)) {
            return false;
        }

        $this->cache->set($key, 1, $this->ttlInSeconds);

        return true;
    }

    public function release(): void
    {
        $this->cache->delete("locks:$this->name");
    }

    public function wait(int $maxWaitTimeInSeconds, callable $callback, int $loopWaitTimeInMilliseconds = 100): mixed
    {
        $maxTimestamp = time() + $maxWaitTimeInSeconds;

        do {
            if ($this->acquire()) {
                try {
                    return $callback();
                } finally {
                    $this->release();
                }
            }

            usleep($loopWaitTimeInMilliseconds * 1000);
        } while ($maxTimestamp > time());

        return null;
    }
}

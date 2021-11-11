<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\RateLimiter;

use FlorentPoujol\SmolFramework\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Framework;

final class CacheLock
{
    public static function make(string $name, int $timeoutInSeconds): self
    {
        return new self(
            $name,
            $timeoutInSeconds,
            Framework::getInstance()->getContainer()->get(CacheInterface::class)
        );
    }

    public function __construct(
        private string $name,
        private int $timeoutInSeconds,
        private CacheInterface $cache,
    ) {
    }

    public function acquire(): bool
    {
        $key = "locks:$this->name";
        if ($this->cache->has($key)) {
            return false;
        }

        $this->cache->set($key, time(), $this->timeoutInSeconds);

        return true;
    }

    public function release(): void
    {
        $this->cache->delete("locks:$this->name");
    }

    public function waitFor(int $maxWaitTimeInSeconds, callable $callback, int $waitTimeInMilliseconds = 200): bool
    {
        $maxTimestamp = time() + $maxWaitTimeInSeconds;
        $acquired = false;

        do {
            if ($this->acquire()) {
                $acquired = true;

                try {
                    $callback();
                } finally {
                    $this->release();
                }

                break;
            }

            usleep($waitTimeInMilliseconds * 1000);
        } while ($maxTimestamp > time());

        return $acquired;
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\RateLimiter;

use FlorentPoujol\SmolFramework\Components\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Components\Lock\CacheLock;

final class CacheRateLimiter
{
    private string $name;
    private string $cacheKey;
    private int $maxHits;
    private int $windowSizeInSeconds;
    private bool $windowIsSliding;
    private CacheLock $fixedWindowLock;

    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function setup(string $name, int $maxHits, int $windowSizeInSeconds, bool $windowIsSliding = false): void
    {
        $this->name = $name;

        $this->maxHits = $maxHits;
        $this->windowSizeInSeconds = $windowSizeInSeconds;

        $this->windowIsSliding = $windowIsSliding;
        $this->cacheKey = "rate_limiters:$name:hit:";

        if (! $this->windowIsSliding) {
            $this->cacheKey = "rate_limiters:$name";
            $this->fixedWindowLock = new CacheLock("rate_limiter:$this->name", 1, $this->cache);
        }
    }

    public function hitIsAllowed(): bool
    {
        if ($this->windowIsSliding) {
            return $this->hitSlidingWindow();
        }

        return $this->hitFixedWindow();
    }

    private function hitFixedWindow(): bool
    {
        // for the fixed window, we create one cache entry the first time the limiter is hit, for the duration of the window

        return (bool) $this->fixedWindowLock->wait(2, function (): bool {
            if (! $this->cache->has($this->cacheKey)) {
                $data = ['remaining_hits' => $this->maxHits - 1];

                if (! $this->windowIsSliding) {
                    $data['max_timestamp'] = time() + $this->windowSizeInSeconds;
                }

                $this->cache->set($this->cacheKey, $data);

                return true;
            }

            $data = $this->cache->get($this->cacheKey);

            if ($data['remaining_hits'] <= 0) {
                return false;
            }

            ++$data['remaining_hits'];

            $this->cache->set($this->cacheKey, $data, $this->windowSizeInSeconds);

            return true;
        });
    }

    private function hitSlidingWindow(): bool
    {
        // for the sliding window, we create one cache entry **per hit**, for the duration of the window,
        // so that we just have to count the number of non-expired hits

        $uniqid = uniqid('', true);
        $this->cache->set($this->cacheKey . $uniqid, time(), $this->windowSizeInSeconds);

        $hitCount = count($this->cache->keys($this->cacheKey));

        return $hitCount >= $this->maxHits;
    }

    public function remainingTimeInSeconds(): int
    {
        if ($this->windowIsSliding) {
            $keys = $this->cache->keys($this->cacheKey);
            if (count($keys) < $this->maxHits) {
                return 0;
            }

            // The number of keys (of hits) can be more than the maxHits, but all keys here are inside the window.
            // So, get the maxHits'th key, which is the key that needs to expire to be able to hit the limiter again (if there is no more hits in between).
            $key = array_slice($keys, $this->maxHits + 1, 1)[0];
            $timestamp = $this->cache->get($key);

            return $timestamp + 1 - (time() - $this->windowSizeInSeconds); // time - windowSizeInSeconds is the timestamp of the start of the window, that is before the key's timestamp
        }

        $maxTimestamp = $this->cache->get($this->cacheKey)['max_timestamp'] ?? null;

        return $maxTimestamp === null
            ? $this->windowSizeInSeconds
            : $maxTimestamp - time();
    }

    public function remainingHitsInWindow(): int
    {
        if ($this->windowIsSliding) {
            return max($this->maxHits - count($this->cache->keys($this->cacheKey)), 0);
        }

        return $this->cache->get($this->cacheKey)['remaining_hits'] ?? $this->maxHits;
    }

    public function clear(): void
    {
        if ($this->windowIsSliding) {
            $this->cache->flush($this->cacheKey);
        }

        $this->cache->delete($this->cacheKey);
    }
}

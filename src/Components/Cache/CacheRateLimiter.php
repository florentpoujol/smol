<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Cache;

use Exception;

final class CacheRateLimiter
{
    private string $cacheKey;
    private CacheLock $fixedWindowLock;

    public function __construct(
        private CacheInterface $cache,
        private string $name,
        private int $maxHits,
        private int $windowSizeInSeconds,
        private bool $windowIsSliding,
    ) {
        $this->cacheKey = "rate_limiters:$name:hit:";

        if (! $this->windowIsSliding) {
            $this->cacheKey = "rate_limiters:$name";
            $this->fixedWindowLock = new CacheLock($this->cache, "rate_limiter:$this->name", 1);
        }
    }

    public function hitIsAllowed(): bool
    {
        if ($this->windowIsSliding) {
            return $this->hitSlidingWindow();
        }

        // using a closure here instead of [$this, 'hitFixedWindow'] because the method is private
        return (bool) $this->fixedWindowLock->wait(2, fn () => $this->hitFixedWindow());
    }

    public function hitAndTrow(): void
    {
        if (! $this->hitIsAllowed()) {
            throw new Exception();
        }
    }

    private function hitFixedWindow(): bool
    {
        // for the fixed window, we create one cache entry the first time the limiter is hit, for the duration of the window
        if (! $this->cache->has($this->cacheKey)) {
            $this->cache->set($this->cacheKey, [
                'remaining_hits' => $this->maxHits - 1,
                'max_timestamp' => time() + $this->windowSizeInSeconds,
            ]);

            return true;
        }

        $data = $this->cache->get($this->cacheKey);
        if ($data['remaining_hits'] <= 0) {
            return false;
        }

        --$data['remaining_hits'];
        $this->cache->set($this->cacheKey, $data, $data['max_timestamp'] - time());

        return true;
    }

    private function hitSlidingWindow(): bool
    {
        // for the sliding window, we create one cache entry **per hit**, for the duration of the window,
        // so that we just have to count the number of non-expired hits
        $this->cache->set(uniqid($this->cacheKey, true), time(), $this->windowSizeInSeconds);

        $hitCount = count($this->cache->keys($this->cacheKey));

        return $hitCount <= $this->maxHits;
    }

    public function remainingTimeInSeconds(): int
    {
        if (! $this->windowIsSliding) {
            $maxTimestamp = $this->cache->get($this->cacheKey)['max_timestamp'] ?? null;

            return $maxTimestamp === null
                ? $this->windowSizeInSeconds
                : $maxTimestamp - time();
        }

        $keys = $this->cache->keys($this->cacheKey);
        if (count($keys) < $this->maxHits) {
            return 0;
        }

        // The number of keys (of hits) can be more than the maxHits, but all keys here are inside the window.
        // So, get the maxHits'th key -from the end of the list of keys that are in order of creation-
        // which is the key that needs to expire to be able to hit the limiter again (if there is no more hits in between).
        $key = array_slice(array_reverse($keys), $this->maxHits - 1, 1)[0];
        $timestamp = $this->cache->get($key);

        return $timestamp - (time() - $this->windowSizeInSeconds); // time - windowSizeInSeconds is the timestamp of the start of the window, that is before the key's timestamp
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

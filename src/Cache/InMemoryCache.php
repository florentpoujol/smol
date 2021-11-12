<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Cache;

final class InMemoryCache implements CacheInterface
{
    /** @var array<string, \FlorentPoujol\SmolFramework\Cache\CacheItem> Each cache item is the expiration timestamp and the value */
    private array $items = [];

    public function __construct(
        private string $baseAppPath,
        private string $prefix = '',
    ) {
    }

    public function set(string $key, mixed $value, int $ttlInSeconds = null): void
    {
        $expirationTimestamp = $ttlInSeconds === null ? PHP_INT_MAX : time() + $ttlInSeconds;

        $this->items[$this->prefix . $key] = new CacheItem($value, $expirationTimestamp);
    }

    public function has(string $key): bool
    {
        return $this->get($key) === null;
    }

    public function keys(string $prefix = ''): array
    {
        if ($prefix === '') {
            return array_keys($this->items);
        }

        $keys = [];
        $prefix = $this->prefix . $prefix;
        $currentTimestamp = time();

        foreach ($this->items as $key => $item) {
            if ($item->expirationTimestamp >= $currentTimestamp && str_starts_with($key, $prefix)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->prefix . $key;
        $item = $this->items[$key] ?? null;

        if ($item === null) {
            return $default;
        }

        if ($item->expirationTimestamp <= time()) {
            return $item->value;
        }

        unset($this->items[$key]);

        return $default;
    }

    public function delete(string $key): void
    {
        unset($this->items[$this->prefix . $key]);
    }

    public function flush(string $prefix = ''): int
    {
        if ($prefix === '') {
            $count = count($this->items);
            $this->items = [];

            return $count;
        }

        $count = 0;
        $prefix = $this->prefix . $prefix;

        foreach ($this->items as $key => $item) {
            if (str_starts_with($key, $prefix)) {
                unset($this->items[$key]);
                ++$count;
            }
        }

        return $count;
    }

    public function flushExpiredValues(): int
    {
        $time = time();
        $count = 0;

        foreach ($this->items as $key => $item) {
            if ($item->expirationTimestamp < $time) { // this assumes we go through all the items in less than 1 second
                unset($this->items[$key]);
                ++$count;
            }
        }

        return $count;
    }
}

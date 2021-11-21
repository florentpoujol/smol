<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Cache;

final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{0: int, 1: mixed}> Each cache item is the expiration timestamp and value */
    private array $items = [];

    public function __construct(
        private string $prefix = '',
    ) {
    }

    public function set(string $key, mixed $value, int $ttlInSeconds = null): void
    {
        $expirationTimestamp = $ttlInSeconds === null ? PHP_INT_MAX : time() + $ttlInSeconds;

        $this->items[$this->prefix . $key] = [$expirationTimestamp, $value];
    }

    public function increment(string $key, int $initialValue = 0, int $ttlInSeconds = null): int
    {
        return $this->offsetInteger($key, 1, $initialValue, $ttlInSeconds);
    }

    public function decrement(string $key, int $initialValue = 0, int $ttlInSeconds = null): int
    {
        return $this->offsetInteger($key, -1, $initialValue, $ttlInSeconds);
    }

    public function offsetInteger(string $key, int $offset, int $initialValue = 0, ?int $ttlInSeconds = null): int
    {
        if (! $this->has($key)) {
            $initialValue += $offset;

            if ($ttlInSeconds === null) {
                $this->set($key, $initialValue);
            } else {
                $this->set($key, $initialValue, $ttlInSeconds);
            }

            return $initialValue;
        }

        $this->items[$this->prefix . $key][1] += $offset;

        return $this->items[$this->prefix . $key][1];
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
            if ($item[0] >= $currentTimestamp && str_starts_with($key, $prefix)) {
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

        if ($item[0] <= time()) {
            return $item[1];
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
            if ($item[0] < $time) { // this assumes we go through all the items in less than 1 second
                unset($this->items[$key]);
                ++$count;
            }
        }

        return $count;
    }
}

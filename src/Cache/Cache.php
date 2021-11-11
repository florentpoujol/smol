<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Cache;

final class Cache implements CacheInterface
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

    public function flushValues(string $prefix = ''): int
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

    // --------------------------------------------------
    // files stuff

    private function getFilePath(): string
    {
        $prefixHash = $this->prefix !== '' ? md5($this->prefix) : 'cache';

        return $this->baseAppPath . "/storage/git-ignored/cache/$prefixHash.txt";
    }

    /**
     * @param null|array<string> $allowedClasses When null, all classes will be allowed to be deserialized. When an array, only the specified classes, plus the built-in CacheItem classe will be allowed to be deserialized.
     */
    public function loadFromFile(?array $allowedClasses = []): void
    {
        $filePath = $this->getFilePath();
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return;
        }

        $options = [];
        if (is_array($allowedClasses)) {
            $options = ['allowed_classes' => array_merge([CacheItem::class], $allowedClasses)];
        }

        $this->items = unserialize(file_get_contents($filePath), $options); // @phpstan-ignore-line

        $this->flushExpiredValues();
    }

    public function writeInFile(): void
    {
        $this->flushExpiredValues();

        file_put_contents($this->getFilePath(), serialize($this->items));
    }
}

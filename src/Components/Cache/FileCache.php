<?php

/* @noinspection UnserializeExploitsInspection */
/* @noinspection MkdirRaceConditionInspection */

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Cache;

use Exception;

final class FileCache implements CacheInterface
{
    public function __construct(
        private string $absoluteCacheFolder,
        private string $prefix = ''
    ) {
        $this->absoluteCacheFolder = '/' . trim($absoluteCacheFolder, '/') . '/';
        if ($prefix !== '') {
            $this->absoluteCacheFolder .= "$prefix/";
        }

        if (
            ! is_dir($this->absoluteCacheFolder)
            && ! mkdir($this->absoluteCacheFolder, 0775, true)
        ) {
            throw new Exception("Can't create folder at path '$this->absoluteCacheFolder'.");
        }
    }

    public function set(string $key, mixed $value, int $ttlInSeconds = null): void
    {
        $expirationTimestamp = $ttlInSeconds === null ? PHP_INT_MAX : time() + $ttlInSeconds;

        file_put_contents(
            $this->absoluteCacheFolder . $key,
            serialize([$expirationTimestamp, serialize($value)]),
            LOCK_EX
        );
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
            $expirationTimestamp = $ttlInSeconds === null ? PHP_INT_MAX : time() + $ttlInSeconds;
            $initialValue += $offset;

            file_put_contents(
                $this->absoluteCacheFolder . $key,
                serialize([$expirationTimestamp, $initialValue]),
                LOCK_EX
            );

            return $initialValue;
        }

        $item = [0, 0];
        $strContent = file_get_contents($this->absoluteCacheFolder . $key);
        if (is_string($strContent)) {
            $item = unserialize($strContent);
            $item[1] += $offset;
        }

        file_put_contents($this->absoluteCacheFolder . $key, serialize($item), LOCK_EX);

        return $item[1];
    }

    public function has(string $key): bool
    {
        return $this->get($key) === null;
    }

    public function keys(string $prefix = ''): array
    {
        $files = scandir($this->absoluteCacheFolder);
        if (! is_array($files)) {
            return [];
        }

        $keys = [];
        $prefix = $this->absoluteCacheFolder . $prefix;

        foreach ($files as $path) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $strContent = file_get_contents($path);
            if (is_string($strContent)) {
                $item = unserialize($strContent);
                if ($item[0] >= time()) {
                    $keys[] = str_replace($this->absoluteCacheFolder, '', $path);
                }
            }
        }

        return $keys;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $filePath = $this->absoluteCacheFolder . $this->prefix . $key;
        $strContent = file_get_contents($filePath);
        if ($strContent === false) {
            return $default;
        }

        $item = unserialize($strContent);
        if ($item[0] <= time()) {
            return $item[1];
        }

        unlink($filePath);

        return $default;
    }

    public function delete(string $key): void
    {
        unlink($this->absoluteCacheFolder . $this->prefix . $key);
    }

    public function flush(string $prefix = ''): int
    {
        $files = scandir($this->absoluteCacheFolder);
        if (! is_array($files)) {
            return 0;
        }

        if ($prefix === '') {
            rmdir($this->absoluteCacheFolder);
            mkdir($this->absoluteCacheFolder);

            return count($files);
        }

        $count = 0;
        $prefix = $this->absoluteCacheFolder . $prefix;

        foreach ($files as $path) {
            if (str_starts_with($path, $prefix)) {
                unlink($path);
                ++$count;
            }
        }

        return $count;
    }

    public function flushExpiredValues(): int
    {
        $files = scandir($this->absoluteCacheFolder);
        if (! is_array($files)) {
            return 0;
        }

        $count = 0;
        foreach ($files as $path) {
            $strContent = file_get_contents($path);
            if (is_string($strContent)) {
                $item = unserialize($strContent);
                if ($item[0] < time()) {
                    unlink($path);
                    ++$count;
                }
            }
        }

        return $count;
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ConfigRepository
{
    private static bool $envFileRead = false;

    /** @var array<string, mixed> */
    private static array $config = [];

    public function __construct(
        private string $baseAppPath
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadConfig();

        if (! str_contains($key, '.')) {
            return self::$config[$key] ?? $default;
        }

        if (array_key_exists($key, self::$config['cache_map'])) {
            return self::$config['cache_map'][$key];
        }

        $keys = explode('.', $key);
        $value = self::$config;

        $valueFound = true;
        foreach ($keys as $_key) {
            $value = $value[$_key] ?? null;
            if (! is_array($value)) {
                if (! isset($value[$_key])) {
                    $valueFound = false;
                }

                break;
            }
        }

        if ($valueFound) {
            self::$config['cache_map'][$key] = $value; // may be null, that's ok
        }

        return $value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->loadConfig();

        self::$config['cache_map'][$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return self::$config;
    }

    // --------------------------------------------------

    private function loadConfig(): void
    {
        if (count(self::$config) > 0) {
            return;
        }

        $cacheFilePath = $this->baseAppPath . '/storage/frameworkCache/config.cache.php';
        if (file_exists($cacheFilePath)) {
            self::$config = require $cacheFilePath;

            return;
        }

        $this->readEnvFile();

        /** @var RecursiveDirectoryIterator|RecursiveIteratorIterator $iterator */
        $iterator = new RecursiveIteratorIterator( // @phpstan-ignore-line (the RecursiveDirectoryIterator PHPDoc is for proper highlight of the
            new RecursiveDirectoryIterator($this->baseAppPath . '/config')
        );

        while ($iterator->valid()) {
            /** @var \SplFileInfo $file */
            $file = $iterator->current();
            if ($file->isFile() && $file->isReadable()) {
                $key = str_replace('.php', '', $file->getBasename());
                self::$config[$key] = require $file->getPathname();
            }

            $iterator->next();
        }
    }

    private function readEnvFile(): void
    {
        if (self::$envFileRead) {
            return;
        }

        $envFilePath = $this->baseAppPath . '/.env';
        $envFileResource = fopen($envFilePath, 'r');
        if ($envFileResource === false) {
            return;
        }

        $envVarPattern = '/^\s*(?<key>[A-Z0-9_-]+)\s*=\s*("|\')?(?<value>.+)("|\')?\s*$/i'; // eg: SOME_ENV = "a value"
        while (is_string($line = fgets($envFileResource))) {
            $matches = [];
            $line = trim($line);
            if (
                $line === '' || $line[0] === '#'
                || preg_match($envVarPattern, $line, $matches) !== 1
            ) {
                continue;
            }

            putenv($matches['key'] . '=' . $matches['value']);
        }

        fclose($envFileResource);

        self::$envFileRead = true;
    }
}

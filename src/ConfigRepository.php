<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

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

        self::$config = ['cache_map' => []];

        $files = scandir($this->baseAppPath . '/config');
        assert(is_array($files));

        foreach ($files as $path) {
            if (str_ends_with($path, '.')) {
                continue;
            }

            $filename = str_replace('.php', '', $path);

            self::$config[$filename] = require $this->baseAppPath . '/config/' . $path;
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

        $envVarPattern = '/^\s*(?<key>[A-Z0-9_-]+)\s*=(?:\s*)(?<value>.+)(?:\s*)$/iU'; // eg: SOME_ENV = "a value"
        while (is_string($line = fgets($envFileResource))) {
            $matches = [];
            $line = trim($line);
            if (
                $line === '' || $line[0] === '#'
                || preg_match($envVarPattern, $line, $matches) !== 1
            ) {
                continue;
            }

            $value = trim($matches['value']);
            if ($value[0] === '"' || $value[0] === "'") {
                // if the value is surrounded by a quotation mark, remove it, but only that one
                // so that a value like ""test"" become "test"
                $value = substr($value, 1, -1);
            }

            putenv($matches['key'] . '=' . $value);
        }

        fclose($envFileResource);

        self::$envFileRead = true;
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

final class ConfigRepository
{
    private static bool $envFileRead = false;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct(
        private string $baseAppPath
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadConfig();

        if (! str_contains($key, '.')) {
            return $this->config[$key] ?? $default;
        }

        if (array_key_exists($key, $this->config['cache_map'])) {
            return $this->config['cache_map'][$key];
        }

        $keys = explode('.', $key);
        $value = $this->config;

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
            $this->config['cache_map'][$key] = $value; // may be null, that's ok
        }

        return $value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->loadConfig();

        $this->config['cache_map'][$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->config;
    }

    // --------------------------------------------------

    private function loadConfig(): void
    {
        if (count($this->config) > 0) {
            return;
        }

        $cacheFilePath = $this->baseAppPath . '/storage/frameworkCache/config.cache.php';
        if (file_exists($cacheFilePath)) {
            $this->config = require $cacheFilePath;

            return;
        }

        $this->readEnvFile();

        $this->config = ['cache_map' => []];

        $files = scandir($this->baseAppPath . '/config');
        assert(is_array($files));

        foreach ($files as $path) {
            if (str_ends_with($path, '.')) {
                continue;
            }

            $filename = str_replace('.php', '', $path);

            $this->config[$filename] = require $this->baseAppPath . '/config/' . $path;
        }
    }

    private function readEnvFile(): void
    {
        if (self::$envFileRead) {
            return;
        }

        if (! file_exists($this->baseAppPath . '/.env')) {
            self::$envFileRead = true;

            return;
        }

        $envVarPattern = '/\s*(?<key>[A-Z0-9_-]+)\s*=(?:\s*)(?<value>.+)(?:\s*)\n/iU'; // eg: SOME_ENV = "a value"
        $fileContent = file_get_contents($this->baseAppPath . '/.env');
        $matches = [];

        preg_match_all($envVarPattern, $fileContent, $matches);

        foreach ($matches['key'] as $i => $key) {
            $value = trim($matches['value'][$i]);
            if (
                   ($value[0] === '"' && $value[-1] === '"')
                || ($value[0] === "'" && $value[-1] === "'")
            ) {
                // if the value is surrounded by a quotation mark, remove it, but only that one
                // so that a value like ""test"" become "test"
                $value = substr($value, 1, -1);
            }

            putenv("$key=$value");
        }

        self::$envFileRead = true;
    }
}

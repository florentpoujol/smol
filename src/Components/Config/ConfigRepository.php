<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Config;

final class ConfigRepository
{
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct(
        private string $configDirPath
    ) {
        $this->loadConfig();
    }

    public function get(string $key, mixed $default = null): mixed
    {
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
        $this->config['cache_map'][$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->config;
    }

    private function loadConfig(): void
    {
        $this->config = ['cache_map' => []];

        $files = scandir($this->configDirPath);
        assert(is_array($files));

        foreach ($files as $path) {
            if (str_ends_with($path, '.')) {
                continue;
            }

            $filename = str_replace('.php', '', $path);

            $this->config[$filename] = require $this->configDirPath . '/' . $path;
        }

        $this->configLoaded = true;
    }
}

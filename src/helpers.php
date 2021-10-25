<?php

declare(strict_types=1);

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        // getenv() always return a string, or false if env var doesn't exists
        $value = getenv($key);

        return match ($value) {
            false => $default,
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

if (! function_exists('dd')) {
    /**
     * @return never-return
     */
    function dd(mixed ...$values): void
    {
        if (function_exists('xdebug_var_dump')) {
            xdebug_var_dump(...$values);

            exit(0);
        }

        var_dump(...$values);

        exit(0);
    }
}

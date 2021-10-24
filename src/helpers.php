<?php

declare(strict_types=1);

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        // getenv() always return a string, or false if env var doesn't exists
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        if ($value === 'true' || $value === 'false') {
            return (bool) $value;
        }
        if ($value === 'null') {
            return null;
        }

        return $value;
    }
}

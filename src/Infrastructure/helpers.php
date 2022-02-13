<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Infrastructure;

use FlorentPoujol\Smol\Infrastructure\Translations\TranslationsRepository;
use RuntimeException;
use Throwable;

if (! function_exists('\FlorentPoujol\Smol\env')) {
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

if (! function_exists('\FlorentPoujol\Smol\__')) {
    /**
     * @param array<string, string> $templateReplacements Keys are the semi-colon prefixed templates found in the translation string, values are their replacement string
     */
    function __(string $key, array $templateReplacements = []): string
    {
        /** @var TranslationsRepository $translationRepository */
        $translationRepository = Project::getInstance()
            ->getContainer()
            ->get(TranslationsRepository::class);

        return $translationRepository->get($key, $templateReplacements);
    }
}

if (! function_exists('\FlorentPoujol\Smol\dump')) { // when it exists it is typically provided by the Symfony var_dumper component
    function dump(mixed ...$values): void
    {
        if (function_exists('xdebug_var_dump')) {
            xdebug_var_dump(...$values);
        } else {
            var_dump(...$values);
        }
    }
}

if (! function_exists('\FlorentPoujol\Smol\dd')) {
    /**
     * @return never-return
     */
    function dd(mixed ...$values): void
    {
        if (function_exists('dump')) {
            dump(...$values); // typically, provided by the Symfony VarDumper component
        } elseif (function_exists('xdebug_var_dump')) {
            xdebug_var_dump(...$values);
        } else {
            var_dump(...$values);
        }

        exit(0);
    }
}

if (! function_exists('\FlorentPoujol\Smol\read_environment_file')) {
    function read_environment_file(
        string $filePath,
        string $envVarPattern = '/\s*(?<key>[A-Z0-9_-]+)\s*=(?:\s*)(?<value>.+)(?:\s*)\n/iU' // eg: SOME_ENV = "a value"
    ): void {
        $fileContent = file_get_contents($filePath);
        assert(is_string($fileContent));
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
    }
}

/**
 * @param callable|class-string<Throwable>|Throwable $exception
 *
 * @return void|never-return
 */
function throwIf(bool $condition, callable|string|Throwable $exception): void
{
    if ($condition) {
        return;
    }

    if (is_callable($exception)) {
        $exception();

        return;
    }

    if ($exception instanceof Throwable) {
        throw $exception;
    }

    if (class_exists($exception)) {
        throw new $exception();
    }

    throw new RuntimeException($exception);
}

function getRandomString(int $length = 10, string $prefix = ''): string
{
    if (function_exists('sodium_bin2base64')) {
        $string = sodium_bin2base64(random_bytes($length * 2), SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);

        return substr($prefix . str_replace(['/', '+', '='], '',$string),0, $length);
    }

    return substr($prefix . str_replace(['/', '+', '='], '', base64_encode(random_bytes($length * 2))), 0, $length);
}

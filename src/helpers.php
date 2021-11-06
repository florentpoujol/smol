<?php

declare(strict_types=1);

use FlorentPoujol\SmolFramework\Framework;
use FlorentPoujol\SmolFramework\Translations\TranslationsRepository;

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

if (! function_exists('__')) {
    /**
     * @param array<string, string> $templateReplacements Keys are the semi-colon prefixed templates found in the translation string, values are their replacement string
     */
    function __(string $key, array $templateReplacements = []): string
    {
        /** @var TranslationsRepository $translationRepository */
        $translationRepository = Framework::getInstance()
            ->getContainer()
            ->get(TranslationsRepository::class);

        return $translationRepository->get($key, $templateReplacements);
    }
}

if (! function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        if (function_exists('xdebug_var_dump')) {
            xdebug_var_dump(...$values);
        } else {
            var_dump(...$values);
        }
    }
}

if (! function_exists('dd')) {
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

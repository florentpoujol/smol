<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework\Translations;

use FlorentPoujol\SimplePhpFramework\ConfigRepository;

final class TranslationsRepository
{
    /** @var array<string, array<string, string|array>> Translations per language, per file name */
    private array $translations = [];

    public function __construct(
        private string $baseAppPath,
        private ConfigRepository $configRepository,
    ) {
    }

    /**
     * @param array<string, string> $templateReplacements Keys are the semi-colon prefixed templates found in the translation string, values are their replacement string
     */
    public function get(string $key, array $templateReplacements = [], string $overrideLanguage = null): string
    {
        $language = $overrideLanguage ?? $this->configRepository->get('app.current_language', 'en');
        $fallbackLanguage = $this->configRepository->get('app.fallback_language');
        if ($language === $fallbackLanguage) {
            $fallbackLanguage = null;
        }

        $keys = explode('.', $key);

        // read language file not done already
        if (! isset($this->translations[$language][$keys[0]])) {
            $this->translations[$language][$keys[0]] =
                require "$this->baseAppPath/translations/$language/$keys[0].php";
        }

        // get the value that match the key
        $value = $this->translations[$language];
        foreach ($keys as $_key) {
            $value = $value[$_key] ?? null;
            if (! is_array($value)) {
                break;
            }
        }

        // if the value isn't found in the current language, but a fallback language is defined
        // then search the value in that language
        if ($value === null && $fallbackLanguage !== null) {
            $value = $this->get($key, [], $fallbackLanguage); // no need to replace templates here
        }

        // if the value is null and if no fallback language is defined or the value is null even in the fallback language
        // then defaults the value to the key
        $value ??= $key;

        // and finally do the replacements, so on the value, the fallback value, or the key
        if (count($templateReplacements) > 0) {
            $search = array_map(fn (string $value) => ':' . $value, array_keys($templateReplacements));
            $replace = array_values($templateReplacements);

            $value = str_replace($search, $replace, $value);
        }

        return $value;
    }
}

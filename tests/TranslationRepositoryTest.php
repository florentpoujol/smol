<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\ConfigRepository;
use FlorentPoujol\SmolFramework\Translations\TranslationsRepository;
use PHPUnit\Framework\TestCase;

final class TranslationRepositoryTest extends TestCase
{
    private ConfigRepository $configRepo;
    private TranslationsRepository $transRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configRepo = new ConfigRepository(__DIR__ . '/Fixtures/Translations');
        $this->transRepo = new TranslationsRepository(__DIR__ . '/Fixtures/Translations', $this->configRepo);

        // current language is FR, fallback is EN
    }

    public function test_translation_without_fallback_or_templates(): void
    {
        $this->configRepo->set('app.fallback_language', null);

        self::assertSame('la valeur :template', $this->transRepo->get('tests.key'));
        self::assertSame('la valeur nestée :template', $this->transRepo->get('tests.nestedKey.key'));

        // assert value default to key
        self::assertSame('tests.non_existent_key', $this->transRepo->get('tests.non_existent_key'));
        self::assertSame('tests.non_existent_key.non_existent_key', $this->transRepo->get('tests.non_existent_key.non_existent_key'));
    }

    public function test_translation_with_fallback_without_templates(): void
    {
        self::assertSame('la valeur :template', $this->transRepo->get('tests.key'));
        self::assertSame('la valeur nestée :template', $this->transRepo->get('tests.nestedKey.key'));

        self::assertSame('value-that-dont-exists-in-fr :template', $this->transRepo->get('tests.key-that-dont-exists-in-fr'));

        self::assertSame('tests.non_existent_key', $this->transRepo->get('tests.non_existent_key'));
        self::assertSame('tests.non_existent_key.non_existent_key', $this->transRepo->get('tests.non_existent_key.non_existent_key'));
    }

    public function test_translation_with_fallback_and_templates(): void
    {
        self::assertSame(
            'la valeur replaced',
            $this->transRepo->get('tests.key', ['template' => 'replaced'])
        );
        self::assertSame(
            'la valeur nestée replaced',
            $this->transRepo->get('tests.nestedKey.key', ['template' => 'replaced'])
        );

        self::assertSame(
            'value-that-dont-exists-in-fr replaced',
            $this->transRepo->get('tests.key-that-dont-exists-in-fr', ['template' => 'replaced'])
        );

        self::assertSame(
            'tests.non_existent_key',
            $this->transRepo->get('tests.non_existent_key', ['template' => 'replaced'])
        );
        self::assertSame(
            'tests.non_existent_key.non_existent_key',
            $this->transRepo->get('tests.non_existent_key.non_existent_key', ['template' => 'replaced'])
        );
    }
}

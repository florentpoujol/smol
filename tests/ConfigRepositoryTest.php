<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Components\Config\ConfigRepository;
use PHPUnit\Framework\TestCase;

final class ConfigRepositoryTest extends TestCase
{
    public function test_that_the_config_file_is_read(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config');

        self::assertSame('file1', $repo->get('file1.key'));
        self::assertSame('file2', $repo->get('file2.key'));
    }

    public function test_that_the_environment_is_read(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config');

        self::assertSame('env var 1 value', $repo->get('file1.from_env'));
        self::assertSame('env var 2 default value', $repo->get('file1.from_env_with_default'));
    }

    public function test_get(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config');

        self::assertIsArray($repo->get('file1'));
        self::assertNotEmpty($repo->get('file1'));

        self::assertSame('file1', $repo->get('file1.key'));
        self::assertSame('file1', $repo->get('file1.key', 'default value'));

        self::assertNull($repo->get('file1.non_existant_key'));
        self::assertSame('default value', $repo->get('file1.non_existant_key', 'default value'));

        self::assertIsArray($repo->get('file1.array_key'));
        self::assertNotEmpty($repo->get('file1.array_key'));
        self::assertTrue($repo->get('file1.array_key.key'));
        self::assertTrue($repo->get('file1.array_key.key', 'default value'));
        self::assertSame('default value', $repo->get('file1.array_key.non_existant_key', 'default value'));
    }

    public function test_set(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config');

        self::assertSame('file1', $repo->get('file1.key'));
        $repo->set('file1.key', 'the new value');
        self::assertSame('the new value', $repo->get('file1.key'));

        self::assertNull($repo->get('file1.non_existant_key'));
        $repo->set('file1.non_existant_key', 'the new value');
        self::assertSame('the new value', $repo->get('file1.key'));

        self::assertTrue($repo->get('file1.array_key.key'));
        $repo->set('file1.key', 'the new value');
        self::assertSame('the new value', $repo->get('file1.key'));

        self::assertNull($repo->get('file1.key1.key.key'));
        $repo->set('file1.key1.key.key', 'the new value');
        self::assertSame('the new value', $repo->get('file1.key1.key.key'));
    }

    public function test_env_function_returns_proper_types(): void
    {
        self::assertNull(env('non_existent_key'));
        self::assertSame(1, env('non_existent_key', 1));
        self::assertFalse(env('non_existent_key', false));
        self::assertSame(1.1, env('non_existent_key', 1.1));
        self::assertSame('1', env('non_existent_key', '1'));

        putenv('ENV_VAR_FALSE=false');
        self::assertFalse(env('ENV_VAR_FALSE'));

        putenv('ENV_VAR_TRUE=true');
        self::assertTrue(env('ENV_VAR_TRUE'));

        putenv('ENV_VAR_NULL=null');
        self::assertNull(env('ENV_VAR_NULL'));
    }

    public function test_env_reading_handles_spaces_correctly(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config');
        self::assertNotNull($repo->get('file1.from_env'));

        self::assertSame('nospace', env('NO_SPACE'));
        self::assertSame('lots of space', env('LOTS_OF_SPACES'));
        self::assertSame(' lots of space', env('LOTS_OF_SPACES_WITH_MARKS')); // one leading slash
    }
}

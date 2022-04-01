<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\Config\Config;
use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use FlorentPoujol\Smol\Components\Config\Env;
use FlorentPoujol\Smol\Components\Validation\Validates;
use FlorentPoujol\Smol\Components\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use function FlorentPoujol\Smol\Infrastructure\env;
use function FlorentPoujol\Smol\Infrastructure\read_environment_file;

final class ConfigRepositoryTest extends TestCase
{
    public function test_that_the_config_file_is_read(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config/config');

        self::assertSame('file1', $repo->get('file1.key'));
        self::assertSame('file2', $repo->get('file2.key'));
    }

    public function test_that_the_environment_is_read(): void
    {
        read_environment_file(__DIR__ . '/Fixtures/Config/.env');

        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config/config');

        self::assertSame('env var 1 value', $repo->get('file1.from_env'));
        self::assertSame('env var 2 default value', $repo->get('file1.from_env_with_default'));
    }

    public function test_get(): void
    {
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config/config');

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
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config/config');

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
        $repo = new ConfigRepository(__DIR__ . '/Fixtures/Config/config');
        self::assertNotNull($repo->get('file1.from_env'));

        self::assertSame('nospace', env('NO_SPACE'));
        self::assertSame('lots of space', env('LOTS_OF_SPACES'));
        self::assertSame(' lots of space', env('LOTS_OF_SPACES_WITH_MARKS')); // one leading slash
    }

    public function test_object_based_config(): void
    {
        $instance = MyConfig::make();
        self::assertInstanceOf(MyConfig::class, $instance);

        self::assertSame('default value', $instance->otherKey);

        self::assertSame($instance, MyConfig::make());
    }

    public function test_object_based_config_from_env(): void
    {
        putenv('TEST_KEY=test');
        putenv('TEST_OTHER_KEY=testtest');
        MyConfig::clearInstances();

        $instance = MyConfig::make();

        self::assertSame('test', $instance->key);
        self::assertSame('testtest', $instance->otherKey);
    }

    public function test_object_based_config_from_env_validation_fails(): void
    {
        putenv('TEST_OTHER_KEY=test');
        MyConfig::clearInstances();

        $this->expectException(ValidationException::class); // because the value of the 'otherKey' property is too short

        $instance = MyConfig::make();
    }
}

final class MyConfig extends Config
{
    #[Env('TEST_KEY')]
    public string $key;

    #[Env('TEST_OTHER_KEY')]
    #[Validates(['minLength:5'])]
    public string $otherKey = 'default value';
}

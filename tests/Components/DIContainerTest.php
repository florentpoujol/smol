<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\Container\Container;
use FlorentPoujol\Smol\Components\Log\ResourceLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class DIContainerTest extends TestCase
{
    public function test_autowire_argument_from_parameters(): void
    {
        // in this test, we are testing that the 'baseAppPath' argument of the DailyFileLogger constructor is indeed provided the correct value
        $container = new Container();
        $resourcePath = __DIR__ . '/Fixtures/Logs/storage/git-ignored/logs/container-parameter-test.log';
        $container->setParameter('resourcePath', $resourcePath);

        $container->bind(LoggerInterface::class, ResourceLogger::class);
        /** @var ResourceLogger $logger */
        $logger = $container->get(LoggerInterface::class);

        $reflClass = new ReflectionClass($logger);
        $reflProperty = $reflClass->getProperty('resourcePath');
        $reflProperty->setAccessible(true);

        self::assertSame($resourcePath, $reflProperty->getValue($logger));

        @unlink($resourcePath);

        self::assertFileDoesNotExist($resourcePath);

        $logger->info('test_autowire_argument_from_parameters');

        self::assertFileExists($resourcePath);
    }
}

<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DIContainerTest extends TestCase
{
    public function test_autowire_argument_from_parameters(): void
    {
        // in this test, we are testing that the 'baseAppPath' argument of the DailyFileLogger constructor is indeed provided the correct value
        $container = new Container();
        $container->setParameter('baseAppPath', __DIR__ . '/Fixtures/Container');

        $logger = $container->get(LoggerInterface::class);

        $date = date('Y-m-d');
        $expectedFilePath = __DIR__ . "/Fixtures/Container/storage/git-ignored/logs/log-$date.log";
        @unlink($expectedFilePath);

        self::assertFileDoesNotExist($expectedFilePath);

        $logger->info('test_autowire_argument_from_parameters');

        self::assertFileExists($expectedFilePath);
    }
}

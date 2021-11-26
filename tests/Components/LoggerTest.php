<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Components\Log\DailyFileLogger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    public function test_basic_view_without_variables(): void
    {
        $logger = new DailyFileLogger(__DIR__ . '/Fixtures/Logs');

        $filePath = __DIR__ . '/Fixtures/Logs/storage/git-ignored/logs/log-' . date('Y-m-d') . '.log';
        @unlink($filePath);

        $logger->info('the_message', ['the' => 'context']);

        self::assertFileExists($filePath);

        $date = date('Y-m-d H:i:s');
        $expectedContent = "[$date] info: the_message {\"the\":\"context\"}" . PHP_EOL;
        self::assertSame($expectedContent, file_get_contents($filePath));
    }
}

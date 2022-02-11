<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Log;

use Psr\Log\AbstractLogger;
use Stringable;

final class DailyFileLogger extends AbstractLogger
{
    public function __construct(
        private string $baseAppPath
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $context
     */
    // @phpstan-ignore-next-line (param $context without type specified, but if we specify one, it complains it is not covariant
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $date = date('Y-m-d');
        $filePath = "$this->baseAppPath/storage/git-ignored/logs/log-$date.log";
        if (! file_exists($filePath)) {
            touch($filePath);
        }

        $date = date('Y-m-d H:i:s');
        $line = "[$date] $level: $message";
        if ($context !== []) {
            $line .= ' ' . json_encode($context);
        }

        file_put_contents($filePath, $line . PHP_EOL, FILE_APPEND);
    }
}

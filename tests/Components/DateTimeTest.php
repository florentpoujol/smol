<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    public function test_validate_array(): void
    {
        $max = 500_000;

        $dt = new DateTime();
        $other = new DateTime();
        $time = microtime(true);
        for ($i = 0; $i < $max; ++$i) {
            $dt->isSameMinute($other);
        }
        $time2 = microtime(true);
        echo $time2 - $time . PHP_EOL;

        $dt = new DateTime();
        $other = new DateTime();
        $time = microtime(true);
        for ($i = 0; $i < $max; ++$i) {
            $dt->isSameMinute2(41);
        }
        $time2 = microtime(true);
        echo $time2 - $time . PHP_EOL;
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\Misc\HashId;
use PHPUnit\Framework\TestCase;

final class HashIdTest extends TestCase
{
    public function test_main(): void
    {
        foreach (range(1, 9999) as $i) {
            // $service = new HashId(random_int(999, 2147483647), random_int(999, 2147483647), random_int(999, 2147483647));
            $service = new HashId(random_int(1, PHP_INT_MAX));

            $expectedNumber = random_int(999, 999_999);
            $expectedHashId = $service->encode($expectedNumber);
            echo $expectedHashId . PHP_EOL;
            $actualNumber = $service->decode($expectedHashId);

            self::assertSame($expectedNumber, $actualNumber);
        }
    }
}

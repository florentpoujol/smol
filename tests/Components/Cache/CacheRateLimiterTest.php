<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Tests\Components\Cache;

use FlorentPoujol\SmolFramework\Components\Cache\ArrayCache;
use FlorentPoujol\SmolFramework\Components\Cache\CacheRateLimiter;
use PHPUnit\Framework\TestCase;

final class CacheRateLimiterTest extends TestCase
{
    public function test_fixed_window(): void
    {
        // arrange
        $rl = new CacheRateLimiter(new ArrayCache(), 'test_rate_limiter', 2, 2, false);

        self::assertSame(2, $rl->remainingHitsInWindow());
        self::assertSame(2, $rl->remainingTimeInSeconds());

        // act and assert

        self::assertTrue($rl->hitIsAllowed());
        self::assertSame(1, $rl->remainingHitsInWindow());
        self::assertSame(2, $rl->remainingTimeInSeconds());

        self::assertTrue($rl->hitIsAllowed());
        self::assertSame(0, $rl->remainingHitsInWindow());
        self::assertSame(2, $rl->remainingTimeInSeconds());

        self::assertFalse($rl->hitIsAllowed());
        self::assertSame(0, $rl->remainingHitsInWindow());
        self::assertSame(2, $rl->remainingTimeInSeconds());

        sleep(1);
        self::assertFalse($rl->hitIsAllowed());
        self::assertSame(0, $rl->remainingHitsInWindow());
        self::assertSame(1, $rl->remainingTimeInSeconds());
    }

    public function test_sliding_window(): void
    {
        // arrange
        $rl = new CacheRateLimiter(new ArrayCache(), 'test_rate_limiter', 2, 3, true);

        self::assertSame(2, $rl->remainingHitsInWindow());
        self::assertSame(0, $rl->remainingTimeInSeconds());

        // act and assert

        self::assertTrue($rl->hitIsAllowed());
        self::assertSame(1, $rl->remainingHitsInWindow());
        self::assertSame(0, $rl->remainingTimeInSeconds());

        self::assertTrue($rl->hitIsAllowed());
        self::assertSame(0, $rl->remainingHitsInWindow());
        self::assertSame(1, $rl->remainingTimeInSeconds());

        self::assertFalse($rl->hitIsAllowed());
        self::assertSame(0, $rl->remainingHitsInWindow());
        self::assertSame(1, $rl->remainingTimeInSeconds());

        sleep(1);
        self::assertSame(2, $rl->remainingHitsInWindow());
        self::assertSame(0, $rl->remainingTimeInSeconds());
    }
}

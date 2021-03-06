<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components\Cache;

use FlorentPoujol\Smol\Components\Cache\ArrayCache;
use FlorentPoujol\Smol\Components\Cache\CacheLock;
use PHPUnit\Framework\TestCase;

final class CacheLockTest extends TestCase
{
    public function test(): void
    {
        // arrange
        $cache = new ArrayCache();
        $lock1 = new CacheLock($cache, 'test_lock', 1);
        $lock2 = new CacheLock($cache, 'test_lock', 1);

        self::assertTrue($lock1->acquire());
        self::assertFalse($lock2->acquire());

        $lock2->release();
        self::assertTrue($lock2->acquire());
        self::assertFalse($lock1->acquire());

        self::assertTrue($lock1->wait(2, fn () => true));
    }
}

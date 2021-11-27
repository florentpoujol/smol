<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Tests\Components\Cache;

use FlorentPoujol\SmolFramework\Components\Cache\ArrayCache;
use FlorentPoujol\SmolFramework\Components\Cache\CacheLock;
use PHPUnit\Framework\TestCase;

final class CacheLockTest extends TestCase
{
    public function test_main(): void
    {
        // arrange
        $cache = new ArrayCache();
        $lock1 = new CacheLock($cache, 'test_lock', 1);
        $lock2 = new CacheLock($cache, 'test_lock', 1);

        self::assertTrue($lock1->acquire());
        self::assertFalse($lock2->acquire());

        $lock2->release();
        self::assertFalse($lock1->acquire());
        self::assertTrue($lock2->acquire());

    }
}

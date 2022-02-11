<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components\Cache;

use FlorentPoujol\Smol\Components\Cache\DatabaseCache;
use FlorentPoujol\Smol\Components\Database\QueryBuilder;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseCacheTest extends TestCase
{
    private DatabaseCache $cache;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $createTable = <<<'SQL'
        CREATE TABLE IF NOT EXISTS `smol_cache` (
          `key` VARCHAR(50) NOT NULL,
          `value` VARCHAR(1000) NOT NULL,
          `expire_at` INT UNSIGNED NOT NULL
        );
        CREATE UNIQUE INDEX test_key_uindex ON smol_cache (`key`);
        SQL;

        $pdo->exec($createTable);

        $this->queryBuilder = new QueryBuilder($pdo);
        $this->cache = new DatabaseCache($this->queryBuilder);
    }

    public function test_main(): void
    {
        // arrange
        $cache = $this->cache;

        self::assertNull($cache->get('bool'));
        self::assertNull($cache->get('string'));
        self::assertNull($cache->get('int'));
        self::assertNull($cache->get('array'));
        self::assertNull($cache->get('object'));
        self::assertNull($cache->get('whatever'));

        self::assertEmpty($cache->keys());

        // act
        $cache->set('bool', true);
        $cache->set('string', 'string');
        $cache->set('int', 1);

        $cache->set('array', ['some' => 'array']);
        $object = new \stdClass();
        $cache->set('object', $object);
        $object->the_property = true;

        // assert

        // test has
        self::assertTrue($cache->has('bool'));
        self::assertTrue($cache->has('string'));
        self::assertTrue($cache->has('int'));
        self::assertTrue($cache->has('array'));
        self::assertTrue($cache->has('object'));
        self::assertFalse($cache->has('whatever'));

        // test get
        self::assertTrue($cache->get('bool'));
        self::assertSame('string', $cache->get('string'));
        self::assertSame(1, $cache->get('int'));
        self::assertSame(['some' => 'array'], $cache->get('array'));
        self::assertNotSame($object, $cache->get('object'));
        // self::assertSame($object->the_property, $cache->get('object')->the_property);
        self::assertNull($cache->get('whatever'));

        // test keys
        $allKeys = ['bool', 'string', 'int', 'array', 'object'];
        self::assertSame($allKeys, $cache->keys());
        self::assertSame(['bool'], $cache->keys('bo'));

        // test delete
        $cache->delete('nothing');
        $cache->delete('string');
        self::assertNull($cache->get('string'));
        self::assertFalse($cache->has('string'));
        self::assertEmpty($cache->keys('stri'));

        $cache->delete('array');
        $allKeys = ['bool', 'int', 'object'];
        self::assertSame($allKeys, $cache->keys());

        // test increment/decrement
        self::assertSame(1, $cache->get('int'));

        self::assertSame(3, $cache->offsetInteger('int', 2));
        self::assertSame(3, $cache->get('int'));

        self::assertSame(4, $cache->increment('int'));
        self::assertSame(4, $cache->get('int'));

        self::assertSame(2, $cache->offsetInteger('int', -2));
        self::assertSame(2, $cache->get('int'));

        self::assertSame(1, $cache->decrement('int'));
        self::assertSame(0, $cache->decrement('int'));
        self::assertSame(0, $cache->get('int'));

        // test flush
        self::assertSame(0, $cache->flushExpiredValues());

        self::assertTrue($cache->get('bool'));
        self::assertNull($cache->get('string'));
        self::assertSame(0, $cache->get('int'));
        self::assertNull($cache->get('array'));
        self::assertNotSame($object, $cache->get('object'));
        self::assertNull($cache->get('whatever'));

        self::assertSame(1, $cache->flush('obj'));

        self::assertTrue($cache->get('bool'));
        self::assertNull($cache->get('string'));
        self::assertSame(0, $cache->get('int'));
        self::assertNull($cache->get('array'));
        self::assertNull($cache->get('object'));
        self::assertNull($cache->get('whatever'));

        self::assertSame(2, $cache->flush());

        self::assertNull($cache->get('bool'));
        self::assertNull($cache->get('string'));
        self::assertNull($cache->get('int'));
        self::assertNull($cache->get('array'));
        self::assertNull($cache->get('object'));
        self::assertNull($cache->get('whatever'));
    }

    public function test_ttl_expiration(): void
    {
        // arrange
        $cache = $this->cache;

        self::assertFalse($cache->has('exp'));
        self::assertFalse($cache->has('no_exp'));

        // act
        $cache->set('exp', 'exp', 1);
        $cache->set('no_exp', 'no_exp');

        // assert

        self::assertTrue($cache->has('exp'));
        self::assertTrue($cache->has('no_exp'));
        self::assertSame(['exp', 'no_exp'], $cache->keys());

        sleep(1);

        self::assertFalse($cache->has('exp'));
        self::assertTrue($cache->has('no_exp'));
        self::assertSame(['no_exp'], $cache->keys());

        self::assertSame(0, $cache->flushExpiredValues()); // 0 because has() calls get() which unset the expired value
    }
}

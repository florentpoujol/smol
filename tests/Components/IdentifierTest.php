<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\Identifier\TimeBased16;
use FlorentPoujol\Smol\Components\Identifier\TimeBased8;
use FlorentPoujol\Smol\Components\Identifier\UUIDv4;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class IdentifierTest extends TestCase
{
    public function test_uuid_v4(): void
    {
        $uuid = UUIDv4::make();

        self::assertSame(32, strlen($uuid->getHex()));
        self::assertSame(36, strlen($uuid->getUuid()));

        $uuidRegex = '/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/';
        self::assertTrue(preg_match($uuidRegex, $uuid->getUuid()) === 1);

        $uuid2 = UUIDv4::fromString($uuid->getHex());
        self::assertSame($uuid->getHex(), $uuid2->getHex());
        self::assertSame($uuid->getUuid(), $uuid2->getUuid());

        $uuid3 = UUIDv4::fromString($uuid->getUuid());
        self::assertSame($uuid->getHex(), $uuid3->getHex());
        self::assertSame($uuid->getUuid(), $uuid3->getUuid());
    }

    public function test_basic_time_based_8(): void
    {
        $id = TimeBased8::make();

        self::assertSame(16, strlen($id->getHex()));
        self::assertTrue($id->getInteger() > microtime(true) * 100_000_000);

        $id2 = TimeBased8::fromString($id->getHex());
        self::assertSame($id->getHex(), $id2->getHex());
        self::assertSame($id->getInteger(), $id2->getInteger());

        $id3 = TimeBased8::fromInteger($id->getInteger());
        self::assertSame($id->getHex(), $id3->getHex());
        self::assertSame($id->getInteger(), $id3->getInteger());

        $this->expectException(UnexpectedValueException::class);
        $id->getUuid();
    }

    public function test_basic_time_based_16(): void
    {
        $id = TimeBased16::make();

        self::assertSame(32, strlen($id->getHex()));
        self::assertSame(36, strlen($id->getUuid()));

        $id2 = TimeBased16::fromString($id->getHex());
        self::assertSame($id->getHex(), $id2->getHex());
    }
}

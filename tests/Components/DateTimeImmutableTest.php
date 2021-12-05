<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Tests\Components;

use DateInterval;
use FlorentPoujol\SmolFramework\Components\DateTime\DateTime;
use FlorentPoujol\SmolFramework\Components\DateTime\DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DateTimeImmutableTest extends TestCase
{
    public function test_get(): void
    {
        $datetime = new DateTimeImmutable('2021-12-05 19:12:13.123456');

        self::assertSame(123456, $datetime->getMicroSecond());
        self::assertSame(13, $datetime->getSecond());
        self::assertSame(12, $datetime->getMinute());
        self::assertSame(19, $datetime->getHour());
        self::assertSame(5, $datetime->getDay());
        self::assertSame(12, $datetime->getMonth());
        self::assertSame(2021, $datetime->getYear());
    }

    public function test_diff_in(): void
    {
        $datetime = new DateTimeImmutable('2021-12-05 19:12:13.123456');

        // seconds
        self::assertSame(17, $datetime->diffInSeconds(new DateTime('2021-12-05 19:12:30')));
        self::assertSame(17, $datetime->diffInSeconds(new DateTime('2021-12-05 19:12:30'), true));

        self::assertSame(-11, $datetime->diffInSeconds(new DateTime('2021-12-05 19:12:02')));
        self::assertSame(11, $datetime->diffInSeconds(new DateTime('2021-12-05 19:12:02'), true));

        // minutes
        self::assertSame(9, $datetime->diffInMinutes(new DateTime('2021-12-05 19:22:00')));
        self::assertSame(9, $datetime->diffInMinutes(new DateTime('2021-12-05 19:22:00'), true));

        self::assertSame(10, $datetime->diffInMinutes(new DateTime('2021-12-05 19:22:32')));
        self::assertSame(10, $datetime->diffInMinutes(new DateTime('2021-12-05 19:22:32'), true));

        self::assertSame(-10, $datetime->diffInMinutes(new DateTime('2021-12-05 19:02')));
        self::assertSame(10, $datetime->diffInMinutes(new DateTime('2021-12-05 19:02'), true));

        // hours
        self::assertSame(0, $datetime->diffInHours(new DateTime('2021-12-05 20:12:12')));
        self::assertSame(0, $datetime->diffInHours(new DateTime('2021-12-05 20:12:12'), true));

        self::assertSame(1, $datetime->diffInHours(new DateTime('2021-12-05 21:12:12')));
        self::assertSame(1, $datetime->diffInHours(new DateTime('2021-12-05 21:12:12'), true));

        self::assertSame(-2, $datetime->diffInHours(new DateTime('2021-12-05 17:12:02')));
        self::assertSame(2, $datetime->diffInHours(new DateTime('2021-12-05 17:12:02'), true));

        // days
        self::assertSame(3, $datetime->diffInDays(new DateTime('2021-12-08 20:12:12')));
        self::assertSame(3, $datetime->diffInDays(new DateTime('2021-12-08 20:12:12'), true));

        self::assertSame(-4, $datetime->diffInDays(new DateTime('2021-12-01')));
        self::assertSame(4, $datetime->diffInDays(new DateTime('2021-12-01'), true));

        // months
        self::assertSame(2, $datetime->diffInMonths(new DateTime('2022-03-01')));
        self::assertSame(2, $datetime->diffInMonths(new DateTime('2022-03-01'), true));

        self::assertSame(-6, $datetime->diffInMonths(new DateTime('2021-05-08')));
        self::assertSame(6, $datetime->diffInMonths(new DateTime('2021-05-08'), true));

        // years
        self::assertSame(2, $datetime->diffInYears(new DateTime('2024-03-01')));
        self::assertSame(2, $datetime->diffInYears(new DateTime('2024-03-01'), true));

        self::assertSame(-1, $datetime->diffInYears(new DateTime('2020-05-08')));
        self::assertSame(1, $datetime->diffInYears(new DateTime('2020-05-08'), true));
    }

    public function test_is(): void
    {
        $year = (int) date('Y');
        $now = date('Y-m-d H:i:s');

        $year -= 1;
        self::assertTrue((new DateTimeImmutable("$year-12-05 19:12:13.123456"))->isPast());
        $year += 2;
        self::assertFalse((new DateTimeImmutable("$year-12-05 19:12:13.123456"))->isPast());
        self::assertFalse((new DateTimeImmutable($now))->isPast());

        self::assertTrue((new DateTimeImmutable($now))->isNow());
        self::assertTrue((new DateTimeImmutable())->isNow());
        self::assertFalse((new DateTimeImmutable('- 1 second'))->isNow());
        self::assertFalse((new DateTimeImmutable('+ 1 minute'))->isNow());

        self::assertTrue((new DateTimeImmutable("$year-12-05 19:12:13.123456"))->isFuture());
        $year -= 2;
        self::assertFalse((new DateTimeImmutable("$year-12-05 19:12:13.123456"))->isFuture());
        self::assertFalse((new DateTimeImmutable($now))->isFuture());
    }

    public function test_is_current(): void
    {
        $before = new DateTimeImmutable('- 1 year');
        self::assertFalse($before->isCurrentSecond());
        self::assertFalse($before->isCurrentMinute());
        self::assertFalse($before->isCurrentHour());
        self::assertFalse($before->isCurrentDay());
        self::assertFalse($before->isCurrentMonth());
        self::assertFalse($before->isCurrentYear());

        $now = new DateTimeImmutable();
        self::assertTrue($now->isCurrentSecond());
        self::assertTrue($now->isCurrentMinute());
        self::assertTrue($now->isCurrentHour());
        self::assertTrue($now->isCurrentDay());
        self::assertTrue($now->isCurrentMonth());
        self::assertTrue($now->isCurrentYear());

        $after = (new DateTimeImmutable())->add(new DateInterval('P1Y'));
        self::assertFalse($after->isCurrentSecond());
        self::assertFalse($after->isCurrentMinute());
        self::assertFalse($after->isCurrentHour());
        self::assertFalse($after->isCurrentDay());
        self::assertFalse($after->isCurrentMonth());
        self::assertFalse($after->isCurrentYear());
    }
}

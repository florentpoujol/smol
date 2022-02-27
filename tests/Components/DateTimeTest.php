<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    public function test_set(): void
    {
        $dt = new DateTime('2022-02-27 12:25:01.123456');

        self::assertSame(123456, $dt->getMicroSecond());
        self::assertSame(456789, $dt->setMicroSecond(456789)->getMicroSecond());

        self::assertSame(1, $dt->getSecond());
        self::assertSame(23, $dt->setSecond(23)->getSecond());

        self::assertSame(25, $dt->getMinute());
        self::assertSame(23, $dt->setMinute(23)->getMinute());

        self::assertSame(12, $dt->getHour());
        self::assertSame(23, $dt->setHour(23)->getHour());

        self::assertSame(27, $dt->getDay());
        self::assertSame(23, $dt->setDay(23)->getDay());

        self::assertSame(2, $dt->getMonth());
        self::assertSame(3, $dt->setMonth(3)->getMonth());

        self::assertSame(2022, $dt->getYear());
        self::assertSame(2023, $dt->setYear(2023)->getYear());

        self::assertSame('2023-03-23 23:23:23.456789', $dt->format('Y-m-d H:i:s.u'));
    }

    public function test_add(): void
    {
        $dt = new DateTime('2022-02-27 12:25:01.123456');

        self::assertSame(123456, $dt->getMicroSecond());
        self::assertSame(123500, $dt->addMicroSeconds(44)->getMicroSecond());

        self::assertSame(1, $dt->getSecond());
        self::assertSame(24, $dt->addSeconds(23)->getSecond());

        self::assertSame(25, $dt->getMinute());
        self::assertSame(48, $dt->addMinutes(23)->getMinute());

        self::assertSame(12, $dt->getHour());
        self::assertSame(22, $dt->addHours(10)->getHour());

        self::assertSame(27, $dt->getDay());
        self::assertSame(28, $dt->addDays(1)->getDay());

        self::assertSame(2, $dt->getMonth());
        self::assertSame(5, $dt->addMonths(3)->getMonth());

        self::assertSame(2022, $dt->getYear());
        self::assertSame(4045, $dt->addYears(2023)->getYear());

        self::assertSame('4045-05-28 22:48:24.123500', $dt->format('Y-m-d H:i:s.u'));
    }

    public function test_add_without_overflow(): void
    {
        // month
        $actual = (new DateTime('2022-01-31 12:25:01.123456'))->addMonthsWithoutOverflow(1)->toDateTimeString();
        self::assertSame('2022-02-28 12:25:01', $actual);

        $actual = (new DateTime('2020-02-29 12:25:01.123456'))->addMonthsWithoutOverflow(13)->toDateTimeString();
        self::assertSame('2021-03-29 12:25:01', $actual);

        $actual = (new DateTime('2020-11-29 12:25:01.123456'))->addMonthsWithoutOverflow(27)->toDateTimeString();
        self::assertSame('2023-02-28 12:25:01', $actual);

        // year
        $actual = (new DateTime('2022-01-31 12:25:01.123456'))->addYearsWithoutOverflow(1)->toDateTimeString();
        self::assertSame('2023-01-31 12:25:01', $actual);

        $actual = (new DateTime('2020-02-29 12:25:01.123456'))->addYearsWithoutOverflow(2)->toDateTimeString();
        self::assertSame('2022-02-28 12:25:01', $actual);

        $actual = (new DateTime('2020-11-29 12:25:01.123456'))->addYearsWithoutOverflow(3)->toDateTimeString();
        self::assertSame('2023-11-29 12:25:01', $actual);
    }

    public function test_sub(): void
    {
        $dt = new DateTime('2022-02-27 12:25:01.123456');

        self::assertSame(123456, $dt->getMicroSecond());
        self::assertSame(123400, $dt->subMicroSeconds(56)->getMicroSecond());

        self::assertSame(1, $dt->getSecond());
        self::assertSame(51, $dt->subSeconds(10)->getSecond());

        self::assertSame(24, $dt->getMinute());
        self::assertSame(1, $dt->subMinutes(23)->getMinute());

        self::assertSame(12, $dt->getHour());
        self::assertSame(3, $dt->subHours(9)->getHour());

        self::assertSame(27, $dt->getDay());
        self::assertSame(26, $dt->subDays(1)->getDay());

        self::assertSame(2, $dt->getMonth());
        self::assertSame(11, $dt->subMonths(3)->getMonth());

        self::assertSame(2021, $dt->getYear());
        self::assertSame(2019, $dt->subYears(2)->getYear());

        self::assertSame('2019-11-26 03:01:51.123400', $dt->format('Y-m-d H:i:s.u'));
    }

    public function test_sub_without_overflow(): void
    {
        // month
        $actual = (new DateTime('2022-03-31 12:25:01.123456'))->subMonthsWithoutOverflow(1)->toDateTimeString();
        self::assertSame('2022-02-28 12:25:01', $actual);

        $actual = (new DateTime('2020-02-29 12:25:01.123456'))->subMonthsWithoutOverflow(13)->toDateTimeString();
        self::assertSame('2019-01-29 12:25:01', $actual);

        $actual = (new DateTime('2020-02-29 12:25:01.123456'))->subMonthsWithoutOverflow(27)->toDateTimeString();
        self::assertSame('2017-11-29 12:25:01', $actual);

        // year
        $actual = (new DateTime('2022-03-31 12:25:01.123456'))->subYearsWithoutOverflow(1)->toDateTimeString();
        self::assertSame('2021-03-31 12:25:01', $actual);

        $actual = (new DateTime('2020-02-29 12:25:01.123456'))->subYearsWithoutOverflow(3)->toDateTimeString();
        self::assertSame('2017-02-28 12:25:01', $actual);

        $actual = (new DateTime('2020-02-29 12:25:01.123456'))->subYearsWithoutOverflow(4)->toDateTimeString();
        self::assertSame('2016-02-29 12:25:01', $actual);
    }

    public function test_start_of(): void
    {
        $dt = new DateTime('2022-02-27 12:25:01.123456');

        self::assertSame('2022-02-27 12:25:01.000000', $dt->startOfSecond()->format('Y-m-d H:i:s.u'));
        self::assertSame('2022-02-27 12:25:00', $dt->startOfMinute()->toDateTimeString());
        self::assertSame('2022-02-27 12:00:00', $dt->startOfHour()->toDateTimeString());
        self::assertSame('2022-02-27 00:00:00', $dt->startOfDay()->toDateTimeString());
        self::assertSame('2022-02-01 00:00:00', $dt->startOfMonth()->toDateTimeString());
        self::assertSame('2022-01-01 00:00:00', $dt->startOfYear()->toDateTimeString());
    }

    public function test_end_of(): void
    {
        $dt = new DateTime('2022-02-27 12:25:01.123456');

        self::assertSame('2022-02-27 12:25:01.999999', $dt->endOfSecond()->format('Y-m-d H:i:s.u'));
        self::assertSame('2022-02-27 12:25:59', $dt->endOfMinute()->toDateTimeString());
        self::assertSame('2022-02-27 12:59:59', $dt->endOfHour()->toDateTimeString());
        self::assertSame('2022-02-27 23:59:59', $dt->endOfDay()->toDateTimeString());
        self::assertSame('2022-02-28 23:59:59', $dt->endOfMonth()->toDateTimeString());
        self::assertSame('2022-12-31 23:59:59', $dt->endOfYear()->toDateTimeString());
    }
}

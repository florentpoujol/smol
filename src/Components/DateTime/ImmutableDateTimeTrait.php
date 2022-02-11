<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\DateTime;

use DateTimeInterface;
use DateTimeZone;

/**
 * Methods that do not change the state of the DateTime object, shared with both the mutable and immutable datetimes.
 */
trait ImmutableDateTimeTrait
{
    // --------------------------------------------------
    // get

    public function getMicroSecond(): int
    {
        return (int) $this->format('u');
    }

    public function getSecond(): int
    {
        return (int) $this->format('s');
    }

    public function getMinute(): int
    {
        return (int) $this->format('i');
    }

    public function getHour(): int
    {
        return (int) $this->format('H');
    }

    public function getDay(): int
    {
        return (int) $this->format('d');
    }

    public function getMonth(): int
    {
        return (int) $this->format('m');
    }

    public function getYear(): int
    {
        return (int) $this->format('Y');
    }

    // --------------------------------------------------
    // diffIn

    public function diffInSeconds(DateTimeInterface $other, bool $absolute = false): int
    {
        $diff = $other->getTimestamp() - $this->getTimestamp();

        return $absolute ? abs($diff) : $diff;
    }

    public function diffInMinutes(DateTimeInterface $other, bool $absolute = false): int
    {
        $diff = (int) (($other->getTimestamp() - $this->getTimestamp()) / 60);

        return $absolute ? abs($diff) : $diff;
    }

    public function diffInHours(DateTimeInterface $other, bool $absolute = false): int
    {
        $diff = (int) (($other->getTimestamp() - $this->getTimestamp()) / 3600);

        return $absolute ? abs($diff) : $diff;
    }

    public function diffInDays(DateTimeInterface $other, bool $absolute = false): int
    {
        $diff = (int) (($other->getTimestamp() - $this->getTimestamp()) / 86400);

        return $absolute ? abs($diff) : $diff;
    }

    public function diffInMonths(DateTimeInterface $other, bool $absolute = false): int
    {
        $diff = $this->diff($other);

        if (! $absolute && (bool) $diff->invert) {
            return -$diff->m;
        }

        return $diff->m;
    }

    public function diffInYears(DateTimeInterface $other, bool $absolute = false): int
    {
        $diff = $this->diff($other);

        if (! $absolute && (bool) $diff->invert) {
            return -$diff->y;
        }

        return $diff->y;
    }

    // --------------------------------------------------
    // is

    public function isPast(): bool
    {
        return $this->getTimestamp() < time();
    }

    public function isNow(): bool
    {
        return $this->getTimestamp() === time();
    }

    public function isFuture(): bool
    {
        return $this->getTimestamp() > time();
    }

    // --------------------------------------------------
    // isCurrent

    public function isCurrentSecond(): bool
    {
        return $this->getTimestamp() === time();
    }

    public function isCurrentMinute(): bool
    {
        return (int) ($this->getTimestamp() / 60) === (int) (time() / 60);
    }

    public function isCurrentHour(): bool
    {
        return (int) ($this->getTimestamp() / 3600) === (int) (time() / 3600);
    }

    public function isCurrentDay(): bool
    {
        return (int) ($this->getTimestamp() / 86400) === (int) (time() / 86400);
    }

    public function isCurrentMonth(): bool
    {
        return $this->format('Ym') === date('Ym');
    }

    public function isCurrentYear(): bool
    {
        return $this->format('Y') === date('Y');
    }

    // --------------------------------------------------
    // isSame

    public function isSameSecond(int $second): bool
    {
        return $this->getTimestamp() === $second;
    }

    public function isSameMinute(int|DateTimeInterface $minute): bool
    {
        if (is_int($minute)) {
            return (int) $this->format('i') === $minute;
        }

        return $this->format('i') === $minute->format('i');
    }

    public function isSameHour(int|DateTimeInterface $hour): bool
    {
        if (is_int($hour)) {
            return (int) $this->format('H') === $hour;
        }

        return $this->format('H') === $hour->format('H');
    }

    public function isSameDay(int|DateTimeInterface $day): bool
    {
        if (is_int($day)) {
            return (int) $this->format('d') === $day;
        }

        return $this->format('d') === $day->format('d');
    }

    public function isSameMonth(int|DateTimeInterface $month): bool
    {
        if (is_int($month)) {
            return (int) $this->format('m') === $month;
        }

        return $this->format('m') === $month->format('m');
    }

    public function isSameYear(int|DateTimeInterface $year): bool
    {
        if (is_int($year)) {
            return (int) $this->format('Y') === $year;
        }

        return $this->format('Y') === $year->format('Y');
    }

    // --------------------------------------------------
    //

    public function toDateString(): string
    {
        return $this->format('Y-m-d');
    }

    public function toDateTimeString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function toIso8601String(): string
    {
        return $this->format('c');
    }

    public function toIso8601ZuluString(): string
    {
        return $this->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}

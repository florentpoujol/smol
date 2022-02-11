<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\DateTime;

final class DateTime extends \DateTime
{
    use ImmutableDateTimeTrait;

    // --------------------------------------------------
    // set

    public function setMicroSecond(int $microSecond): self
    {
        $this->setTime(
            (int) $this->format('H'),
            (int) $this->format('i'),
            (int) $this->format('s'),
            $microSecond,
        );

        return $this;
    }

    public function setSecond(int $second): self
    {
        $this->setTime(
            (int) $this->format('H'),
            (int) $this->format('i'),
            $second,
            (int) $this->format('u'),
        );

        return $this;
    }

    public function setMinute(int $minute): self
    {
        $this->setTime(
            (int) $this->format('H'),
            $minute,
            (int) $this->format('s'),
            (int) $this->format('u'),
        );

        return $this;
    }

    public function setHour(int $hour): self
    {
        $this->setTime(
            $hour,
            (int) $this->format('i'),
            (int) $this->format('s'),
            (int) $this->format('u'),
        );

        return $this;
    }

    public function setDay(int $day): self
    {
        $this->setDate(
            (int) $this->format('Y'),
            (int) $this->format('m'),
            $day,
        );

        return $this;
    }

    public function setMonth(int $month): self
    {
        $this->setDate(
            (int) $this->format('Y'),
            $month,
            (int) $this->format('d'),
        );

        return $this;
    }

    public function setYear(int $year): self
    {
        $this->setDate(
            $year,
            (int) $this->format('m'),
            (int) $this->format('d'),
        );

        return $this;
    }

    // --------------------------------------------------
    // add

    public function addMicroSeconds(int $microSeconds): self
    {
        $this->modify("$microSeconds microseconds");

        return $this;
    }

    public function addSeconds(int $seconds): self
    {
        $this->modify("$seconds seconds");

        return $this;
    }

    public function addMinutes(int $minutes): self
    {
        $this->modify("$minutes minutes");

        return $this;
    }

    public function addHours(int $hours): self
    {
        $this->modify("$hours hours");

        return $this;
    }

    public function addDays(int $days): self
    {
        $this->modify("$days days");

        return $this;
    }

    public function addMonths(int $months): self
    {
        $this->modify("$months months");

        return $this;
    }

    public function addYears(int $years): self
    {
        $this->modify("$years years");

        return $this;
    }

    // --------------------------------------------------
    // add*WithoutOverflow

    /**
     * Add the provided number of months, but without changing the current day.
     */
    public function addMonthsWithoutOverflow(int $months): self
    {
        $year = $this->getYear();
        $month = $this->getMonth() + $months;

        if ($month > 12) {
            $diffYear = (int) ($month / 12);
            $year += $diffYear;
            $month -= $diffYear * 12;
        }

        $day = $this->getDay();
        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        $this->setDate($year, $month, $day);

        return $this;
    }

    public function addYearsWithoutOverflow(int $years): self
    {
        $year = $this->getYear() + $years;
        $month = $this->getMonth();
        $day = $this->getDay();

        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        $this->setDate($year, $month, $day);

        return $this;
    }

    // --------------------------------------------------
    // sub

    public function subMicroSeconds(int $microSeconds): self
    {
        $this->modify("- $microSeconds microseconds");

        return $this;
    }

    public function subSeconds(int $seconds): self
    {
        $this->modify("- $seconds seconds");

        return $this;
    }

    public function subMinutes(int $minutes): self
    {
        $this->modify("- $minutes minutes");

        return $this;
    }

    public function subHours(int $hours): self
    {
        $this->modify("- $hours hours");

        return $this;
    }

    public function subDays(int $days): self
    {
        $this->modify("- $days days");

        return $this;
    }

    public function subMonths(int $months): self
    {
        $this->modify("- $months months");

        return $this;
    }

    public function subYears(int $years): self
    {
        $this->modify("- $years years");

        return $this;
    }

    // --------------------------------------------------
    // sub*WithoutOverflow

    public function subMonthsWithoutOverflow(int $months): self
    {
        $year = $this->getYear();
        $month = $this->getMonth() - $months;

        if ($month > 12) {
            $diffYear = (int) ($month / 12);
            $year -= $diffYear;
            $month -= $diffYear * 12;
        }

        $day = $this->getDay();
        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        $this->setDate($year, $month, $day);

        return $this;
    }

    public function subYearsWithoutOverflow(int $years): self
    {
        $year = $this->getYear() - $years;
        $month = $this->getMonth();

        $day = $this->getDay();
        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        $this->setDate($year, $month, $day);

        return $this;
    }

    // --------------------------------------------------
    // startOf

    public function startOfSecond(): self
    {
        $this->setTime(
            (int) $this->format('H'),
            (int) $this->format('i'),
            (int) $this->format('s'),
            0,
        );

        return $this;
    }

    public function startOfMinute(): self
    {
        $this->setTime(
            (int) $this->format('H'),
            (int) $this->format('i'),
            0,
            0,
        );

        return $this;
    }

    public function startOfHour(): self
    {
        $this->setTime(
            (int) $this->format('H'),
            0,
            0,
            0,
        );

        return $this;
    }

    public function startOfDay(): self
    {
        $this->setTime(0, 0, 0, 0);

        return $this;
    }

    public function startOfMonth(): self
    {
        $this->setDate(
            (int) $this->format('Y'),
            (int) $this->format('m'),
            1,
        );

        $this->setTime(0, 0, 0, 0);

        return $this;
    }

    public function startOfYear(): self
    {
        $this->setDate(
            (int) $this->format('Y'),
            1,
            1,
        );

        $this->setTime(0, 0, 0, 0);

        return $this;
    }

    // --------------------------------------------------
    // endOf

    public function endOfSecond(): self
    {
        $this->setTime(
            (int) $this->format('H'),
            (int) $this->format('i'),
            (int) $this->format('s'),
            999_999,
        );

        return $this;
    }

    public function endOfMinute(): self
    {
        $this->setTime(
            (int) $this->format('H'),
            (int) $this->format('i'),
            59,
            999_999,
        );

        return $this;
    }

    public function endOfHour(): self
    {
        $this->setTime(
            (int) $this->format('H'),
            59,
            59,
            999_999,
        );

        return $this;
    }

    public function endOfDay(): self
    {
        $this->setTime(23, 59, 59, 999_999);

        return $this;
    }

    public function endOfMonth(): self
    {
        $this->setDate(
            (int) $this->format('Y'),
            (int) $this->format('m'),
            (int) $this->format('t'), // t = number of days in the current month
        );

        $this->setTime(23, 59, 59, 999_999);

        return $this;
    }

    public function endOfYear(): self
    {
        $this->setDate(
            (int) $this->format('Y'),
            12,
            31,
        );

        $this->setTime(23, 59, 59, 999_999);

        return $this;
    }
}

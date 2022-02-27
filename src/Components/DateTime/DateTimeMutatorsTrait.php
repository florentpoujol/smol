<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\DateTime;

use DateTimeImmutable;

trait DateTimeMutatorsTrait
{
    private function getInstance(): self
    {
        if ($this instanceof DateTimeImmutable) {
            return clone $this;
        }

        return $this;
    }

    // --------------------------------------------------
    // set

    public function setMicroSecond(int $microSecond): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            (int) $instance->format('i'),
            (int) $instance->format('s'),
            $microSecond,
        );
    }

    public function setSecond(int $second): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            (int) $instance->format('i'),
            $second,
            (int) $instance->format('u'),
        );
    }

    public function setMinute(int $minute): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            $minute,
            (int) $instance->format('s'),
            (int) $instance->format('u'),
        );
    }

    public function setHour(int $hour): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            $hour,
            (int) $instance->format('i'),
            (int) $instance->format('s'),
            (int) $instance->format('u'),
        );
    }

    public function setDay(int $day): self
    {
        $instance = $this->getInstance();

        return $instance->setDate(
            (int) $instance->format('Y'),
            (int) $instance->format('m'),
            $day,
        );
    }

    public function setMonth(int $month): self
    {
        $instance = $this->getInstance();

        return $instance->setDate(
            (int) $instance->format('Y'),
            $month,
            (int) $instance->format('d'),
        );
    }

    public function setYear(int $year): self
    {
        $instance = $this->getInstance();

        return $instance->setDate(
            $year,
            (int) $instance->format('m'),
            (int) $instance->format('d'),
        );
    }

    // --------------------------------------------------
    // add

    public function addMicroSeconds(int $microSeconds): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$microSeconds microseconds");
    }

    public function addSeconds(int $seconds): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$seconds seconds");
    }

    public function addMinutes(int $minutes): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$minutes minutes");
    }

    public function addHours(int $hours): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$hours hours");
    }

    public function addDays(int $days): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$days days");
    }

    public function addMonths(int $months): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$months months");
    }

    public function addYears(int $years): self
    {
        $instance = $this->getInstance();

        return $instance->modify("$years years");
    }

    // --------------------------------------------------
    // add*WithoutOverflow

    /**
     * Add the provided number of months, but without changing the current day.
     */
    public function addMonthsWithoutOverflow(int $months): self
    {
        $instance = $this->getInstance();

        $year = $instance->getYear();
        $month = $instance->getMonth() + $months;

        if ($month > 12) {
            $diffYear = (int) ($month / 12);
            $year += $diffYear;
            $month -= $diffYear * 12;
        }

        $day = $instance->getDay();
        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        return $instance->setDate($year, $month, $day);
    }

    public function addYearsWithoutOverflow(int $years): self
    {
        $instance = $this->getInstance();

        $year = $instance->getYear() + $years;
        $month = $instance->getMonth();
        $day = $instance->getDay();

        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        return $instance->setDate($year, $month, $day);
    }

    // --------------------------------------------------
    // sub

    public function subMicroSeconds(int $microSeconds): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $microSeconds microseconds");
    }

    public function subSeconds(int $seconds): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $seconds seconds");
    }

    public function subMinutes(int $minutes): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $minutes minutes");
    }

    public function subHours(int $hours): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $hours hours");
    }

    public function subDays(int $days): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $days days");
    }

    public function subMonths(int $months): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $months months");
    }

    public function subYears(int $years): self
    {
        $instance = $this->getInstance();

        return $instance->modify("- $years years");
    }

    // --------------------------------------------------
    // sub*WithoutOverflow

    public function subMonthsWithoutOverflow(int $months): self
    {
        $instance = $this->getInstance();

        $year = $instance->getYear();
        $month = $instance->getMonth() - $months;

        if ($month < 0) {
            $diffYear = (int) ($months / 12);
            $year -= $diffYear;
            $month = $instance->getMonth() - ($months - 12 * $diffYear);

            if ($month < 0) {
                --$year;
                $month = 12 + $month;
            }
        }

        $day = $instance->getDay();
        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        return $instance->setDate($year, $month, $day);
    }

    public function subYearsWithoutOverflow(int $years): self
    {
        $instance = $this->getInstance();

        $year = $instance->getYear() - $years;
        $month = $instance->getMonth();

        $day = $instance->getDay();
        if ($day >= 29) {
            $day = min($day, (int) date('t', strtotime("$year-$month-01")));
        }

        return $instance->setDate($year, $month, $day);
    }

    // --------------------------------------------------
    // startOf

    public function startOfSecond(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            (int) $instance->format('i'),
            (int) $instance->format('s'),
            0,
        );
    }

    public function startOfMinute(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            (int) $instance->format('i'),
            0,
            0,
        );
    }

    public function startOfHour(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            0,
            0,
            0,
        );
    }

    public function startOfDay(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(0, 0, 0, 0);
    }

    public function startOfMonth(): self
    {
        $instance = $this->getInstance();

        return $instance
            ->setDate(
                (int) $instance->format('Y'),
                (int) $instance->format('m'),
                1,
            )
            ->setTime(0, 0, 0, 0);
    }

    public function startOfYear(): self
    {
        $instance = $this->getInstance();

        return $instance
            ->setDate(
                (int) $instance->format('Y'),
                1,
                1,
            )
            ->setTime(0, 0, 0, 0);
    }

    // --------------------------------------------------
    // endOf

    public function endOfSecond(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            (int) $instance->format('i'),
            (int) $instance->format('s'),
            999_999,
        );
    }

    public function endOfMinute(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            (int) $instance->format('i'),
            59,
            999_999,
        );
    }

    public function endOfHour(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(
            (int) $instance->format('H'),
            59,
            59,
            999_999,
        );
    }

    public function endOfDay(): self
    {
        $instance = $this->getInstance();

        return $instance->setTime(23, 59, 59, 999_999);
    }

    public function endOfMonth(): self
    {
        $instance = $this->getInstance();

        return $instance
            ->setDate(
                (int) $instance->format('Y'),
                (int) $instance->format('m'),
                (int) $instance->format('t'), // t = number of days in the current month
            )
            ->setTime(23, 59, 59, 999_999);
    }

    public function endOfYear(): self
    {
        $instance = $this->getInstance();

        return $instance
            ->setDate(
                (int) $instance->format('Y'),
                12,
                31,
            )
            ->setTime(23, 59, 59, 999_999);
    }
}

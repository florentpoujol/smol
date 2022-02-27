# Datetime

The datetime component provide a `DateTime` and `DateTimeImmutable` classes that extends PHP's base one and add several convenience methods.

You can think of them as a mini-[Carbon](https://carbon.nesbot.com).

## Available methods that do not modify the instance

### get*

They all return an integer:
```php
$dt = new DateTime('2022-02-27 14:08:01.123456');
$dt->getMicrosecond(); // 123456
$dt->getSecond(); // 1
$dt->getMinute(); // 8
$dt->getHour(); // 14
$dt->getDay(); // 27
$dt->getMonth(); // 2
$dt->getYear(); // 2022
```

### diffIn*

They all return an integer. 
```php
$dt = new DateTime('2022-02-27 14:08:01.123456');
$otherDt = new DateTime('2021-02-27 14:08:01.123456');

$dt->diffInSeconds($otherDt), // -31536000
$dt->diffInMinutes($otherDt), // -525600
$dt->diffInHours($otherDt), // -8760
$dt->diffInDays($otherDt), // -365
$dt->diffInMonths($otherDt), // 0
$dt->diffInYears($otherDt), // -1
```

The second argument `$absolute` allow to return the result as always positive:
```php
$dt = new DateTime('2022-02-27 14:08:01.123456');
$otherDt = new DateTime('2021-02-27 14:08:01.123456');

$dt->diffInMinutes($otherDt), // -525600
$dt->diffInMinutes($otherDt, true), // 525600
```

### is*

```php
(new DateTime('- 1 day'))->isPast(); // true
(new DateTime())->isNow(); // true
(new DateTime('+ 1 day'))->isFuture(); // true
```

### isCurrent*

```php
$now = new DateTime();

$now->isCurrentSecond(); // true
$now->isCurrentMinute(); // true
$now->isCurrentHour(); // true
$now->isCurrentDay(); // true
$now->isCurrentMonth(); // true
$now->isCurrentYear(); // true
```

### isSame*

```php
$dt = new DateTime('2022-02-27 14:08:01.123456');
$otherDt = new DateTime('2022-03-27 15:08:02');

$dt->isSameSecond($otherDt); // false
$dt->isSameMinute($otherDt); // true
$dt->isSameHour($otherDt); // false
$dt->isSameDay($otherDt); // true
$dt->isSameMonth($otherDt); // false
$dt->isSameYear($otherDt); // true

$dt->isSameSecond(1); // true
$dt->isSameMinute(9); // false
$dt->isSameHour(14); // true
$dt->isSameDay(28); // false
$dt->isSameMonth(02); // true
$dt->isSameYear(2021); // false
```

### to*

```php
$dt = new DateTime('2022-02-27 14:08:01.123456+02:00');

$dt->toDateString(); // 2022-02-27
$dt->toDateTimeString(); // 2022-02-27 14:08:01
$dt->toIso8601String(); // 2022-02-27T14:08:01+02:00
$dt->toIso8601ZuluString(); // 2022-02-27T12:08:01Z
```

## Available methods that do return a modified instance (and modify the current instance if not immutable) 

### set*

```php
$dt = new DateTimeImmutable('2022-02-27 14:08:01.123456');

$dt->setMicrosecond(1); // 2022-02-27 14:08:01.000001
$dt->setSecond(2); // 2022-02-27 14:08:02
$dt->setMinute(3); // 2022-02-27 14:03:01
$dt->setHour(4); // 2022-02-27 04:08:01
$dt->setDay(5); // 2022-02-05 14:08:01
$dt->setMonth(6); // 2022-06-27 14:08:01
$dt->setYear(2021); // 2021-02-27 14:08:01
```

### add*

```php
$dt = new DateTimeImmutable('2022-02-27 14:08:01.123456');

$dt->addMicroSeconds(1); // 2022-02-27 14:08:01.123457
$dt->addSeconds(2); // 2022-02-27 14:08:03.123456
$dt->addMinutes(3); // 2022-02-27 14:11:01.123456
$dt->addHours(4); // 2022-02-27 18:08:01.123456
$dt->addDays(5); // 2022-03-04 14:08:01.123456
$dt->addMonths(6); // 2022-08-27 14:08:01.123456
$dt->addYears(7); // 2029-02-27 14:08:01.123456
```

```php
$dt = new DateTime('2022-02-27 14:08:01.123456');

$dt->addMonthsWithoutOverflow(6);
$dt->addYearsWithoutOverflow(7);
```

### sub*

```php
$dt = new DateTime('2022-02-27 14:08:01.123456');

$dt->subMicroSeconds(1); // 2022-02-27 14:08:01.123455
$dt->subSeconds(2); // 2022-02-27 14:007:59.123455
$dt->subMinutes(3); // 2022-02-27 14:05:01.123455
$dt->subHours(4); // 2022-02-27 10:08:01.123455
$dt->subDays(5); // 2022-02-22 14:08:01.123455
$dt->subMonths(6); // 2021-08-27 14:08:01.123455
$dt->subYears(7); // 2015-02-27 14:08:01.123455
```

```php
$dt = new DateTime('2022-02-27 14:08:01.123456');

$dt->subMonthsWithoutOverflow(6);
$dt->subYearsWithoutOverflow(7);
```

### startOf*

```php
$dt = new DateTime('2022-02-27 14:08:01.123456');

$dt->startOfSecond(); // 2022-02-27 14:08:01.000000
$dt->startOfMinute(); // 2022-02-27 14:08:00
$dt->startOfHour(); // 2022-02-27 14:00:00
$dt->startOfDay(); // 2022-02-27 00:00:00
$dt->startOfMonth(); // 2022-02-01 00:00:00
$dt->startOfYear(); // 2022-01-01 00:00:00
```

### endOf*

```php
$dt = new DateTime('2022-02-27 14:08:01.123456');

$dt->endOfSecond(); // 2022-02-27 14:08:01.999999
$dt->endOfMinute(); // 2022-02-27 14:08:59
$dt->endOfHour(); // 2022-02-27 14:59:59
$dt->endOfDay(); // 2022-02-27 23:59:59
$dt->endOfMonth(); // 2022-02-28 23:59:59
$dt->endOfYear(); // 2022-12-31 23:59:59
```

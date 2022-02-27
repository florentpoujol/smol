<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    use DateTimeGettersTrait;
    use DateTimeMutatorsTrait;

    private string $fqcn = DateTime::class;
}

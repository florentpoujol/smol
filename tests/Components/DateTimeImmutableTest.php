<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use FlorentPoujol\Smol\Components\DateTime\DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DateTimeImmutableTest extends TestCase
{
    use DateTimeGettersTrait;
    use DateTimeMutatorsTrait;

    private string $fqcn = DateTimeImmutable::class;
}

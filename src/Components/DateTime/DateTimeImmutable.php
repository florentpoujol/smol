<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\DateTime;

final class DateTimeImmutable extends \DateTimeImmutable
{
    use ImmutableDateTimeTrait;
}

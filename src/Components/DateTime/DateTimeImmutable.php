<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\DateTime;

final class DateTimeImmutable extends \DateTimeImmutable
{
    use ImmutableDateTimeTrait;
}

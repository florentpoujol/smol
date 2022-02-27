<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\DateTime;

final class DateTime extends \DateTime
{
    use DateTimeGettersTrait;
    use DateTimeMutatorsTrait;
}

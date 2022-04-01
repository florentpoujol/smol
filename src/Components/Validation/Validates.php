<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Validates
{
    public function __construct(
        /** @var array<string|Rule> */
        public array $rules = [],
    ) {
    }
}

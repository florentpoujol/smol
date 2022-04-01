<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Config;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Env
{
    public function __construct(
        public string $envVarName,
        public mixed $defaultValue = null,
    ) {
    }
}

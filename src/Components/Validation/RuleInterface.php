<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Validation;

interface RuleInterface
{
    public function passes(string $key, mixed $value): bool;

    public function getMessage(string $key): ?string;
}

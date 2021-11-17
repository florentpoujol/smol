<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Validation;

interface RuleInterface
{
    public function passes(mixed $value): bool;

    public function getMessage(): ?string;
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Validation;

interface RuleInterface
{
    /**
     * @param array<string, mixed>|object $data an assoc array, or an object
     */
    public function passes(mixed $value, array|object $data): bool;

    public function getMessage(): ?string;
}

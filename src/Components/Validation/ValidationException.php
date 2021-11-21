<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Validation;

use UnexpectedValueException;

final class ValidationException extends UnexpectedValueException
{
    public function __construct(
        /** @var array<string, mixed>|object an assoc array, or an object */
        public array|object $data,

        /** @var array<string, array<string>> The keys match the one found in the values */
        public array $messages,
    ) {
    }
}

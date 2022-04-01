<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Validation;

use Stringable;

enum ParametrizedRule: string implements ValidationRule
{
    case instanceof = 'instanceof';
    case regex = 'regex';
    case superiorOrEqual = '>=';
    case superior = '>';
    case inferiorOrEqual = '<=';
    case inferior = '<';
    case minLength = 'minLength';
    case maxLength = 'maxLength';
    case length = 'length';
    case equal = '==';
    case strictlyEqual = '===';
    case in = 'in';
    case sameAs = 'sameAs';

    /**
     * @param array<scalar|Stringable>|string $param
     */
    public function with(array|string $param): string
    {
        if (is_array($param)) {
            $param = implode(',', $param);
        }

        return "$this->value:$param";
    }
}

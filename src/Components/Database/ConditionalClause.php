<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Database;

final class ConditionalClause
{
    public string $condition = 'AND';
    public string $expression = '';
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Database;

final class ConditionalClause
{
    public string $condition = 'AND';
    public string $expression = '';
}

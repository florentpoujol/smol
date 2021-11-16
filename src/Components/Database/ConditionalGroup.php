<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Database;

final class ConditionalGroup
{
    public string $condition = 'AND';

    /** @var array<ConditionalClause|ConditionalGroup> */
    public array $clauses = [];
}

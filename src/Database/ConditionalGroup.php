<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Database;

final class ConditionalGroup
{
    public string $condition = 'AND';

    /** @var array<ConditionalClause|ConditionalGroup> */
    public array $clauses = [];
}

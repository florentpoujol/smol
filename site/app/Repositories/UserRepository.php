<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Repositories;

use FlorentPoujol\Smol\Site\app\Entities\User;

final class UserRepository extends PdoRepository
{
    protected string $table = 'users';
    protected string $entityFqcn = User::class;
}

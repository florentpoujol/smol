<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Repositories;

use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use FlorentPoujol\Smol\Site\app\Entities\User;
use PDO;

final class UserRepository extends PdoRepository
{
    protected string $table = 'users';
    protected string $entityFqcn = User::class;

    public function __construct(
        PDO $pdo,
        private ConfigRepository $config,
    ) {
        parent::__construct($pdo);
    }

    public function hashPassword(string $plainPassword): string
    {
        $algo = $this->config->get('auth.password.algo', PASSWORD_BCRYPT);
        $algoOptions = $this->config->get('auth.password.algo-options', [
            'rounds' => 15,
        ]);

        return password_hash($plainPassword, $algo, $algoOptions);
    }
}

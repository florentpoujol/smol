<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Entities;

use FlorentPoujol\Smol\Components\Entity;

final class User extends Entity
{
    public int $id;
    public string $name;
    public string $email;
    public string $password;
    public ?string $email_validated_at = null;
    /** @var ?string A token for various usages (email validation, reset password, remember cookie) */
    public ?string $auth_token = null;

    public function regenerateAuthToken(): void
    {
        $this->auth_token = bin2hex(random_bytes(25)); // 50 chars
    }
}

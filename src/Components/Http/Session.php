<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Http;

final class Session
{
    public string $id;

    /** @var array<string, mixed> */
    public array $data = [];

    public function regenerateId(): void
    {
        $this->id = bin2hex(random_bytes(20));
    }
}

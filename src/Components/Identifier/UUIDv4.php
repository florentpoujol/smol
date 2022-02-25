<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

/**
 * A 16 bytes, purely random identifier.
 */
final class UUIDv4 extends Identifier
{
    public function generate(): void
    {
        $hex = bin2hex(random_bytes(16));
        $hex[12] = '4';

        $this->binary = hex2bin($hex);
    }
}

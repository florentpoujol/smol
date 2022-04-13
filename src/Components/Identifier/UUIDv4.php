<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

/**
 * A 16 bytes, purely random identifier.
 */
final class UUIDv4 extends Identifier
{
    protected function generate(): string
    {
        $hex = bin2hex(random_bytes(16));
        $hex[12] = '4';

        return hex2bin($hex);
    }
}

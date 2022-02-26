<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Encrypter;

enum Cipher: string
{
    case AES_128_CBC = 'aes-128-cbc';
    case AES_256_CBC = 'aes-256-cbc';
    case AES_128_GCM = 'aes-128-gcm';
    case AES_256_GCM = 'aes-256-gcm';
    // AES = Advanced Encryption Standard
    // CBC = Cipher Block Chaining
    // GCM = Galois/Counter Mode

    /**
     * The expected size **in bytes** of the encryption key (typically 16 or 32).
     */
    public function getKeySize(): int
    {
        return match ($this) {
            self::AES_128_CBC, self::AES_128_GCM => 16,
            self::AES_256_CBC, self::AES_256_GCM => 32,
        };
    }

    public function hasAEAD(): bool
    {
        // AEAD = Authenticated Encryption With Associated Data
        return str_ends_with($this->value, 'gcm');
    }

    /**
     * @return string A cryptographically secure, random, binary string. Typically 16 or 32 bytes longs.
     */
    public function generateKey(): string
    {
        return random_bytes($this->getKeySize());
    }
}

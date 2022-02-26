<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Encrypter;

use Exception;
use RuntimeException;
use stdClass;

final class Encrypter
{
    public function __construct(
        /** @var string The encryption key, as a **binary** string */
        private string $passphrase,
        /** The algorithm used for encryption. */
        private Cipher $cipher = Cipher::AES_256_CBC,
    ) {
        $expectedSizeInBytes = $cipher->getKeySize();

        if (strlen(bin2hex($passphrase)) !== $expectedSizeInBytes * 2) {
            throw new RuntimeException("Cipher '{$cipher->value}' expect a key length of $expectedSizeInBytes.");
        }
    }

    /**
     * @return string A base64-encoded string
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher->value));

        $encryptedValue = openssl_encrypt(
            $serialize ? serialize($value) : (string) $value,
            $this->cipher->value,
            $this->passphrase,
            0, // 0 = return a base64 string
            $iv,
            $tag, // out
        );

        if ($encryptedValue === false) {
            throw new Exception('Could not encrypt the data.');
        }

        $iv = base64_encode($iv);

        $payload = new Payload(
            $serialize,
            $encryptedValue,
            $iv,
            $this->cipher->hasAEAD()
                ? base64_encode($tag)
                : $this->hash($iv, $encryptedValue),
        );

        return base64_encode(serialize((array) $payload));
    }

    public function decrypt(string $encodedValue, bool $unserialize = true): mixed
    {
        $payloadArray = unserialize(base64_decode($encodedValue, true), ['allowed_classes' => [Payload::class, stdClass::class]]);
        $payload = new Payload(...$payloadArray);

        $iv = base64_decode($payload->iv, true);
        if (strlen($iv) !== openssl_cipher_iv_length($this->cipher->value)) {
            throw new Exception('The IV is invalid.');
        }

        $tag = null;
        if ($this->cipher->hasAEAD()) {
            $tag = base64_decode($payload->mac, true);
        } elseif (! hash_equals($this->hash($payload->iv, $payload->encryptedValue), $payload->mac)) {
            throw new Exception('The MAC is invalid.');
        }

        $decryptedValue = openssl_decrypt(
            $payload->encryptedValue,
            $this->cipher->value,
            $this->passphrase,
            0, // 0 = the passed date is a base64 string, instead of a binary string
            $iv,
            $tag
        );

        if ($decryptedValue === false) {
            throw new Exception('Could not decrypt the data.');
        }

        return
            $payload->serialized && $unserialize
            ? unserialize($decryptedValue, ['allowed_classes' => [stdClass::class]])
            : $decryptedValue;
    }

    /**
     * Create a MAC for the given value.
     */
    private function hash(string $iv, string $encodedValue): string
    {
        return hash_hmac('sha256', $iv . $encodedValue, $this->passphrase);
    }
}

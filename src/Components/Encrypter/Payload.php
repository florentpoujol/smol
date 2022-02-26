<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Encrypter;

final class Payload
{
    public function __construct(
        /** @var bool Tell whether the encrypted value has been serialized priori to encryption */
        public readonly bool $serialized,

        /** @var string Base64 string */
        public readonly string $encryptedValue,

        /** @var string Base64 string */
        public readonly string $iv,

        /** @var string A base64 string of the tag for AEAD algorithms, or a SHA256 HMAC hash for non-AEAD algorithms */
        public readonly string $mac,
    ) {
    }
}

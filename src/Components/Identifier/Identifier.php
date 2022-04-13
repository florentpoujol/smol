<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

use UnexpectedValueException;

abstract class Identifier implements IdentifierInterface
{
    /** @var string A binary string */
    protected string $binary;

    abstract protected function generate(): string;

    /**
     * @param null|string $raw A binary string
     */
    public function __construct(string $raw = null)
    {
        $this->binary = $raw ?? $this->generate();
    }

    // --------------------------------------------------

    public function getRaw(): string
    {
        return $this->binary;
    }

    public function getHex(): string
    {
        return bin2hex($this->binary);
    }

    public function getUuid(): string
    {
        $hex = bin2hex($this->binary);
        if (strlen($hex) !== 32) {
            throw new UnexpectedValueException("Hexadecimal version of this identifier '$hex' is not 32 chars long.");
        }

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    // --------------------------------------------------

    public static function make(): static
    {
        return new static();
    }

    public static function fromString(string $id): static
    {
        return new static(hex2bin(str_replace('-', '', $id)));
    }
}

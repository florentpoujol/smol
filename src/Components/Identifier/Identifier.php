<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

use UnexpectedValueException;

abstract class Identifier implements IdentifierInterface
{
    /** @var string A binary string */
    protected string $binary;

    abstract protected function generate(): void;

    private function __construct()
    {
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
        if (strlen($hex) !== 16) {
            throw new UnexpectedValueException("Hexadecimal version of this identifier '$hex' is not 32 chars long.");
        }

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    /**
     * @return int Return the identifier as an integer. If the identifier is more than 8 butes, returns 0.
     */
    public function getInteger(): int
    {
        return bindec($this->binary);
    }

    // --------------------------------------------------

    public static function make(): static
    {
        $uuid = new static();
        $uuid->generate();

        return $uuid;
    }

    public static function fromString(string $uuid): static
    {
        $instance = new static();
        $instance->binary = hex2bin(str_replace('-', '', $uuid));

        return $instance;
    }

    public static function fromInteger(int $decimal): static
    {
        $instance = new static();
        $instance->binary = decbin($decimal);

        return $instance;
    }
}

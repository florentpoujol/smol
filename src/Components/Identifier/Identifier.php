<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

use UnexpectedValueException;

abstract class Identifier implements IdentifierInterface
{
    /** @var string A binary string */
    private string $binary;

    abstract protected function generate(): string;

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
        if (strlen($hex) !== 32) {
            throw new UnexpectedValueException("Hexadecimal version of this identifier '$hex' is not 32 chars long.");
        }

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    // --------------------------------------------------

    public static function make(): static
    {
        $uuid = new static();
        $uuid->binary = $uuid->generate();

        return $uuid;
    }

    public static function fromString(string $id): static
    {
        $instance = new static();
        $instance->binary = hex2bin(str_replace('-', '', $id));

        return $instance;
    }
}

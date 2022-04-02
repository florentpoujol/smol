<?php
/**
 * @see https://github.com/jenssegers/optimus/blob/master/src/Optimus.php
 */
declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Misc;

final class HashId
{
    /** @var int */
    private const MAX = 2147483647; // max value of a signed 4 bytes int

    public function __construct(
        private int $random,
        private int $prime = 2123809381,
        private int $inverse = 1885413229,
    ) {
    }

    public function encode(int $value): string
    {
        $int = (($value * $this->prime) & self::MAX) ^ $this->random;

        return trim(base64_encode(dechex($int)), '=');
    }

    public function decode(string $hasId): int
    {
        $int = hexdec(base64_decode($hasId));

        return (($int ^ $this->random) * $this->inverse) & self::MAX;
    }
}

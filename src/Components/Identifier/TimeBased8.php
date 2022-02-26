<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

/**
 * A 8 bytes time-based identifier that begins by the micro-timestamp on 7 bytes (enough for until the year 4253) and is followed by 1 random byte.
 */
final class TimeBased8 extends Identifier
{
    protected function generate(): string
    {
        $time = gettimeofday();
        $hexTime = '0' . dechex((int) ($time['sec'] . $time['usec']));
        // as of 2022, and until 2112, the hex version of the microtimestamp is 13 chars long, so we pad it here to 14

        return hex2bin($hexTime) . random_bytes(1);
    }

    public function getInteger(): int
    {
        return hexdec($this->getHex());
    }

    public static function fromInteger(int $id): static
    {
        return self::fromString(str_pad(dechex($id), 16, '0', STR_PAD_LEFT));
    }
}

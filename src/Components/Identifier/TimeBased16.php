<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Identifier;

use UnexpectedValueException;

/**
 * A 16 bytes time-based identifier that begins by the micro-timestamp on 7 bytes (enough for until the year 4253) and is followed by 9 random bytes.
 */
final class TimeBased16 extends Identifier
{
    protected function generate(): void
    {
        $times = explode(' ', microtime(), 2);
        // microtime() return something like "0.21080700 1645782542"
        // microtime(true) return something like "1645782542.210800" (with the last 2 digits always zero)

        $hexTime = dechex((int) ($times[1] . substr($times[0], 2, 6)));
        // as of 2022, and until 2112, $hexTime is 13 chars long
        $hexTime = str_pad($hexTime, 14, '0', STR_PAD_LEFT);

        $this->binary = hex2bin($hexTime) . random_bytes(9);
    }

    /**
     * @throws UnexpectedValueException When the identifier is more than 8 bytes
     */
    public function getInteger(): never
    {
        throw new UnexpectedValueException("PHP can't return a 16 bytes integer.");
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Cache;

final class CacheItem
{
    public function __construct(
        public mixed $value,
        public int $expirationTimestamp = PHP_INT_MAX,
    ) {
    }
}

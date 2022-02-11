<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Infrastructure\RateLimiter;

use FlorentPoujol\Smol\Components\Cache\CacheInterface;
use FlorentPoujol\Smol\Components\Cache\CacheRateLimiter;
use FlorentPoujol\Smol\Components\Config\ConfigRepository;

final class CacheRateLimiterFactory
{
    public function __construct(
        private CacheInterface $cache,
        private ConfigRepository $config,
    ) {
    }

    public function makeFromConfig(string $configKey): CacheRateLimiter
    {
        return new CacheRateLimiter($this->cache, ...$this->config->get("app.rate_limiters.$configKey"));
    }
}

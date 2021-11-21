<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\RateLimiter;

use FlorentPoujol\SmolFramework\Components\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Components\Config\ConfigRepository;
use FlorentPoujol\SmolFramework\Components\RateLimiter\CacheRateLimiter;

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

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\RateLimiter;

use FlorentPoujol\SmolFramework\Components\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Framework\ConfigRepository;

final class RateLimiterFactory
{
    public function __construct(
        private CacheInterface $cache,
        private ConfigRepository $config,
    ) {
    }

    public function make(string $name, int $ttlInSeconds): CacheLock
    {
        return new CacheLock(
            $name,
            $ttlInSeconds,
            $this->cache
        );
    }

    public function makeFromConfig(string $name, int $ttlInSeconds): CacheLock
    {
        $config = $this->config->get("app.rate_limiters.$configKey");

        $this->setup(
            $configKey,
            ...$config
        );
    }
}

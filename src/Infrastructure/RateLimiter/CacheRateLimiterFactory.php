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

    /**
     * @param string $uniqueKey Something beside the key from the config to uniquely scope this rate limiter like a user's email or IP address
     */
    public function makeFromConfig(string $configKey, string $uniqueKey): CacheRateLimiter
    {
        $args = $this->config->get("app.rate_limiters.$configKey");
        $args['name'] .= ":$uniqueKey";

        return new CacheRateLimiter($this->cache, ...$args);
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Infrastructure;

use FlorentPoujol\SmolFramework\Components\Container\Container;

interface ServiceProviderInterface
{
    public function register(Container $container): void;

    public function boot(): void;

    public function stop(): void;
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework;

use FlorentPoujol\SmolFramework\Components\Container\Container;

interface ServiceProviderInterface
{
    public function register(Container $container): void;

    public function boot(): void;
}

<?php

declare(strict_types=1);

use FlorentPoujol\SmolFramework\Framework\Framework;
use FlorentPoujol\SmolFramework\Framework\RequestHandlers\SingleRequestHandler;
use FlorentPoujol\SmolFramework\Framework\SmolServiceProvider;

require __DIR__ . '/../vendor/autoload.php';

// \FlorentPoujol\SmolFramework\Framework\read_environment_file(__DIR__ . '/../.env');

$framework = Framework::make(__DIR__ . '/..');

// --------------------------------------------------
// register service providers and/or do other init steps

$framework->setServiceProviders([
    SmolServiceProvider::class,
    SingleRequestHandler::class,
    // YourAppServiceProvider::class,
]);

// --------------------------------------------------

$framework->run();

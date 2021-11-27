<?php

declare(strict_types=1);

use FlorentPoujol\SmolFramework\Framework\Framework;

require __DIR__ . '/../vendor/autoload.php';

$framework = Framework::make(__DIR__ . '/..');

// set custom container if needed
// set FPM reqeust handler

// set service providers
// set plugins in order

$framework->register();

$framework->boot();

$framework->run();

$framework->cleanUp();

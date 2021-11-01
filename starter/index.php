<?php

declare(strict_types=1);

use FlorentPoujol\SmolFramework\Framework;

require __DIR__ . '/../vendor/autoload.php';

$framework = Framework::make(__DIR__ . '/..');

$framework->handleHttpRequest();

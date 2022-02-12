<?php

declare(strict_types=1);

use FlorentPoujol\Smol\Site\app\App;
use FlorentPoujol\Smol\Site\app\LoggerFactory;
use function FlorentPoujol\Smol\Infrastructure\read_environment_file;

read_environment_file('.env');

$app = new App(__DIR__, 'production');
$app->register();

// --------------------------------------------------
// now "seed" the container with services we know we need every times

$loggerFactory = $app->container->get(LoggerFactory::class);
$loggerFactory->get('error');
$loggerFactory->get('http-request');

//...

// --------------------------------------------------
// and then cache the fully developped container

$app->cache();

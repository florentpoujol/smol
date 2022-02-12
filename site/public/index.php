<?php

declare(strict_types=1);

use FlorentPoujol\Smol\Infrastructure\HttpKernel;
use FlorentPoujol\Smol\Site\app\App;
use Psr\Http\Message\ServerRequestInterface;
use function FlorentPoujol\Smol\Infrastructure\env;
use function FlorentPoujol\Smol\Infrastructure\read_environment_file;

require __DIR__ . '/../vendor/autoload.php';

// --------------------------------------------------
// register service providers and/or do other init steps

read_environment_file(__DIR__ . '/../.env');

$app = App::make(__DIR__ . './..', env('APP_ENV'));

$app->register();

$app->boot();

// --------------------------------------------------
// actually handle the HTTP request

$httpKernel = new HttpKernel($app->container);

$serverRequest = $app->container->get(ServerRequestInterface::class);

$response = $httpKernel->handle($serverRequest);

$httpKernel->sendResponse($response);

exit(0);

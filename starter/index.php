<?php

declare(strict_types=1);

use FlorentPoujol\SmolFramework\Infrastructure\Framework;
use FlorentPoujol\SmolFramework\Infrastructure\HttpKernel;
use FlorentPoujol\SmolFramework\Infrastructure\SmolServiceProvider;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

// \FlorentPoujol\SmolFramework\Framework\read_environment_file(__DIR__ . '/../.env');

$framework = Framework::make(__DIR__ . '/..');

// --------------------------------------------------
// register service providers and/or do other init steps

$framework->setServiceProviders([
    SmolServiceProvider::class,
    // YourAppServiceProvider::class,
]);

$framework->register();

$framework->boot();

// --------------------------------------------------
// actually handle the HTTP request

$httpKernel = new HttpKernel($framework->getContainer());

/** @var ServerRequestInterface $serverRequest */
$serverRequest = $framework->getContainer()->get(ServerRequestInterface::class);

$response = $httpKernel->handle($serverRequest);

// --------------------------------------------------
// send response to client

http_response_code($response->getStatusCode());

/**
 * @var array<string> $values
 */
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}

$response->getBody()->rewind();

echo $response->getBody()->getContents();

$response->getBody()->close();

// --------------------------------------------------
// stops/cleanup the framework

$framework->stop();

exit(0);

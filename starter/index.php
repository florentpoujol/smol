<?php

declare(strict_types=1);

use FlorentPoujol\Smol\Infrastructure\HttpKernel;
use FlorentPoujol\Smol\Infrastructure\Project;
use FlorentPoujol\Smol\Infrastructure\SmolServiceProvider;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

// \FlorentPoujol\Smol\Framework\read_environment_file(__DIR__ . '/../.env');

$project = Project::make(__DIR__ . '/..');

// --------------------------------------------------
// register service providers and/or do other init steps

$project->setServiceProviders([
    SmolServiceProvider::class,
    // YourAppServiceProvider::class,
]);

$project->register();

$project->boot();

// --------------------------------------------------
// actually handle the HTTP request

$httpKernel = new HttpKernel($project->getContainer());

/** @var ServerRequestInterface $serverRequest */
$serverRequest = $project->getContainer()->get(ServerRequestInterface::class);

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

$project->stop();

exit(0);

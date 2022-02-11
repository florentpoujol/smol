<?php

declare(strict_types=1);

use FlorentPoujol\Smol\Infrastructure\Framework;
use FlorentPoujol\Smol\Infrastructure\HttpKernel;
use FlorentPoujol\Smol\Infrastructure\SmolServiceProvider;
use Nyholm\Psr7Server\ServerRequestCreator;
use Swoole\HTTP\Server;

$server = new Swoole\HTTP\Server('127.0.0.1', 9501);

// see https://www.swoole.co.uk/docs/modules/swoole-server/configuration for all possible config
$server->set([
    'worker_num' => 1,      // The number of worker processes to start
    // 'task_worker_num' => 4,  // The amount of task workers to start
    'backlog' => 128,       // TCP backlog connection number
]);

$framework = new Framework([
    'baseAppPath' => __DIR__,
    'environment' => 'local',
]);

$framework->setServiceProviders([
    SmolServiceProvider::class,
    // YourAppServiceProvider::class,
]);

// Triggered when the HTTP Server starts, connections are accepted after this callback is executed
$server->on('Start', function (Server $server) use ($framework): void {
    echo 'Starting server... ' . PHP_EOL;

    $framework->register();

    $framework->getContainer()->setInstance(Server, $server);

    $framework->boot();
});

// Triggered when new worker processes starts
$server->on('WorkerStart', function (Server $server, int $workerId): void {
    echo 'WorkerStart... ' . PHP_EOL;
});

$httpKernel = new HttpKernel($framework->getContainer());

/** @var ServerRequestCreator $psrServerRequestCreator */
$psrServerRequestCreator = $framework->getContainer()->get(ServerRequestCreator::class);

// The main HTTP server request callback event, entry point for all incoming HTTP requests
$server->on('Request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) use ($psrServerRequestCreator, $httpKernel): void {
    // turn swoole request to array and get get psr server resquest
    $psrServerRequest = $psrServerRequestCreator->fromArrays([], [], [],[], [], [], []);

    $psrResponse = $httpKernel->handle($psrServerRequest);

    // seed swoole response from psr respnose
    $swooleResponse->send($psrResponse->getBody()->getContents());
});

// Triggered when worker processes are being stopped
$server->on('WorkerStop', function (Server $server, int $workerId): void {
    echo 'WorkerStop... ' . PHP_EOL;
});

// Triggered when the server is shutting down
$server->on('Shutdown', function (Server $server, int $workerId): void {
    $framework->stop();
    echo 'Shutdown server... ' . PHP_EOL;
});

// $server->on('Task', function (Server $server, int $workerId): void {
//     echo 'on Task... ' . PHP_EOL;
// });

$server->start();

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Container\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ServiceFactories
{
    public static function makeServerRequest(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();

        $serverRequestFactory = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
        );

        return new ServerRequest($serverRequestFactory->fromGlobals());
    }

    public static function makeResponse(): ResponseInterface
    {
        return new Response();
    }

    public static function makePdo(Container $container, array $constructorArguments = []): PDO
    {
        $defaultOptions = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($constructorArguments === []) {
            $constructorArguments = $container->get(ConfigRepository::class)->get('app.database', []);
        }

        $constructorArguments['options'] = array_merge($defaultOptions, $constructorArguments['options'] ?? []);

        return new PDO(...$constructorArguments);
    }
}

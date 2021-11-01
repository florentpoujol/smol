<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
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

        return $serverRequestFactory->fromGlobals();
    }

    public static function makeResponse(): ResponseInterface
    {
        return new Response();
    }
}

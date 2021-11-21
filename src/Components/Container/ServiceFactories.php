<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Container;

use FlorentPoujol\SmolFramework\Components\Config\ConfigRepository;
use FlorentPoujol\SmolFramework\Framework\Http\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Redis;

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

    /**
     * @param array{dsn: string, username: ?string, password: ?string, options: ?array} $constructorArguments
     */
    public static function makePdo(Container $container, array $constructorArguments = null): PDO
    {
        $defaultOptions = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($constructorArguments === null) {
            $constructorArguments = $container->get(ConfigRepository::class)->get('app.database', []);
        }

        $constructorArguments['options'] = array_merge($defaultOptions, $constructorArguments['options'] ?? []);

        return new PDO(...$constructorArguments);
    }

    public static function makeRedis(Container $container): Redis
    {
        $redisExtVersion = phpversion('redis');
        if ($redisExtVersion === false) {
            throw new \Exception("the phpredis exception isn't installed");
        }

        $redis = new Redis();
        /** @var array<string, string> $connectionInfo */
        $connectionInfo = $container->get(ConfigRepository::class)->get('app.phpredis');

        if (version_compare($redisExtVersion, '5.3.0', '>=')) {
            if (isset($connectionInfo['auth'])) {
                $redis->auth($connectionInfo['auth']);
                unset($connectionInfo['auth']);
            }
        }

        $redis->pconnect(...$connectionInfo);

        return $redis;
    }
}

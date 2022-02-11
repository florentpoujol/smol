<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Infrastructure;

use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use FlorentPoujol\Smol\Components\Container\Container;
use FlorentPoujol\Smol\Components\Log\ResourceLogger;
use FlorentPoujol\Smol\Infrastructure\Http\Psr15RequestHandler;
use FlorentPoujol\Smol\Infrastructure\Http\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
use PDO;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Redis;

/**
 * Common/Default services for Smol Framework.
 */
final class SmolServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->bind(ServerRequestCreator::class, [$this, 'makePsrServerRequestCreator']);
        $container->bind(ServerRequestInterface::class, [$this, 'makePsrServerRequest']);
        $container->bind(ResponseInterface::class, fn () => new Response());
        $container->bind(RequestInterface::class, Request::class); // client request
        $container->bind(RequestHandlerInterface::class, Psr15RequestHandler::class);

        $container->bind(PDO::class, [$this, 'makePdo']);
        $container->bind(Redis::class, [$this, 'makeRedis']);
        $container->bind(LoggerInterface::class, ResourceLogger::class);
    }

    public function boot(): void
    {
        // nothing to do here
    }

    public function stop(): void
    {
        // nothing to do here
    }

    // --------------------------------------------------

    public function makePsrServerRequestCreator(): ServerRequestCreator
    {
        $psr17Factory = new Psr17Factory();

        return new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    public function makePsrServerRequest(): ServerRequestInterface
    {
        return new ServerRequest($this->makePsrServerRequestCreator()->fromGlobals());
    }

    /**
     * @param array{dsn: string, username: ?string, password: ?string, options: ?array} $constructorArguments
     */
    public function makePdo(Container $container, array $constructorArguments = null): PDO
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

    public function makeRedis(Container $container): Redis
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

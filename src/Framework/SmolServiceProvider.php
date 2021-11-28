<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework;

use FlorentPoujol\SmolFramework\Components\Config\ConfigRepository;
use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Components\Log\ResourceLogger;
use FlorentPoujol\SmolFramework\Framework\Http\Psr15RequestHandler;
use PDO;
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
        $container->bind(PDO::class, [$this, 'makePdo']);
        $container->bind(Redis::class, [$this, 'makeRedis']);
        $container->bind(RequestHandlerInterface::class, Psr15RequestHandler::class);
        $container->bind(LoggerInterface::class, ResourceLogger::class);
    }

    public function boot(): void
    {
    }

    // --------------------------------------------------

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

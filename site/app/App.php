<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app;

use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use FlorentPoujol\Smol\Components\Container\Container;
use FlorentPoujol\Smol\Components\Log\ResourceLogger;
use FlorentPoujol\Smol\Infrastructure\Http\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
use PDO;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Redis;

final class App
{
    public readonly Container $container;
    public readonly string $baseAppPath;
    public readonly string $environment;

    public function __construct(string $baseAppPath, string $environment)
    {
        $this->container = new Container();

        $path = realpath($baseAppPath);
        assert(is_string($path));
        $this->baseAppPath = $path;

        $this->environment = $environment;
    }

    public static function make(string $baseAppPath, string $environment): self
    {
        $realBaseAppPath = realpath($baseAppPath);
        assert(is_string($realBaseAppPath));

        $cachePath = $realBaseAppPath . '/writable/app.cached';
        if (! file_exists($cachePath)) {
            return new self($baseAppPath, $environment);
        }

        $content = file_get_contents($cachePath);
        assert(is_string($content));

        $allowedClasses = [
            self::class, Container::class,
        ];

        /** @var self $app */
        $app = unserialize($content, ['allowed_classes' => $allowedClasses]);

        if (! $app instanceof self || ! $app->container instanceof Container) { // @phpstan-ignore-line
            throw new \Exception();
        }
        // TODO, base on the environment, throw an error, or just default to a new instance
        //   also check that the app path and the environment are the same

        return $app;
    }

    public function cache(): void
    {
        $serialized = serialize($this);

        file_put_contents($this->baseAppPath . '/writable/app.cached', $serialized);
    }

    private bool $registered = false;

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $this->container->bind(ServerRequestCreator::class, [self::class, 'makePsrServerRequestCreator']);
        $this->container->bind(ServerRequestInterface::class, [self::class, 'makePsrServerRequest']);
        $this->container->bind(ResponseInterface::class, Response::class);
        $this->container->bind(RequestInterface::class, Request::class); // client request

        $this->container->bind(PDO::class, [self::class, 'makePdo']);
        $this->container->bind(Redis::class, [self::class, 'makeRedis']);
        $this->container->bind(LoggerInterface::class, ResourceLogger::class);
        $this->container->bind(ErrorLoggerInterface::class, [self::class, 'makeErrorLogger']);
    }

    public function boot(): void
    {
        // nothing to do here
    }

    // --------------------------------------------------

    public static function makePsrServerRequestCreator(): ServerRequestCreator
    {
        $psr17Factory = new Psr17Factory();

        return new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    public static function makePsrServerRequest(): ServerRequestInterface
    {
        return new ServerRequest(self::makePsrServerRequestCreator()->fromGlobals());
    }

    public static function makePdo(Container $container): PDO
    {
        $defaultOptions = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        /** @param array{dsn: string, username: ?string, password: ?string, options: ?array<int, bool|int>} $constructorArguments */
        $constructorArguments = $container->get(ConfigRepository::class)->get('database.default', []);

        $constructorArguments['options'] = array_merge($defaultOptions, $constructorArguments['options'] ?? []);

        return new PDO(...$constructorArguments); // @phpstan-ignore-line (Missing parameter $dsn (string) in call to PDO constructor.)
    }

    public static function makeRedis(Container $container): Redis
    {
        $redis = new Redis();
        /** @var array<string, string> $connectionInfo */
        $connectionInfo = $container->get(ConfigRepository::class)->get('app.phpredis');

        $redis->auth($connectionInfo['auth']);
        unset($connectionInfo['auth']);

        $redis->pconnect(...$connectionInfo);

        return $redis;
    }

    public static function makeErrorLogger(Container $container): ResourceLogger
    {
        $configInfo = $container->get(ConfigRepository::class)->get('lggers.error');

        $day = date('Y-m-d');
        return new ResourceLogger("writable/logs/error.$day.log");

        // + have logic to delete other days logger files
    }
}

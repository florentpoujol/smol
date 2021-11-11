<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Container;

use FlorentPoujol\SmolFramework\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Cache\InMemoryCache;
use FlorentPoujol\SmolFramework\Log\DailyFileLogger;
use FlorentPoujol\SmolFramework\Psr15RequestHandler;
use Nyholm\Psr7\Request;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Redis;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * @template ServiceType
 */
final class Container implements ContainerInterface
{
    /** @var array<class-string<ServiceType>, callable|string|array> */
    private array $factories = [
        ServerRequestInterface::class => [ServiceFactories::class, 'makeServerRequest'],
        ResponseInterface::class => [ServiceFactories::class, 'makeResponse'],
        RequestHandlerInterface::class => Psr15RequestHandler::class,
        RequestInterface::class => Request::class, // client request
        LoggerInterface::class => DailyFileLogger::class,
        PDO::class => [ServiceFactories::class, 'makePdo'],
        Redis::class => [ServiceFactories::class, 'makeRedis'],
        CacheInterface::class => InMemoryCache::class,
    ];

    /**
     * Values cached by get().
     * Typically, object instances but may be any values returned by closures or found in services.
     *
     * @var array<class-string<ServiceType>, null|object<ServiceType>
     */
    private array $instances = [];

    /**
     * @var array<string|class-string, mixed> match
     */
    private array $parameters = [];

    /**
     * @param array<class-string, callable|string|array> $factories
     * @param array<string, mixed>                       $parameters
     */
    public function __construct(array $factories = null, array $parameters = null)
    {
        $this->instances[ContainerInterface::class] = $this;

        if ($factories !== null) {
            $this->factories = $factories;
        }

        if ($parameters !== null) {
            $this->parameters = $parameters;
        }
    }

    public function setParameter(string $name, mixed $value): void
    {
        $this->parameters[$name] = $value;
    }

    public function getParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * @param class-string<ServiceType>                        $serviceName
     * @param callable|string|array<string|object|int, string> $factory     Instance factory, service alias, or constructor arguments
     */
    public function setFactory(string $serviceName, callable|string|array $factory): void
    {
        $this->factories[$serviceName] = $factory;
    }

    /**
     * @param class-string<ServiceType> $serviceName
     * @param ServiceType               $instance
     */
    public function setInstance(string $serviceName, object $instance): void
    {
        $this->instances[$serviceName] = $instance;
    }

    /**
     * @param class-string<ServiceType> $id
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * @param class-string<ServiceType> $id
     *
     * @return ServiceType
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $value = $this->make($id);
        $this->instances[$id] = $value;

        if ($value === null) {
            throw new NotFoundException("'$id' couldn't be resolved");
        }

        return $value;
    }

    /**
     * Returns a new instance of object or call again a callable.
     *
     * @param array<string, mixed> $extraArguments
     *
     * @throws \FlorentPoujol\SmolFramework\Container\NotFoundException when a service name couldn't be resolved
     */
    public function make(string $serviceName, array $extraArguments = []): ?object
    {
        if (! isset($this->factories[$serviceName])) {
            if (class_exists($serviceName)) {
                return $this->createObject($serviceName, $extraArguments);
            }

            throw new ContainerException("Factory for service '$serviceName' not found.");
        }

        $value = $this->factories[$serviceName];

        // check if is a callable first, because callables can be string or array, too
        if (is_callable($value)) {
            return $value($this, $extraArguments);
        }

        if (is_array($value)) {
            // $serviceName is a concrete class name, $value is class constructor description
            return $this->createObject($serviceName, array_merge($value, $extraArguments)); // @phpstan-ignore-line
        }

        // typically, $serviceName is an interface or alias to other service

        // resolve alias as deep as possible
        $valueChanged = false;
        while (isset($this->factories[$value])) {
            $value = $this->factories[$value];

            if (! is_string($value)) {
                throw new ContainerException();
            }
            $valueChanged = true;
        }

        if ($valueChanged) {
            return $this->make($value, $extraArguments);
        }

        if (class_exists($value)) {
            return $this->createObject($value, $extraArguments);
        }

        throw new ContainerException("Service '$serviceName' resolve to a string value '$value' that is neither another known service nor a class name.");
    }

    /**
     * @param class-string         $classFqcn
     * @param array<string, mixed> $extraArguments
     *
     * @throws \Exception
     */
    private function createObject(string $classFqcn, array $extraArguments = []): ?object
    {
        $class = new \ReflectionClass($classFqcn);
        $constructor = $class->getConstructor();

        if ($constructor === null) {
            return new $classFqcn();
        }

        $args = [];
        $params = $constructor->getParameters();
        foreach ($params as $param) {
            $paramName = $param->getName();
            $isParamMandatory = ! $param->isOptional();

            $typeName = '';
            $typeIsBuiltin = false;
            $typeIsNullable = false;
            $type = $param->getType();

            if ($type instanceof ReflectionUnionType) {
                throw new ContainerException("Can't autowire argument '$paramName' of service '$classFqcn' because it has union type.");
            }
            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $typeIsBuiltin = $type->isBuiltin();
                $typeIsNullable = $type->allowsNull();
            } // else is null (no type specified)

            if (isset($extraArguments[$paramName])) {
                $value = $extraArguments[$paramName];

                if (is_string($value)) {
                    if ($value[0] === '@') { // service reference
                        $value = $this->get(str_replace('@', '', $value));
                    } elseif ($value[0] === '%') { // parameter reference
                        $value = $this->parameters[str_replace('%', '', $value)];
                    }
                }

                $args[] = $value;

                continue;
            }

            if ($typeName === '' || $typeIsBuiltin) {
                // no type hint or not an object, so try to get a value from the parameters
                $value = $this->parameters[$paramName] ?? null;

                if ($value === null && ! $typeIsNullable && $isParamMandatory) {
                    throw new ContainerException("Constructor argument '$paramName' for class '$classFqcn' has no type-hint or is of built-in" . " type '$typeName' but value is not manually specified in the container or the extra arguments.");
                }

                $args[] = $value;

                continue;
            }

            // param is a class or interface (internal or userland)
            if (interface_exists($typeName) && ! $this->has($typeName)) {
                throw new ContainerException("Constructor argument '$paramName' for class '$classFqcn' is type-hinted with the interface " . "'$typeName' but no alias for it is set in the container.");
            }

            $instance = null;
            if ($isParamMandatory) {
                try {
                    $instance = $this->get($typeName);
                } catch (ContainerException $exception) {
                    throw new ContainerException("Constructor argument '$paramName' for class '$classFqcn' has type '$typeName' " . " but the container don't know how to resolve it.");
                }
            }

            $args[] = $instance;
        }

        return new $classFqcn(...$args);
    }
}

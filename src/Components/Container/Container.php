<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Container;

use FlorentPoujol\SmolFramework\Components\Cache\ArrayCache;
use FlorentPoujol\SmolFramework\Components\Cache\CacheInterface;
use FlorentPoujol\SmolFramework\Components\Log\DailyFileLogger;
use FlorentPoujol\SmolFramework\Framework\Http\Psr15RequestHandler;
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
 * @template ServiceType of object
 */
final class Container implements ContainerInterface
{
    /** @var array<class-string<ServiceType>, callable|string> */
    private array $bindings = [];

    /** @var array<class-string<ServiceType>, callable|string> */
    private array $singletonBindings = [
        ServerRequestInterface::class => [ServiceFactories::class, 'makeServerRequest'],
        ResponseInterface::class => [ServiceFactories::class, 'makeResponse'],
        RequestHandlerInterface::class => Psr15RequestHandler::class,
        RequestInterface::class => Request::class, // client request
        LoggerInterface::class => DailyFileLogger::class,
        PDO::class => [ServiceFactories::class, 'makePdo'],
        Redis::class => [ServiceFactories::class, 'makeRedis'],
        CacheInterface::class => ArrayCache::class,
    ];

    /**
     * Values cached by get().
     * Typically, object instances but may be any values returned by closures or found in services.
     *
     * @var array<class-string<ServiceType>, object<ServiceType>
     */
    private array $instances = [];

    /**
     * @var array<string|class-string, mixed> match
     */
    private array $parameters = [];

    public function __construct()
    {
        $this->instances[ContainerInterface::class] = $this;
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
     * @param class-string<ServiceType> $abstract
     * @param callable|class-string     $concreteOrFactory
     */
    public function bind(string $abstract, callable|string $concreteOrFactory, bool $isSingleton = true): void
    {
        if ($isSingleton) {
            $this->singletonBindings[$abstract] = $concreteOrFactory;
        } else {
            $this->bindings[$abstract] = $concreteOrFactory;
        }
    }

    /**
     * @param class-string<ServiceType> $abstract
     * @param ServiceType               $instance
     */
    public function setInstance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * @param class-string<ServiceType> $id
     */
    public function has(string $id): bool
    {
        return
               isset($this->instances[$id])
            || isset($this->singletonBindings[$id])
            || isset($this->bindings[$id]);
    }

    /**
     * @param class-string<ServiceType> $id
     *
     * @return object<ServiceType>
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $concrete = $this->make($id);
        if ($concrete === null) {
            throw new NotFoundException("'$id' couldn't be resolved");
        }

        if (isset($this->singletonBindings[$id])) {
            $this->instances[$id] = $concrete;
            $this->instances[get_class($concrete)] = $concrete;
        }

        return $concrete;
    }

    /**
     * Returns a new instance of object or call again a callable.
     *
     * @param array<string, mixed> $extraArguments
     *
     * @return null|object<ServiceType>
     *
     * @throws \FlorentPoujol\SmolFramework\Components\Container\NotFoundException when a service name couldn't be resolved
     */
    public function make(string $abstract, array $extraArguments = []): ?object
    {
        if (! isset($this->singletonBindings[$abstract], $this->bindings[$abstract])) {
            if (class_exists($abstract)) {
                return $this->createObject($abstract, $extraArguments);
            }

            throw new NotFoundException("Factory or concrete class FQCN for abstract '$abstract' not found.");
        }

        $bindings = array_merge($this->singletonBindings, $this->bindings);

        $value = $bindings[$abstract];

        if (is_callable($value)) {
            return $value($this, $extraArguments);
        }

        // $value is a concrete class, which may also be and alias to other service

        // resolve alias as deep as possible
        while (isset($bindings[$value])) {
            $value = $bindings[$value];

            if (is_callable($value)) {
                return $value($this, $extraArguments);
            }
        }

        if (class_exists($value)) {
            return $this->createObject($value, $extraArguments);
        }

        throw new NotFoundException("Service '$abstract' resolve to a string value '$value' that is neither another known service nor a class name.");
    }

    /**
     * @param class-string<ServiceType> $classFqcn
     * @param array<string, mixed>      $extraArguments
     *
     * @return object<ServiceType>
     */
    private function createObject(string $classFqcn, array $extraArguments = []): ?object
    {
        $reflectionClass = new \ReflectionClass($classFqcn);
        $reflectionConstructor = $reflectionClass->getConstructor();

        if ($reflectionConstructor === null) {
            return new $classFqcn();
        }

        $args = [];
        $reflectionParameters = $reflectionConstructor->getParameters();
        foreach ($reflectionParameters as $reflectionParameter) {
            $paramName = $reflectionParameter->getName();

            if (isset($extraArguments[$paramName])) {
                $value = $extraArguments[$paramName];

                if (is_string($value)) {
                    if ($value[0] === '@') { // service reference
                        $value = $this->get(str_replace('@', '', $value));
                    } elseif ($value[0] === '%') { // parameter reference
                        $value = $this->parameters[str_replace('%', '', $value)];
                    }
                }

                $args[$paramName] = $value;

                continue;
            }

            $paramIsMandatory = ! $reflectionParameter->isOptional();

            $typeName = null;
            $typeIsBuiltin = false;
            $typeIsNullable = false;
            $reflectionType = $reflectionParameter->getType();

            if ($reflectionType instanceof ReflectionUnionType) {
                throw new ContainerException("Can't autowire argument '$paramName' of service '$classFqcn' because it has union type.");
            }

            if ($reflectionType instanceof ReflectionNamedType) {
                $typeName = $reflectionType->getName();
                $typeIsBuiltin = $reflectionType->isBuiltin();
                $typeIsNullable = $reflectionType->allowsNull();
            } // else $reflectionType === null (no type specified)

            if ($typeName === null || $typeIsBuiltin) {
                // no type hint or not an object, so try to get a value from the parameters
                $hasParameter = isset($this->parameters[$paramName]);
                $value = $this->parameters[$paramName] ?? null;

                if ($hasParameter && $value === null && ! $typeIsNullable) {
                    throw new ContainerException("Constructor argument '$paramName' for class '$classFqcn' is not nullable but a null value was specified through parameters");
                }

                if (! $hasParameter && $paramIsMandatory) {
                    $message = "Constructor argument '$paramName' for class '$classFqcn' has no type-hint or is of built-in" .
                        " type '$typeName' but value is not manually specified in the container or the extra arguments.";

                    throw new ContainerException($message);
                }

                if (! $hasParameter) {
                    // because of the condition above, we know the param is always optional here
                    continue;
                }

                $args[$paramName] = $value;

                continue;
            }

            // param is a class or interface (internal or userland)
            if (interface_exists($typeName) && ! $this->has($typeName)) {
                $msg = "Constructor argument '$paramName' for class '$classFqcn' is type-hinted with the interface " .
                    "'$typeName' but no alias for it is set in the container.";

                throw new ContainerException($msg);
            }

            $instance = null;
            if ($paramIsMandatory) {
                try {
                    $instance = $this->get($typeName);
                } catch (ContainerException $exception) {
                    $msg = "Constructor argument '$paramName' for class '$classFqcn' has type '$typeName' " .
                        " but the container don't know how to resolve it.";

                    throw new ContainerException($msg);
                }
            }

            $args[$paramName] = $instance;
        }

        return new $classFqcn(...$args);
    }
}

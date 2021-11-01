<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

use Exception;
use Nyholm\Psr7\Request;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

final class Container implements ContainerInterface
{
    /** @var array<string|class-string, callable|string|array> */
    private array $factories = [
        ServerRequestInterface::class => [ServiceFactories::class, 'makeServerRequest'],
        ResponseInterface::class => [ServiceFactories::class, 'makeResponse'],
        RequestHandlerInterface::class => Psr15RequestHandler::class,
        RequestInterface::class => Request::class,
        LoggerInterface::class => [ServiceFactories::class, 'makeLogger'],
];

    /**
     * Values cached by get().
     * Typically, object instances but may be any values returned by closures or found in services.
     *
     * @var array<string|class-string, null|object>
     */
    private array $instances = [];

    /**
     * @var array<string|class-string, mixed>
     */
    private array $parameters = [];

    /**
     * @param array<string, string> $services
     * @param array<string, mixed>  $parameters
     */
    public function __construct(array $services = null, array $parameters = null)
    {
        if ($services !== null) {
            $this->factories = $services;
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
     * @param class-string|string                              $serviceName
     * @param callable|string|array<string|object|int, string> $factory     Instance factory, service alias, or constructor arguments
     */
    public function setFactory(string $serviceName, callable|string|array $factory): void
    {
        $this->factories[$serviceName] = $factory;
    }

    /**
     * @param class-string|string $serviceName
     */
    public function setInstance(string $serviceName, object $instance): void
    {
        $this->instances[$serviceName] = $instance;
    }

    /**
     * @param class-string|string $id
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * @param class-string|string $id
     */
    public function get(string $id): ?object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $value = $this->make($id);
        $this->instances[$id] = $value;

        return $value;
    }

    /**
     * Returns a new instance of object or call again a callable.
     *
     * @throws \Exception when a service name couldn't be resolved
     */
    public function make(string $serviceName): ?object
    {
        if (! isset($this->factories[$serviceName])) {
            if (class_exists($serviceName)) {
                return $this->createObject($serviceName);
            }

            throw new Exception("Service '$serviceName' not found.");
        }

        $value = $this->factories[$serviceName];

        // check if is a callable first, because callables can be string or array, too
        if (is_callable($value)) {
            return $value($this);
        }

        if (is_array($value)) {
            // $serviceName is a concrete class name, $value is class constructor description
            return $this->createObject($serviceName, $value); // @phpstan-ignore-line
        }

        // $serviceName is a class name or alias to other service

        // resolve alias as deep as possible
        $valueChanged = false;
        while (isset($this->factories[$value])) {
            $value = $this->factories[$value];

            if (! is_string($value)) {
                throw new Exception();
            }
            $valueChanged = true;
        }

        if ($valueChanged) {
            return $this->make($value);
        }

        if (class_exists($value)) {
            return $this->createObject($value);
        }

        throw new UnexpectedValueException("Service '$serviceName' resolve to a string value '$value' that is neither another known service nor a class name.");
    }

    /**
     * @param class-string         $classFqcn
     * @param array<string, mixed> $manualArguments
     *
     * @throws \Exception
     */
    private function createObject(string $classFqcn, array $manualArguments = []): ?object
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
            $type = $param->getType();
            if ($type !== null) {
                $typeName = (string) $type;
                $typeIsBuiltin = $type->isBuiltin(); // @phpstan-ignore-line (doesn't know isBuiltin() ?)
            }

            if (isset($manualArguments[$paramName])) {
                $value = $manualArguments[$paramName];

                if (is_string($value)) {
                    if ($value[0] === '@') { // service reference
                        $value = $this->make(str_replace('@', '', $value));
                    // shoudn't make() be called here when createObject() is called from make() ?
                        // could allow user to prepend service name with @@ instead of @ to use either get or make
                    } elseif ($value[0] === '%') { // parameter reference
                        $value = $this->getParameter(str_replace('%', '', $value));
                    }
                }

                $args[] = $value;

                continue;
            }

            if ($typeName === '' || $typeIsBuiltin) {
                // no type hint or not an object
                if ($isParamMandatory) {
                    throw new Exception("Constructor argument '$paramName' for class '$classFqcn' has no type-hint or is of built-in type '$typeName' but value is not manually specified in the container.");
                }

                continue;
            }

            // param is a class or interface (internal or userland)
            if (interface_exists($typeName) && ! $this->has($typeName)) {
                throw new Exception("Constructor argument '$paramName' for class '$classFqcn' is type-hinted with the interface '$typeName' but no alias for it is set in the container.");
            }

            $object = null;

            if ($isParamMandatory) {
                try {
                    $object = $this->get($typeName);
                } catch (Exception $exception) {
                    throw new Exception("Constructor argument '$paramName' for class '$classFqcn' has type '$typeName' but the container don't know how to resolve it.");
                }
            }

            $args[] = $object;
        }

        return new $classFqcn(...$args);
    }
}

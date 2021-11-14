<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Container\Container;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Framework
{
    private static self $instance;

    /**
     * @param array<string, null|string> $config
     */
    public static function make(array $config): self
    {
        return new self($config);
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * @param array<string, null|string> $config
     */
    public function __construct(array $config)
    {
        self::$instance = $this;

        $this->config = array_merge($this->config, $config);
    }

    // --------------------------------------------------
    // config stuffs

    /** @var array<string, null|string> */
    private array $config = [
        'baseAppPath' => __DIR__,
        'container_fqcn' => Container::class,
        'environment' => 'production',
    ];

    public function getConfig(string $key, string $default = null): ?string
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(string $key, ?string $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    // --------------------------------------------------
    // boot stuffs

    private Container $container;

    public function getContainer(): Container
    {
        return $this->container;
    }

    private bool $booted = false;

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->container = new $this->config['container_fqcn']();
        $this->container->setInstance(self::class, $this);

        foreach ($this->config as $key => $value) {
            $this->container->setParameter($key, $value);
        }

        $this->booted = true;
    }

    // --------------------------------------------------
    // HTTP request stuffs

    public function handleHttpRequest(): void
    {
        try {
            /** @var \FlorentPoujol\SmolFramework\Router $router */
            $router = $this->container->get(Router::class);
            $route = $router->resolveRoute();

            if ($route === null) {
                $this->sendResponseToClient(
                    new Response(404, body: $_SERVER['REQUEST_URI'] . ' not found')
                );
            }

            $this->container->setInstance(Route::class, $route);

            if ($route->isRedirect()) {
                $action = $route->getAction();
                assert(is_string($action));

                $status = str_starts_with($action, 'redirect-permanent:') ? 301 : 302;
                $location = str_replace(['redirect:', 'redirect-permanent:'], '', $action);

                $this->sendResponseToClient(new Response($status, ['Location' => $location])); // app exit here
            }

            if ($route->hasPsr15Middleware()) {
                $this->handleRequestThroughPsr15Middleware(); // app exit here
            }

            $hasMiddleware = $route->getMiddleware() !== [];
            if ($hasMiddleware) {
                $this->sendRequestThroughMiddleware($route); // app *may* exit here
            }

            $response = $this->callRouteAction($route);

            if ($hasMiddleware) {
                $response = $this->sendResponseThroughMiddleware($response, $route);
            }
        } catch (\Throwable $exception) {
            /** @var \FlorentPoujol\SmolFramework\ExceptionHandler $exceptionHandler */
            $exceptionHandler = $this->container->get(ExceptionHandler::class);

            $exceptionHandler->report($exception);

            $response = $exceptionHandler->render($exception);
        }

        $this->sendResponseToClient($response);
    }

    /**
     * @return never-return
     */
    public function handleRequestThroughPsr15Middleware(): void
    {
        /** @var \Psr\Http\Server\RequestHandlerInterface $handler */
        $handler = $this->container->get(RequestHandlerInterface::class);

        /** @var \Psr\Http\Message\ServerRequestInterface $serverRequest */
        $serverRequest = $this->container->get(ServerRequestInterface::class);

        $response = $handler->handle($serverRequest); // see in the handle method for explanation as to why this single like does everything and return the final response, whatever happens in between

        $this->sendResponseToClient($response); // app exit here
    }

    /**
     * @return never-return|void
     */
    private function sendRequestThroughMiddleware(Route $route): void
    {
        $this->responseMiddleware = [];
        $serverRequest = $this->container->get(ServerRequestInterface::class);

        foreach ($route->getMiddleware() as $requestMiddleware) {
            $responseMiddleware = null;

            if (! is_callable($requestMiddleware)) {
                $instance = $this->container->get($requestMiddleware);

                $responseMiddleware = [$instance, 'handleResponse'];
                if (! is_callable($responseMiddleware)) {
                    $responseMiddleware = null;
                }

                $requestMiddleware = [$instance, 'handleRequest'];
                if (! is_callable($requestMiddleware)) {
                    if ($responseMiddleware !== null) {
                        $this->responseMiddleware[] = $responseMiddleware;
                    }

                    continue;
                }
            }

            $response = $requestMiddleware($serverRequest, $route);

            if ($response !== null) {
                if ($this->responseMiddleware !== []) {
                    $response = $this->sendResponseThroughMiddleware($response, $route);
                }

                $this->sendResponseToClient($response); // app exit here
            }

            $this->responseMiddleware[] = $responseMiddleware ?? $requestMiddleware;
        }
    }

    /** @var array<callable> */
    private array $responseMiddleware = [];

    private function sendResponseThroughMiddleware(ResponseInterface $response, Route $route): ResponseInterface
    {
        $middleware = array_reverse($this->responseMiddleware);

        foreach ($middleware as $responseMiddleware) {
            $response = $responseMiddleware($response, $route) ?? $response;
        }

        return $response;
    }

    public function callRouteAction(Route $route): ResponseInterface
    {
        /** @var callable|string $action A callable or an "at" string : "Controller@method" */
        $action = $route->getAction();

        if (! is_callable($action)) {
            // "Controller@method"
            [$fqcn, $method] = explode('@', $action, 2);
            $action = [$this->container->get($fqcn), $method];
            assert(is_callable($action));
        }

        return $action(
            ...$route->getActionArguments() // this unpacks an assoc array and make use of named arguments to inject the proper value taken from the URI segments to the correct argument
        );
    }

    /**
     * @return never-return
     */
    private function sendResponseToClient(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        /**
         * @var array<string> $values
         */
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }

        $response->getBody()->rewind();
        echo $response->getBody()->getContents();

        exit(0);
    }
}

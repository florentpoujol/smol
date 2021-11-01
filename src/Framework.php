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
     * @param  array<string, string> $config
     */
    public static function make(array $config): self
    {
        return new self($config);
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function __construct(array $config)
    {
        self::$instance = $this;

        $this->config = array_merge($this->config, $config);
    }

    // --------------------------------------------------
    // config stuffs

    /** @var array<string, string> */
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
        $this->boot();

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

                $this->sendResponseToClient(new Response($status, ['Location' => $location]));
            }

            if ($route->hasPsr15Middleware()) {
                $this->handleRequestThroughPsr15Middleware(); // code exit here
            }

            $this->sendRequestThroughMiddleware($route); // code may exit here

            $response = $this->callRouteAction($route);

            $response = $this->sendResponseThroughMiddleware($response, $route);
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

        $response = $handler->handle($serverRequest);

        $this->sendResponseToClient($response);
    }

    /**
     * @return never-return|void
     */
    private function sendRequestThroughMiddleware(Route $route): void
    {
        $middleware = $route->getMiddleware();
        if ($middleware === []) {
            return;
        }

        $this->responseMiddleware = [];
        $serverRequest = $this->container->get(ServerRequestInterface::class);

        foreach ($middleware as $_middleware) {
            if (! is_callable($_middleware)) {
                // "Controller@method"
                [$fqcn, $method] = explode('@', $_middleware, 2);
                $_middleware = [$this->container->get($fqcn), $method];
                assert(is_callable($_middleware));
            }

            $response = $_middleware($serverRequest, $route);
            $this->responseMiddleware[] = $_middleware;

            if ($response !== null) {
                $response = $this->sendResponseThroughMiddleware($response, $route);

                $this->sendResponseToClient($response); // code exit here
            }
        }
    }

    /** @var array<callable> */
    private array $responseMiddleware = [];

    private function sendResponseThroughMiddleware(ResponseInterface $response, Route $route): ResponseInterface
    {
        if ($this->responseMiddleware === []) {
            return $response;
        }

        $middleware = array_reverse($this->responseMiddleware);

        foreach ($middleware as $_middleware) {
            $response = $_middleware($response, $route) ?? $response;
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

<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use FlorentPoujol\SimplePhpFramework\Translations\TranslationsRepository;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Framework
{
    private static ?self $instance = null;

    public static function make(string $baseDirectory): self
    {
        return new self($baseDirectory);
    }

    public static function getInstance(): self
    {
        assert(self::$instance !== null);

        return self::$instance;
    }

    private string $baseDirectory;

    public function __construct(string $baseDirectory)
    {
        assert(self::$instance === null);
        self::$instance = $this;

        $realpath = realpath($baseDirectory);
        $this->baseDirectory = is_string($realpath) ? $realpath : $baseDirectory;

        $this->boot();
    }

    /** @var class-string<\FlorentPoujol\SimplePhpFramework\Container> */
    private string $containerFqcn = Container::class;
    private Container $container;

    /**
     * @param class-string<\FlorentPoujol\SimplePhpFramework\Container> $containerFqcn
     */
    public function setContainerFqcn(string $containerFqcn): self
    {
        $this->containerFqcn = $containerFqcn;

        return $this;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    private function boot(): void
    {
        $this->container = new $this->containerFqcn();

        $this->container->setInstance(self::class, $this);

        $this->container->setFactory(Router::class, ['baseAppPath' => $this->baseDirectory]);
        $this->container->setFactory(ConfigRepository::class, ['baseAppPath' => $this->baseDirectory]);
        $this->container->setFactory(TranslationsRepository::class, ['baseAppPath' => $this->baseDirectory]);
        $this->container->setFactory(ViewRenderer::class, ['baseAppPath' => $this->baseDirectory]);
    }

    public function handleHttpRequest(): void
    {
        /** @var \FlorentPoujol\SimplePhpFramework\Router $router */
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
            $this->handleRequestThroughPSR15Middleware(); // code exit here
        }

        $this->sendRequestThroughMiddleware($route); // code may exit here

        $response = $this->callRouteAction($route);

        $response = $this->sendResponseThroughMiddleware($response, $route);

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

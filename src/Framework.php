<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Framework
{
    public static function make(string $baseDirectory): self
    {
        return new self($baseDirectory);
    }

    private string $baseDirectory;

    public function __construct(string $baseDirectory)
    {
        $realpath = realpath($baseDirectory);
        $this->baseDirectory = is_string($realpath) ? $realpath : $baseDirectory;

        $this->init();
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

    private function init(): void
    {
        $this->container = new $this->containerFqcn();
        $this->container->setFactory(Router::class, ['baseAppPath' => $this->baseDirectory]);
        $this->container->setFactory(ConfigRepository::class, ['baseAppPath' => $this->baseDirectory]);
    }

    public function handleHttpRequest(): void
    {
        /** @var \FlorentPoujol\SimplePhpFramework\Router $router */
        $router = $this->container->get(Router::class);
        $route = $router->resolveRoute();

        if ($route === null) {
            http_response_code(404);
            echo $_SERVER['REQUEST_URI'] . ' not found';

            exit(0);
        }

        $this->sendRequestThroughMiddleware($route); // may exit here

        $response = $this->callRouteAction($route);

        $response = $this->sendResponseThroughMiddleware($response);

        $this->sendResponseToClient($response);
    }

    /**
     * @return void|never-return
     */
    private function sendRequestThroughMiddleware(Route $route): void
    {
        $middleware = $route->getMiddleware();
        if (count($middleware) === 0) {
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

            $response = $_middleware($serverRequest, $this);
            $this->responseMiddleware[] = $_middleware;

            if ($response !== null) {
                $response = $this->sendResponseThroughMiddleware($response);

                $this->sendResponseToClient($response);
            }
        }
    }

    /** @var array<callable> */
    private array $responseMiddleware = [];

    private function sendResponseThroughMiddleware(ResponseInterface $response): ResponseInterface
    {
        $middleware = array_reverse($this->responseMiddleware);

        foreach ($middleware as $_middleware) {
            $response = $_middleware($response, $this) ?? $response;
        }

        return $response;
    }

    private function callRouteAction(Route $route): ResponseInterface
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

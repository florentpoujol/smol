<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use FlorentPoujol\SimplePhpFramework\Translations\TranslationsRepository;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
        $this->container->setFactory(TranslationsRepository::class, ['baseAppPath' => $this->baseDirectory]);
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

        if ($route->isRedirect()) {
            $action = $route->getAction();
            assert(is_string($action));

            $status = str_starts_with($action, 'redirect-permanent:') ? 301 : 302;
            $location = str_replace(['redirect:', 'redirect-permanent:'], '', $action);

            $this->sendResponseToClient(new Response($status, ['Location' => $location]));
        }

        $this->sendRequestThroughMiddleware($route); // code may exit here

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

                $this->sendResponseToClient($response); // code exit here
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

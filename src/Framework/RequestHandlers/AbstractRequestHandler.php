<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\RequestHandlers;

use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Framework\Exceptions\ExceptionHandler;
use FlorentPoujol\SmolFramework\Framework\Http\Route;
use FlorentPoujol\SmolFramework\Framework\Http\Router;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractRequestHandler
{
    public function __construct(
        private Container $container
    ) {
    }

    abstract protected function getHttpMethod(): string;
    abstract protected function getUri(): string;

    // --------------------------------------------------
    // HTTP request stuffs

    public function handle(): void
    {
        try {
            /** @var \FlorentPoujol\SmolFramework\Framework\Http\Router $router */
            $router = $this->container->get(Router::class);
            $route = $router->resolveRoute($this->getHttpMethod(), $this->getUri());

            if ($route === null) {
                $this->sendResponseToClient(
                    new Response(404, body: $this->getUri() . ' not found')
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
            /** @var \FlorentPoujol\SmolFramework\Framework\Exceptions\ExceptionHandler $exceptionHandler */
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
        /** @var RequestHandlerInterface $handler */
        $handler = $this->container->get(RequestHandlerInterface::class);

        /** @var ServerRequestInterface $serverRequest */
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
                        array_unshift($this->responseMiddleware, $responseMiddleware);
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

            array_unshift($this->responseMiddleware, $responseMiddleware ?? $requestMiddleware);
        }
    }

    /** @var array<callable> */
    private array $responseMiddleware = [];

    private function sendResponseThroughMiddleware(ResponseInterface $response, Route $route): ResponseInterface
    {
        foreach ($this->responseMiddleware as $responseMiddleware) {
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
    abstract public function sendResponseToClient(ResponseInterface $response): void;
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Infrastructure;

use FlorentPoujol\Smol\Components\Container\Container;
use FlorentPoujol\Smol\Infrastructure\Exceptions\ExceptionHandler;
use FlorentPoujol\Smol\Infrastructure\Http\Route;
use FlorentPoujol\Smol\Infrastructure\Http\Router;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;
use Throwable;

final class HttpKernel
{
    public function __construct(
        private Container $container,
    ) {
    }

    public function handle(ServerRequestInterface $serverRequest): ResponseInterface
    {
        try {
            /** @var Router $router */
            $router = $this->container->get(Router::class);
            $route = $router->resolveRoute($serverRequest->getMethod(), $serverRequest->getUri()->getPath());

            if ($route === null) {
                return new Response(404, body: $serverRequest->getUri()->getPath() . ' not found');
            }

            $this->container->setInstance(Route::class, $route);

            if ($route->isRedirect()) {
                $action = $route->getAction();
                assert(is_string($action));

                $status = str_starts_with($action, 'redirect-permanent:') ? 301 : 302;
                $location = str_replace(['redirect:', 'redirect-permanent:'], '', $action);

                return new Response($status, ['Location' => $location]);
            }

            if ($route->hasPsr15Middleware()) {
                return $this->handleRequestThroughPsr15Middleware();
            }

            $hasMiddleware = $route->getMiddleware() !== [];
            if ($hasMiddleware) {
                $response = $this->sendRequestThroughMiddleware($route);
                if ($response !== null) {
                    return $response;
                }
            }

            $response = $this->callRouteAction($route);

            if ($hasMiddleware) {
                $response = $this->sendResponseThroughMiddleware($response, $route);
            }
        } catch (Throwable $exception) {
            /** @var ExceptionHandler $exceptionHandler */
            $exceptionHandler = $this->container->get(ExceptionHandler::class);

            $exceptionHandler->report($exception);

            $response = $exceptionHandler->render($exception);
        }

        return $response;
    }

    public function handleRequestThroughPsr15Middleware(): ResponseInterface
    {
        /** @var PsrRequestHandlerInterface $handler */
        $handler = $this->container->get(PsrRequestHandlerInterface::class);

        /** @var ServerRequestInterface $serverRequest */
        $serverRequest = $this->container->get(ServerRequestInterface::class);

        return $handler->handle($serverRequest); // see in the handle method for explanation as to why this single line does everything and return the final response, whatever happens in between
    }

    private function sendRequestThroughMiddleware(Route $route): ?ResponseInterface
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

                return $response;
            }

            array_unshift($this->responseMiddleware, $responseMiddleware ?? $requestMiddleware);
        }

        return null;
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

    public function sendResponse(ResponseInterface $response): void
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

        $body = $response->getBody();
        $body->rewind();

        echo $body->getContents();

        $body->close();
    }
}

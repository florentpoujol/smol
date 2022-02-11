<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Infrastructure\Http;

use FlorentPoujol\SmolFramework\Infrastructure\Framework;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Psr15RequestHandler implements RequestHandlerInterface
{
    /** @var array<class-string<\Psr\Http\Server\MiddlewareInterface>> */
    private array $middleware;

    public function __construct(
        private Route $route,
        private Framework $framework,
    ) {
        $this->middleware = $route->getMiddleware(); // @phpstan-ignore-line
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var class-string<\Psr\Http\Server\MiddlewareInterface> $fqcn */
        $fqcn = array_shift($this->middleware);

        if ($fqcn !== null) { // @phpstan-ignore-line (thinks array_shift above cannot return null)
            /** @var \Psr\Http\Server\MiddlewareInterface $instance */
            $instance = $this->framework->getContainer()->get($fqcn);

            return $instance->process($request, $this);

            // The trick here is that we are passing this handler instance to all middleware.
            // So this method will be called multiple times, each time removing a middleware from the stack.

            // If a middleware returns a response without passing the request to the handler
            // the code below never gets called and the response naturally bubble up
            // the stack of middleware that have run, up to Framework::handleRequestThroughPsr15Middleware().
        }

        // If we are here, we did get through all middleware.
        // It is then time to call the controller, then returning the response,
        // which will automatically pass it up the stack of middleware that have run, up to Framework::handleRequestThroughPsr15Middleware().

        // @phpstan-ignore-next-line (thinks the line is unreachable because array_shift above cannot return null)
        return $this->framework->callRouteAction($this->route);
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getFramework(): Framework
    {
        return $this->framework;
    }
}

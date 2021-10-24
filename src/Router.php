<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

final class Router
{
    /** @var array<string, array<\FlorentPoujol\SimplePhpFramework\Route>> [http method => [Route]] */
    private array $routesByMethod = [];

    /**
     * @param Route|string|array<string> $routeOrMethod
     */
    public function addRoute(Route|string|array $routeOrMethod, string $uri = null, callable $target = null): void
    {
        if (! is_object($routeOrMethod)) {
            // $route is the method(s)
            $routeOrMethod = new Route($routeOrMethod, $uri, $target); // @phpstan-ignore-line
        }

        $methods = $routeOrMethod->getMethods();

        foreach ($methods as $method) {
            $this->routesByMethod[$method] ??= [];
            $this->routesByMethod[$method][] = $routeOrMethod;
        }
    }

    public function resolveRoute(): ?Route
    {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $uri = $_SERVER['REQUEST_URI'];

        if (! isset($this->routesByMethod[$method])) {
            // no routes
            return null;
        }

        $routes = $this->routesByMethod[$method];

        // get the matched route
        foreach ($routes as $route) {
            if ($route->match($method, $uri)) {
                return $route;
            }
        }
        // to prevent looping on all routes and matching them individually against the uri (which is the slowest)
        // we could do a few things:
        // - when routes have a non-regex prefix (like "/user/{id}"), they can be segregated by it
        // - when regex is needed, we could use grouped and chunked regexes
        //   see https://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html

        return null;
    }
}

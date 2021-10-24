<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

final class Router
{
    private ?string $baseAppPath = null;

    /** @var array<string, array<string, array<\FlorentPoujol\SimplePhpFramework\Route>>> Routes instances by HTTP methods and prefixes */
    private array $routes = [
        // HTTP method => [
        //     /prefix => [
        //         route 1
        //         route 2
        //     ]
        // ]
    ];

    /** @var array<string, \FlorentPoujol\SimplePhpFramework\Route> */
    private array $routesByName = [];

    public function setBaseAppPath(string $baseAppPath): self
    {
        $this->baseAppPath = $baseAppPath;

        return $this;
    }

    public function getRouteByName(string $name): ?Route
    {
        return $this->routesByName[$name] ?? null;
    }

    public function resolveRoute(): ?Route
    {
        $this->collectRoutes();

        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        if (! isset($this->routes[$method])) {
            // no routes
            return null;
        }

        $uri = '/' . trim($_SERVER['REQUEST_URI'], ' /');

        foreach ($this->routes[$method] as $prefix => $routes) {
            if (! str_starts_with($uri, $prefix)) {
                continue;
            }

            // we found all routes which prefix match the current URI
            // now we need to find which route actually match the whole URI
            // even if there is a single route, it does not mean it match
            foreach ($routes as $route) {
                if ($route->match($uri)) {
                    return $route;
                }
            }
        }

        return null;
    }

    private function collectRoutes(): void
    {
        $routes = require $this->baseAppPath . '/routes.php';

        /** @var \FlorentPoujol\SimplePhpFramework\Route $route */
        foreach ($routes as $route) {
            if ($route->getName() !== null) {
                $this->routesByName[$route->getName()] = $route; // used for URL generation
            }

            $uri = $route->getUri();
            $prefix = $uri;
            $firstPlaceholderPos = strpos($uri, '{');
            if (is_int($firstPlaceholderPos)) {
                $prefix = substr($route->getUri(), 0, $firstPlaceholderPos);
            }

            foreach ($route->getMethods() as $method) {
                $this->routes[$method][$prefix][] = $route;
            }
        }

        foreach ($this->routes as &$routesByPrefix) { // /!\ REFERENCE
            krsort($routesByPrefix); // sort alphabetically in reverse order, so that the longest prefixes are first
        }
    }
}

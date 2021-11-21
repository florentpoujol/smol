<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\Http;

use FlorentPoujol\SmolFramework\Framework\Exceptions\SmolFrameworkException;

final class Router
{
    /** @var array<string, array<string, array<\FlorentPoujol\SmolFramework\Framework\Http\Route>>> Routes instances by HTTP methods and prefixes */
    private array $routes = [
        // HTTP method => [
        //     /prefix => [
        //         route 1
        //         route 2
        //     ]
        // ]
    ];

    /** @var array<string, \FlorentPoujol\SmolFramework\Framework\Http\Route> */
    private array $routesByName = [];

    public function __construct(
        private string $baseAppPath
    ) {
        $this->collectRoutes();
    }

    /**
     * @throws \FlorentPoujol\SmolFramework\Framework\Exceptions\SmolFrameworkException When no route with that name is found
     */
    public function getRouteByName(string $name): Route
    {
        if (isset($this->routesByName[$name])) {
            return $this->routesByName[$name];
        }

        foreach ($this->routes as $routesByPrefix) {
            foreach ($routesByPrefix as $routes) {
                foreach ($routes as $route) {
                    if ($route->getName() === $name) {
                        $this->routesByName[$name] = $route;

                        return $route;
                    }
                }
            }
        }

        throw new SmolFrameworkException("Unknown route name '$name'.");
    }

    public function resolveRoute(string $method, string $uri): ?Route
    {
        if (! isset($this->routes[$method])) {
            // no routes
            return null;
        }

        $uri = '/' . trim($uri, ' /');

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
        $cachePath = $this->baseAppPath . '/storage/frameworkCache/routes.txt';
        if (file_exists($cachePath) && is_readable($cachePath)) {
            $this->collectRoutesFromCache($cachePath);

            return;
        }

        $files = scandir($this->baseAppPath . '/routes');
        if (! is_array($files)) {
            throw new SmolFrameworkException("Count not read route folder at '$this->baseAppPath . '/routes'.");
        }

        foreach ($files as $path) {
            if (str_ends_with($path, '.')) {
                continue;
            }

            $routes = require $this->baseAppPath . '/routes/' . $path;

            /** @var \FlorentPoujol\SmolFramework\Framework\Http\Route $route */
            foreach ($routes as $route) {
                if ($route->getName() !== null) {
                    $this->routesByName[$route->getName()] = $route; // used for URL generation
                }

                $uri = $route->getUri();
                $prefix = $uri;
                $lengthUpToPlaceholderNonIncluded = strpos($uri, '{');
                if (is_int($lengthUpToPlaceholderNonIncluded)) {
                    $prefix = substr($route->getUri(), 0, $lengthUpToPlaceholderNonIncluded);
                }

                foreach ($route->getMethods() as $method) {
                    $this->routes[$method][$prefix][] = $route;
                }
            }
        }

        foreach ($this->routes as $method => $routesByPrefix) {
            krsort($routesByPrefix); // sort alphabetically in reverse order, so that the longest prefixes are first
            $this->routes[$method] = $routesByPrefix;
        }
    }

    private function collectRoutesFromCache(string $cachePath): void
    {
        $serializedRoutes = file_get_contents($cachePath);
        assert(is_string($serializedRoutes));

        $this->routes = unserialize($serializedRoutes, ['allowed_classes' => [Route::class]]);
    }

    public function cacheCollectedRoutes(): void
    {
        $path = $this->baseAppPath . '/storage/frameworkCache/routes.txt';

        file_put_contents($path, serialize($this->routes));
    }
}

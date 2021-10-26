<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

final class Router
{
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

    public function __construct(
        private string $baseAppPath
    ) {
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
                    if ($route->isRedirect()) {
                        $this->redirect($route);
                    }

                    return $route;
                }
            }
        }

        return null;
    }

    private function collectRoutes(): void
    {
        $files = scandir($this->baseAppPath . '/routes');
        assert(is_array($files));

        foreach ($files as $path) {
            if (str_ends_with($path, '.')) {
                continue;
            }

            $filename = str_replace('.php', '', $path);
            $routes = require $this->baseAppPath . '/routes/' . $path;

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
        }

        foreach ($this->routes as &$routesByPrefix) { // /!\ REFERENCE
            krsort($routesByPrefix); // sort alphabetically in reverse order, so that the longest prefixes are first
        }
    }

    /**
     * @return never-return
     */
    private function redirect(Route $route): void
    {
        $action = $route->getAction();
        assert(is_string($action));

        http_response_code(302);
        if (str_starts_with($action, 'redirect-permanent:')) {
            http_response_code(301);
        }

        $location = str_replace(['redirect:', 'redirect-permanent:'], '', $action);
        header("Location: $location");

        exit(0);
    }
}

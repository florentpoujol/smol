<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SimplePhpFramework;

use FlorentPoujol\SimplePhpFramework\Route;
use FlorentPoujol\SimplePhpFramework\Router;
use PHPUnit\Framework\TestCase;
use Tests\FlorentPoujol\SimplePhpFramework\Fixtures\Routes\TestMiddleware1;
use Tests\FlorentPoujol\SimplePhpFramework\Fixtures\Routes\TestMiddleware2;

final class RouterTest extends TestCase
{
    private Router $router;

    private function setupRouter(string $method, string $uri): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;

        $this->router = new Router(__DIR__ . '/Fixtures/Routes');
    }

    public function test_that_unknown_uri_isnt_resolved(): void
    {
        $this->setupRouter('get', '/unknown route');

        $route = $this->router->resolveRoute();
        self::assertNull($route);
    }

    public function test_route_with_wrong_method_isnt_resolved(): void
    {
        $this->setupRouter('delete', '/get/static-route');

        $route = $this->router->resolveRoute();
        self::assertNull($route);
    }

    public function test_route_is_resolved(): void
    {
        $this->setupRouter('get', '/get/static-route');

        $route = $this->router->resolveRoute();
        self::assertInstanceOf(Route::class, $route);
        assert($route !== null);
        self::assertSame(['GET'], $route->getMethods());
    }

    public function test_route_is_resolved_with_all_methods(): void
    {
        $this->setupRouter('get', '/postput/static-route');
        $route = $this->router->resolveRoute();
        self::assertNull($route);

        $this->setupRouter('post', '/postput/static-route');
        $route = $this->router->resolveRoute();
        self::assertInstanceOf(Route::class, $route);
        assert($route !== null);
        self::assertSame(['POST', 'PUT'], $route->getMethods());

        $this->setupRouter('put', '/postput/static-route');
        $route = $this->router->resolveRoute();
        self::assertInstanceOf(Route::class, $route);
    }

    public function test_placeholders_segments_are_properly_resolved(): void
    {
        // even if this route is defined after /docs/{page}, since routes are ordered by static prefixes, it is resolved first
        $this->setupRouter('get', '/docs/page');
        $route = $this->router->resolveRoute();
        self::assertInstanceOf(Route::class, $route);
        assert($route !== null);
        self::assertSame('static doc page', $route->getName());

        $this->setupRouter('get', '/docs/getting-started');
        $route = $this->router->resolveRoute();
        self::assertInstanceOf(Route::class, $route);
        assert($route !== null);
        self::assertSame('dynamic doc page', $route->getName());
        self::assertSame(['page' => 'getting-started'], $route->getActionArguments());
    }

    public function test_middleware(): void
    {
        $this->setupRouter('get', '/middleware');
        $route = $this->router->resolveRoute();
        self::assertInstanceOf(Route::class, $route);
        assert($route !== null);

        $middleware = [
            TestMiddleware1::class,
            TestMiddleware2::class,
        ];
        self::assertSame($middleware, $route->getMiddleware());
    }

    // public function test_redirect(): void
    // {
    //     $testFramework = new TestFramework(__DIR__);
    //     $this->setupRouter('get', '/redirect/302');
    //     $testFramework->getContainer()->setInstance(Router::class, $this->router);
    //
    //     $testFramework->handleHttpRequest();
    //
    //     $response = $testFramework->getResponse();
    //
    //     self::assertSame(302, $response->getStatusCode());
    //     self::assertSame('/somewhere', $response->getHeader('Location'));
    // }
}

// final class TestFramework
// {
//     private Framework $proxyInstance;
//
//     public function __construct(string $baseDirectory)
//     {
//         $this->proxyInstance = new Framework($baseDirectory);
//     }
//
//     /**
//      * @param array<mixed> $arguments
//      */
//     public function __call(string $name, array $arguments): mixed
//     {
//         return $this->proxyInstance->$name(...$arguments);
//     }
//
//     private ResponseInterface $response;
//
//     public function sendResponseToClient(ResponseInterface $response): void
//     {
//         $this->response = $response;
//     }
//
//     public function getResponse(): ResponseInterface
//     {
//         return $this->response;
//     }
// }

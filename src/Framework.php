<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

final class Framework
{
    public static function make(string $baseDirectory): self
    {
        return new self($baseDirectory);
    }

    private string $baseDirectory;

    public function __construct(string $baseDirectory)
    {
        $this->baseDirectory = $baseDirectory;
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

    private function init(): void
    {
        $this->container = new $this->containerFqcn();
        // $this->container->init();
    }

    public function handleHttpRequest(): void
    {
        // $uri = $_REQUEST['REQUEST_URI'];
        //
        // $router = new Router();
        // $route = $router->resolveRoute($uri);
        //
        // if ($route === null) {
        //     if ($router->throwOn404()) {
        //         throw new HttpException();
        //     }
        //
        //     http_response_code(404);
        //
        //     exit(0);
        // }
        //
        // $this->init();
        //
        // $serverRequest = $this->container->make(PsrServerRequest);
        //
        // $response = $this->sendRequestThroughMiddleware($serverRequest);
        // if ($response === null) {
        //     $response = $route->sendRequestToController($serverRequest);
        // }
        //
        // $this->sendResponseThroughMiddleware($response);
        //
        // $response->send();
    }
}

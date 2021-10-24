<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use Psr\Http\Message\ResponseInterface;

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
        $this->container->setFactory(Router::class, [$this->baseDirectory]);
    }

    public function handleHttpRequest(): void
    {
        $route = $this->container->get(Router::class)->resolveRoute();
        if ($route === null) {
            http_response_code(404);

            echo $_SERVER['REQUEST_URI'] . ' not found';

            exit(0);
        }

        /** @var \FlorentPoujol\SimplePhpFramework\Route $route */
        $response = $route->callControllerAction();

        $this->sendResponseToClient($response);
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

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\RequestHandlers;

use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Framework\Http\ServerRequest;
use FlorentPoujol\SmolFramework\Framework\ServiceProviderInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SingleRequestHandler extends AbstractRequestHandler implements ServiceProviderInterface
{
    protected function getHttpMethod(): string
    {
        return strtoupper($_SERVER['HTTP_METHOD']);
    }

    protected function getUri(): string
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * @return never-return
     */
    public function sendResponseToClient(ResponseInterface $response): void
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

    // --------------------------------------------------

    public function register(Container $container): void
    {
        $container->setInstance(RequestHandlerInterface::class, $this);

        $container->bind(ServerRequestInterface::class, [$this, 'makeServerRequest']);
        $container->bind(ResponseInterface::class, fn () => new Response());
        $container->bind(RequestInterface::class, Request::class); // client request
    }

    public function boot(): void
    {
        // nothing to do
    }

    public function makeServerRequest(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();

        $serverRequestFactory = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
        );

        return new ServerRequest($serverRequestFactory->fromGlobals());
    }
}

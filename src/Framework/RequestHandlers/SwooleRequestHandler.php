<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\RequestHandlers;

use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Framework\Request;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class SwooleRequestHandler extends AbstractRequestHandler
{
    public function __construct(
        private Container $container,
        private Request $request,
        private Response $response,
    ) {
    }

    // --------------------------------------------------
    // HTTP request stuffs

    public function handle(): void
    {
    }

    protected function getHttpMethod(): string
    {
        return $this->request->http_method;
    }

    protected function getUri(): string
    {
        return $this->request->uri;
    }

    /**
     * @return never-return
     */
    public function sendResponseToClient(ResponseInterface $response): void
    {
        // write stuff to the swoole response
    }
}

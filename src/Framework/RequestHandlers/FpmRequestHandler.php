<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\RequestHandlers;

use Psr\Http\Message\ResponseInterface;

final class FpmRequestHandler extends AbstractRequestHandler
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
}

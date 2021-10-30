<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework\HttpClient;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class RequestException extends AbstractHttpException implements RequestExceptionInterface
{
    private RequestInterface $request;

    /**
     * {@inheritDoc}
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function setRequest(RequestInterface $request): self
    {
        $this->request = $request;

        return $this;
    }
}

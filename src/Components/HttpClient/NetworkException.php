<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\HttpClient;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class NetworkException extends AbstractHttpException implements NetworkExceptionInterface
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

<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequest implements ServerRequestInterface
{
    public function __construct(
        private ServerRequestInterface $decoratedServerRequest,
    ) {
    }

    public function getProtocolVersion(): string
    {
        return $this->decoratedServerRequest->getProtocolVersion();
    }

    public function withProtocolVersion($version): self
    {
        $this->decoratedServerRequest->withProtocolVersion($version);

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->decoratedServerRequest->getHeaders();
    }

    public function hasHeader($name): bool
    {
        return $this->decoratedServerRequest->hasHeader($name);
    }

    public function getHeader($name): array
    {
        return $this->decoratedServerRequest->getHeader($name);
    }

    public function getHeaderLine($name): string
    {
        return $this->decoratedServerRequest->getHeaderLine($name);
    }

    public function withHeader($name, $value): self
    {
        $this->decoratedServerRequest->withHeader($name, $value);

        return $this;
    }

    public function withAddedHeader($name, $value): self
    {
        $this->decoratedServerRequest->withAddedHeader($name, $value);

        return $this;
    }

    public function withoutHeader($name): self
    {
        $this->decoratedServerRequest->withoutHeader($name);

        return $this;
    }

    public function getBody(): StreamInterface
    {
        return $this->decoratedServerRequest->getBody();
    }

    public function withBody(StreamInterface $body): self
    {
        $this->decoratedServerRequest->withBody($body);

        return $this;
    }

    public function getRequestTarget(): string
    {
        return $this->decoratedServerRequest->getRequestTarget();
    }

    public function withRequestTarget($requestTarget): self
    {
        $this->decoratedServerRequest->withRequestTarget($requestTarget);

        return $this;
    }

    public function getMethod(): string
    {
        return strtoupper($this->decoratedServerRequest->getMethod());
    }

    public function withMethod($method): self
    {
        $this->decoratedServerRequest->withMethod($method);

        return $this;
    }

    public function getUri(): UriInterface
    {
        return $this->decoratedServerRequest->getUri();
    }

    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $this->decoratedServerRequest->withUri($uri, $preserveHost);

        return $this;
    }

    public function getServerParams(): array
    {
        return $this->decoratedServerRequest->getServerParams();
    }

    public function getCookieParams(): array
    {
        return $this->decoratedServerRequest->getCookieParams();
    }

    public function withCookieParams(array $cookies): self
    {
        $this->decoratedServerRequest->withCookieParams($cookies);

        return $this;
    }

    public function getQueryParams(): array
    {
        return $this->decoratedServerRequest->getQueryParams();
    }

    public function withQueryParams(array $query): self
    {
        $this->decoratedServerRequest->withQueryParams($query);

        return $this;
    }

    public function getUploadedFiles(): array
    {
        return $this->decoratedServerRequest->getUploadedFiles();
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $this->decoratedServerRequest->withUploadedFiles($uploadedFiles);

        return $this;
    }

    private ?array $parsedBody = null;

    /**
     * @return array The JSON decoded body, as array, or empty array if there was an error or the body wasn't JSON
     */
    public function getParsedBody(): array
    {
        if ($this->parsedBody === null) {
            $this->parsedBody = json_decode($this->getBody()->getContents(), true) ?? [];
        }

        return $this->parsedBody;
    }

    public function withParsedBody($data): self
    {
        $this->decoratedServerRequest->withParsedBody($data);

        return $this;
    }

    public function getAttributes()
    {
        return $this->decoratedServerRequest->getAttributes();
    }

    public function getAttribute($name, $default = null): mixed
    {
        return $this->decoratedServerRequest->getAttribute($name, $default);
    }

    public function withAttribute($name, $value): self
    {
        $this->decoratedServerRequest->withAttribute($name, $value);

        return $this;
    }

    public function withoutAttribute($name): self
    {
        $this->decoratedServerRequest->withoutAttribute($name);

        return $this;
    }
}

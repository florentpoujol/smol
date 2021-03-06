<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Infrastructure\Http;

use FlorentPoujol\Smol\Components\Cache\CacheInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ResponseCacheMiddleware
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    private string $cacheKey = '';

    public function handleRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        $this->cacheKey = 'responses:' . md5(
            $request->getMethod() .
            ($request->getHeader('host')[0] ?? '') .
            $request->getUri() .
            implode('', $request->getQueryParams())
        );

        return $this->cache->get($this->cacheKey);
    }

    public function handleResponse(ResponseInterface $response): void
    {
        if ($this->cacheKey === '' || $response->getStatusCode() !== 200) {
            return;
        }

        $this->cache->set($this->cacheKey, $response, 60 * 5);
    }
}

<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Http\Middleware;

use FlorentPoujol\Smol\Components\Http\Session;
use FlorentPoujol\Smol\Infrastructure\Http\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Redis;

final class StartSessionMiddleware
{
    private Session $session;

    public function __construct(
        private Redis $redis
    ) {
    }

    public function __invoke(ServerRequestInterface|ResponseInterface $request, Route $route): null|ServerRequestInterface
    {
        if ($request instanceof ServerRequestInterface) {
            $sessionId = $request->getCookieParams()['session'] ?? null;

            $session = null;
            if (is_string($sessionId) && strlen($sessionId) === 32) {
                $session = unserialize($this->redis->get("web-session:$sessionId"), ['allowed_classes' => [Session::class]]);
            }

            if (! $session instanceof Session) {
                $session = new Session();
                $session->regenerateId();
            }

            $this->session = $session;

            return $request->withAttribute('session', $session);
        }

        // handle response, write the session to the storage
        $this->redis->setex("web-session:{$this->session->id}", 3600 * 24 * 7, serialize($this->session));

        return null;
    }
}

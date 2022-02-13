<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Http\Middleware;

use Exception;
use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use FlorentPoujol\Smol\Components\Database\QueryBuilder;
use FlorentPoujol\Smol\Components\Http\Session;
use FlorentPoujol\Smol\Infrastructure\Http\Route;
use FlorentPoujol\Smol\Site\app\Entities\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Must be run after the StartSessionMiddleware.
 *
 * @template UserEntityType
 */
final class AuthenticateMiddleware
{
    public static ?User $user = null;

    public function __construct(
        private QueryBuilder $queryBuilder,
        private ConfigRepository $config,
    ) {
    }

    public function __invoke(ServerRequestInterface|ResponseInterface $request, Route $route): null|ServerRequestInterface
    {
        if (! $request instanceof ServerRequestInterface) {
            return null;
        }

        /** @var Session $session */
        $session = $request->getAttribute('session');

        $sessionUserIdKey = $this->config->get('auth.session_user_id_key', 'user_id');
        $userId = $session->data[$sessionUserIdKey] ?? -1;

        /** @var User $user */
        $user = $this->queryBuilder
            ->table('users')
            ->where('id', '=', (int) $userId)
            ->hydrate(User::class)
            ->selectSingle();

        if ($user === null) {
            throw new Exception();
        }

        if ($user->email_validated_at === null) {
            throw new Exception();
        }

        self::$user = $user;

        return $request->withAttribute('user', $user);
    }

    public static function login(User $user)
    {

    }
}

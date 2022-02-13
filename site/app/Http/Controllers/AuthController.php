<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Http\Controllers;

use Exception;
use FlorentPoujol\Smol\Components\Cache\CacheRateLimiter;
use FlorentPoujol\Smol\Components\Database\QueryBuilder;
use FlorentPoujol\Smol\Components\DateTime\DateTime;
use FlorentPoujol\Smol\Components\Http\Session;
use FlorentPoujol\Smol\Components\Validation\RuleInterface;
use FlorentPoujol\Smol\Components\Validation\Validator;
use FlorentPoujol\Smol\Components\ViewRenderer;
use FlorentPoujol\Smol\Infrastructure\RateLimiter\CacheRateLimiterFactory;
use FlorentPoujol\Smol\Site\app\Entities\User;
use FlorentPoujol\Smol\Site\app\Repositories\UserRepository;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    private CacheRateLimiter $rateLimiter;

    public function __construct(
        private CacheRateLimiterFactory $rateLimiterFactory,
        private ServerRequestInterface $request,
        private UserRepository $userRepo,
        private Validator $validator,
        private ViewRenderer $viewRenderer,
    ) {
        $this->rateLimiter = $this->rateLimiterFactory->makeFromConfig('auth', $this->request->getServerParams()['client-ip']);
    }

    /**
     * @param array<string, array<string|RuleInterface>> $rules
     * @param array<string>                              $exclude
     *
     * @return array<string, mixed>
     */
    private function getValidatedBody(array $rules, array $exclude = []): array
    {
        return $this->validator
            ->setRules($rules)
            ->setData($this->request->getParsedBody())
            ->getValidatedData($exclude);
    }

    // --------------------------------------------------
    // register

    /**
     * Route: GET /auth/register.
     */
    public function showRegisterForm(): Response
    {
        return new Response(body: $this->viewRenderer->render('auth.register'));
    }

    /**
     * Route: POST /auth/register.
     */
    public function register(): Response
    {
        $data = $this->getValidatedBody([
            'name' => ['string', 'min:5', 'max:255'],
            'email' => ['email', 'min:5', 'max:255'],
            'password' => ['string', 'min:8'],
            'password_confirm' => ['same-as:password'],
        ], ['password_confirm']);

        $user = User::fromArray($data);
        $user->password = $this->userRepo->hashPassword($user->password);
        $user->regenerateAuthToken(); // auth token used for email validation

        $this->userRepo->insert($user);

        // TODO send email with confirm email address link

        return new Response();
    }

    // --------------------------------------------------
    // login

    /**
     * Route: GET /auth/login.
     */
    public function showLoginForm(): Response
    {
        return new Response(body: $this->viewRenderer->render('auth.login'));
    }

    /**
     * Route: POST /auth/login.
     */
    public function login(): Response
    {
        $this->rateLimiter->hitAndTrow();

        $data = $this->getValidatedBody([
            'email' => ['email', 'min:5', 'max:255'],
            'password' => ['string', 'min:8'],
        ]);

        /** @var null|User $user */
        $user = $this->userRepo->find($data['email'], 'email');

        if (
            ! password_verify($data['password'], $user->password ?? '') // do that first because it will take the most time, and prevent timing attacks
            || $user?->email_validated_at === null
        ) {
            throw new Exception();
        }

        // login
        $this->rateLimiter->clear();

        $session = $this->request->getAttribute('session');
        $session->regenerateId();
        $session->data['user_id'] = $user->id;

        return new Response();
    }

    /**
     * Route: GET /auth/validate-email/{email}/{token}.
     */
    public function validateEmail(string $email, string $token): Response
    {
        $this->rateLimiter->hitAndTrow();

        /** @var null|User $user */
        $user = $this->userRepo->getQueryBuilder()
            ->where('email', '=', $email)
            ->where('auth_token', '=', $token)
            ->selectSingle();

        if ($user === null) {
            throw new Exception();
        }

        $user->auth_token = null;
        $user->email_validated_at = date('Y-m-d H:i:s');

        $this->userRepo->update($user, ['auth_token', 'email_validated_at']);

        $this->rateLimiter->clear();

        $session = $this->request->getAttribute('session');
        $session->regenerateId();

        return new Response();
    }

    /**
     * Route: GET /auth/logout.
     */
    public function logout(): Response
    {
        /** @var null|Session $session */
        $session = $this->request->getAttribute('session');

        if ($session !== null && isset($session->data['user_id'])) {
            $session->data = [];
            $session->regenerateId();
        }

        return new Response();
    }

    // --------------------------------------------------
    // lost password

    /**
     * Route: GET /auth/lost-password.
     */
    public function showLostPassword(): Response
    {
        return new Response(body: $this->viewRenderer->render('auth.lost-password'));
    }

    /**
     * Route: POST /auth/lost-password.
     */
    public function doLostPassword(): Response
    {
        $data = $this->getValidatedBody([
            'email' => ['email', 'min:5', 'max:255'],
        ]);

        /** @var null|User $user */
        $user = $this->userRepo->find($data['email']);

        if ($user === null) {
            throw new Exception();
        }

        QueryBuilder::make('password_resets')
            ->upsertSingle([
                'user_id' => $user->id, // key
                'user_email' => $user->email,
                'token' => substr(str_replace(['/', '+', '='], '', random_bytes(80)), 0, 100),
                'created_at' => date('Y-m-d H:i:s'),
            ], ['user_id']);

        // TODO send email to the user

        return new Response();
    }

    /**
     * Route: GET /auth/reset-password/{token}.
     */
    public function showResetPassword(): Response
    {
        return new Response(body: $this->viewRenderer->render('auth.reset-password'));
    }

    /**
     * Route: POST /auth/reset-password.
     */
    public function doResetPassword(): Response
    {
        $this->rateLimiter->hitAndTrow();

        $formData = $this->getValidatedBody([
            'password' => ['string', 'min:8'],
            'password_confirm' => ['same-as:password'],
            'token' => ['string', 'length:100'],
        ], ['password_confirm']);

        /** @var null|array<string, int|string> $passwordResetData */

        $passwordResetData = QueryBuilder::make('password_resets')
            ->where('token', '=', $formData['token'])
            ->where('created_at', '>=', (new DateTime())->subHours(2)->toDateTimeString())
            ->selectSingle();

        if (
            $passwordResetData === null
            || ! hash_equals($passwordResetData['token'], $formData['token']) // we do that to take case sensitivity into account and to prevent timing attacks
        ) {
            return new Response(); // error, return view
        }

        QueryBuilder::make('password_resets')
            ->where('token', '=', $passwordResetData['token'])
            ->delete();

        $this->userRepo->whereKey($passwordResetData['user_id'])
            ->update([
                'password' => $this->userRepo->hashPassword($formData['password']),
                'auth_token' => null,
            ]);

        $this->rateLimiter->clear();

        return new Response(); // redirect to the view
    }
}

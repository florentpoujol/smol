<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app\Http\Controllers;

use Exception;
use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use FlorentPoujol\Smol\Components\Database\QueryBuilder;
use FlorentPoujol\Smol\Components\DateTime\DateTime;
use FlorentPoujol\Smol\Components\Http\Session;
use FlorentPoujol\Smol\Components\Validation\Validator;
use FlorentPoujol\Smol\Components\ViewRenderer;
use FlorentPoujol\Smol\Site\app\Entities\User;
use FlorentPoujol\Smol\Site\app\Repositories\UserRepository;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private ConfigRepository $config,
        private QueryBuilder $queryBuilder,
        private ServerRequestInterface $request,
        private UserRepository $userRepo,
        private Validator $validator,
        private ViewRenderer $viewRenderer,
    ) {
    }

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
        // validate
        $data = $this->getValidatedBody([
            'name' => ['string', 'min:5', 'max:255'],
            'email' => ['email', 'min:5', 'max:255'],
            'password' => ['string', 'min:8'],
            'password_confirm' => ['same-as:password'],
        ], ['password_confirm']);

        // prepare user before insertion
        $user = User::fromArray($data);

        $user->password = User::hashPassword(
            $user->password,
            $this->config->get('auth.password.algo', PASSWORD_BCRYPT),
            $this->config->get('auth.password.algo-options', [
                'rounds' => 15,
            ])
        );
        $user->regenerateAuthToken(); // auth token used for email validation

        // insert
        $this->queryBuilder->table('users')->insertSingle($data);

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
        $data = $this->getValidatedBody([
            'email' => ['email', 'min:5', 'max:255'],
            'password' => ['string', 'min:8'],
        ]);

        /** @var null|User $user */
        $user = $this->userRepo->find($data['email'], 'email');

        if (
            $user === null
            || $user->email_validated_at === null
            || ! password_verify($data['password'], $user->password)
        ) {
            throw new Exception();
        }

        // login
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

        $this->queryBuilder->reset()
            ->table('password_resets')
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
        $formData = $this->getValidatedBody([
            'password' => ['string', 'min:8'],
            'password_confirm' => ['same-as:password'],
            'token' => ['string', 'length:100'],
        ], ['password_confirm']);

        /** @var null|array<string, int|string> $passwordResetData */
        $passwordResetData = $this->queryBuilder
            ->table('password_resets')
            ->where('token', '=', $formData['token'])
            ->where('created_at', '>=', (new DateTime())->subHours(2)->toDateTimeString())
            ->selectSingle();

        if (
            $passwordResetData === null
            || ! hash_equals($passwordResetData['token'], $formData['token']) // we do that to take case sensitivity into account and to prevent timing attacks
        ) {
            return new Response(); // error, return view
        }

        $this->queryBuilder->reset()
            ->table('password_resets')
            ->where('token', '=', $passwordResetData['token'])
            ->delete();

        $this->userRepo->whereKey($passwordResetData['user_id'])
            ->update([
                'password' => User::hashPassword($formData['password']),
                'auth_token' => null,
            ]);

        return new Response(); // redirect to the view
    }
}

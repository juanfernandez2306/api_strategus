<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Application\Settings\SettingsInterface;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\DTOs\Auth\UserDTO;
use App\Users\Repositories\Auth\TokenRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\LoginValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;

class LoginAction
{
    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private TokenRepositoryInterface $tokenRepo;
    private LoginValidator $validator;
    private LoggerInterface $logger;

    /** @var array<string, mixed> */
    private array $cookieSettings;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        TokenRepositoryInterface $tokenRepo,
        LoginValidator $validator,
        LoggerInterface $logger,
        SettingsInterface $settings
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->tokenRepo = $tokenRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->cookieSettings = $settings->get('cookie');
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower(trim($validatedData['email']));
        $password = $validatedData['password'];

        $userData = $this->userRepo->findByEmail($email);
        $userDTO = UserDTO::fromArray($userData);

        if ($userDTO === null || !password_verify($password, $userDTO->password)) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::UNAUTHORIZED,
                message: 'Las credenciales ingresadas son incorrectas.'
            );
        }

        if ((int) $userDTO->userIsActive !== 1) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::FORBIDDEN,
                message: 'Su cuenta se encuentra inactiva. Contacte al administrador.'
            );
        }

        if ($userDTO->emailVerifiedAt === null) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::FORBIDDEN,
                message: 'Debe verificar su correo electrónico antes de iniciar sesión.'
            );
        }

        $tokenPlain = bin2hex(random_bytes(32));
        $tokenName = 'auth_token';

        $this->pdo->beginTransaction();
        try {
            $saved = $this->tokenRepo->save(
                $userDTO->userId,
                $tokenName,
                $tokenPlain
            );

            if (!$saved) {
                throw new RuntimeException('Error al guardar el token de acceso.');
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error durante el inicio de sesión del usuario', [
                'user_id' => $userDTO->userId,
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }
        
        $cookieName   = $this->cookieSettings['name'];
        $cookieValue  = $tokenPlain;
        $cookieExpire = time() + $this->cookieSettings['lifetime'];

        $cookieParts = [
            sprintf('%s=%s', $cookieName, $cookieValue),
            'Expires=' . gmdate('D, d-M-Y H:i:s T', $cookieExpire),
            'Path=/',
        ];

        $domain = $this->cookieSettings['domain'];

        if (!empty($domain)) {
            $cookieParts[] = 'Domain=' . $domain;
        }

        $isSecure = $this->cookieSettings['secure'] ?? false;

        if (!empty($isSecure)) {
            $cookieParts[] = 'Secure';
        }

        $cookieParts[] = 'HttpOnly';
        $cookieParts[] = 'SameSite=' . ($this->cookieSettings['samesite'] ?? 'Lax');

        $cookieHeaderValue = implode('; ', $cookieParts);

        $response = $response->withAddedHeader('Set-Cookie', $cookieHeaderValue);

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Inicio de sesión exitoso.',
            data: [
                'token'          => $tokenPlain,
                'user_full_name' => $userDTO->getFullName(),
                'role_id'        => (int) $userDTO->userRoleId
            ]
        );
    }
}

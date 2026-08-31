<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
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

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        TokenRepositoryInterface $tokenRepo,
        LoginValidator $validator,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->tokenRepo = $tokenRepo;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower(trim($validatedData['email']));
        $password = $validatedData['password'];

        $user = $this->userRepo->findByEmail($email);

        if (empty($user) || !password_verify($password, $user['password'])) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::UNAUTHORIZED,
                message: 'Las credenciales ingresadas son incorrectas.'
            );
        }

        if ((int) $user['is_active'] !== 1) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::FORBIDDEN,
                message: 'Su cuenta se encuentra inactiva. Contacte al administrador.'
            );
        }

        if ($user['email_verified_at'] === null) {
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
                (int) $user['id'],
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
                'user_id' => $user['id'],
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $firstName = mb_convert_case($user['first_name'] ?? '', MB_CASE_TITLE, 'UTF-8');
        $lastName  = mb_convert_case($user['last_name'] ?? '', MB_CASE_TITLE, 'UTF-8');
        $userFullName = trim("{$firstName} {$lastName}");

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Inicio de sesión exitoso.',
            data: [
                'token'          => $tokenPlain,
                'user_full_name' => $userFullName,
                'role_id'        => (int) $user['role_id']
            ]
        );
    }
}

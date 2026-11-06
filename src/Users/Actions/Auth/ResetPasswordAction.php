<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\DTOs\Auth\PasswordResetTokenDTO;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\ResetPasswordValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ResetPasswordAction
{
    private PDO $pdo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private UserRepositoryInterface $userRepo;
    private ResetPasswordValidator $validator;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        UserRepositoryInterface $userRepo,
        ResetPasswordValidator $validator,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->userRepo = $userRepo;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower($validatedData['email']);
        $tokenPlain = $validatedData['token'];
        $newPassword = $validatedData['password'];

        $resetTokenData = $this->passwordResetRepo->findByToken($tokenPlain);

        if (empty($resetTokenData)) {
            return ApiResponse::json(
                $response,
                HttpStatus::NOT_FOUND,
                'El enlace de restablecimiento es inválido o no existe.'
            );
        }

        $resetTokenDTO = PasswordResetTokenDTO::fromArray($resetTokenData);

        if ($resetTokenDTO->isTokenExpired()) {
            return ApiResponse::json(
                $response,
                HttpStatus::BAD_REQUEST,
                'El enlace de restablecimiento ha expirado. Por favor, solicite uno nuevo.'
            );
        }

        $this->pdo->beginTransaction();
        try {
            $updated = $this->userRepo->updatePassword(
                userId: $resetTokenDTO->userId,
                newPassword: $newPassword
            );

            $deleted = $this->passwordResetRepo->deleteByUserId(
                userId: $resetTokenDTO->userId
            );

            (!$updated || !$deleted) && throw new RuntimeException(
                'Error al actualizar la contraseña.'
            );

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error al restablecer la contraseña del usuario', [
                'user_id' => $resetTokenDTO->userId,
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            'La contraseña ha sido actualizada con éxito.'
        );
    }
}

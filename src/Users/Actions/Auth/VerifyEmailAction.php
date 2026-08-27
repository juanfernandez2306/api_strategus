<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\VerifyEmailValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;
use RuntimeException;

class VerifyEmailAction
{
    private PDO $pdo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private UserRepositoryInterface $userRepo;
    private VerifyEmailValidator $validator;


    public function __construct(
        PDO $pdo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        UserRepositoryInterface $userRepo,
        VerifyEmailValidator $validator
    ) {
        $this->pdo = $pdo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->userRepo = $userRepo;
        $this->validator = $validator;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getQueryParams() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = $validatedData['email'];
        $tokenPlain = $validatedData['token'];

        $resetRecord = $this->passwordResetRepo->findByEmailAndToken($email, $tokenPlain);

        if (!$resetRecord) {
            return ApiResponse::json(
                $response,
                HttpStatus::NOT_FOUND,
                'El enlace de verificación es inválido o no existe.'
            );
        }

        if ($resetRecord['email_verified_at'] !== null) {
            return ApiResponse::json(
                $response,
                HttpStatus::OK,
                'El correo electrónico ya ha sido verificado anteriormente.'
            );
        }

        if ($resetRecord['expires_at'] < date('Y-m-d H:i:s')) {
            return ApiResponse::json(
                $response,
                HttpStatus::BAD_REQUEST,
                'El enlace de verificación ha expirado. Por favor, solicite uno nuevo.'
            );
        }

        $this->pdo->beginTransaction();
        try {
            $updated = $this->userRepo->markEmailAsVerified((int) $resetRecord['user_id']);
            $deleted = $this->passwordResetRepo->deleteByUserId((int) $resetRecord['user_id']);

            (!$updated || !$deleted) && throw new RuntimeException('Error al procesar la verificación.');

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            'Correo electrónico verificado con éxito.'
        );
    }
}

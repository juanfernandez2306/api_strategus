<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\DTOs\Auth\UserDTO;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\AuthEmailService;
use App\Users\Validators\Auth\EmailRequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;
use Ramsey\Uuid\Uuid;

class SendPasswordResetEmailAction
{
    private const GENERIC_SUCCESS_MESSAGE = 'Si la dirección de correo ingresada coincide con una cuenta registrada, '
                                            . 'recibirás un enlace para restablecer tu contraseña.';

    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private EmailRequestValidator $validator;
    private LoggerInterface $logger;
    private AuthEmailService $authEmailService;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        EmailRequestValidator $validator,
        LoggerInterface $logger,
        AuthEmailService $authEmailService
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->authEmailService = $authEmailService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower($validatedData['email']);

        $userData = $this->userRepo->findByEmail($email);

        if (empty($userData)) {
            return ApiResponse::json(
                $response,
                HttpStatus::OK,
                self::GENERIC_SUCCESS_MESSAGE
            );
        }

        $userDTO = UserDTO::fromArray($userData);

        if ($this->passwordResetRepo->hasRecentToken($userDTO->id)) {
            return ApiResponse::json(
                $response,
                HttpStatus::OK,
                self::GENERIC_SUCCESS_MESSAGE
            );
        }

        $tokenPlain = Uuid::uuid7()->toString();
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));

        $this->pdo->beginTransaction();
        try {
            $saved = $this->passwordResetRepo->save(
                userId: $userDTO->id,
                tokenPlain: $tokenPlain,
                expiresAt: $tokenExpiresAt
            );

            if (!$saved) {
                throw new RuntimeException(
                    'Error al guardar el token de restablecimiento de contraseña.'
                );
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error al solicitar restablecimiento de contraseña', [
                'user_id' => $userDTO->id,
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $resetUrl = AuthEmailService::generateUrl(
            path: 'users/password/forgot',
            tokenPlain: $tokenPlain
        );

        $this->authEmailService->sendGenericEmail(
            toEmail: $email,
            toName: $userDTO->getFullName(),
            subject: 'Restablecer contraseña - GESTIÓN PALMA DIGITAL',
            viewTemplate: 'Emails.ResetPassword',
            viewData: [
                'userFullName' => $userDTO->getFullName(),
                'actionUrl'    => $resetUrl,
            ]
        );

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            self::GENERIC_SUCCESS_MESSAGE
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Validators\Auth\RegisterValidator;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\UserMailServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RegisterAction
{
    private PDO $pdo;
    private RegisterValidator $validator;
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private UserMailServiceInterface $userMailService;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        RegisterValidator $validator,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        UserMailServiceInterface $userMailService,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->validator = $validator;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->userMailService = $userMailService;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $validatedData = $this->validator->validate($data);

        $this->pdo->beginTransaction();

        try {
            $userId = $this->userRepo->create($validatedData);

            ($userId <= 0) && throw new RuntimeException(
                "No se pudo obtener el ID del usuario recién creado."
            );

            $tokenPlain = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));

            $savedToken = $this->passwordResetRepo->save($userId, $tokenPlain, $expiresAt);

            (!$savedToken) && throw new RuntimeException(
                "Error al registrar el token de verificación del usuario."
            );

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error al registrar el nuevo usuario', [
                'email' => $validatedData['email'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $fullName = ucfirst($validatedData['first_name'])
                    . ' ' . ucfirst($validatedData['last_name']);


        $frontendUrl = $_ENV['FRONTEND_URL'] ?? '';

        if (empty($frontendUrl)) {
            $msg = "La variable de entorno 'FRONTEND_URL' no está definida o está vacía.";
            $this->logger->critical($msg);
            throw new RuntimeException($msg);
        }

        $verificationUrl = sprintf(
            '%s/verify/email?email=%s&token=%s',
            rtrim($frontendUrl, '/'),
            urlencode($validatedData['email']),
            urlencode($tokenPlain)
        );

        $this->userMailService->send([
            'toEmail'      => $validatedData['email'],
            'userFullName' => $fullName,
            'subject'      => 'Verifica tu cuenta - GESTIÓN PALMA DIGITAL',
            'viewTemplate' => 'Emails.VerifyEmail',
            'actionUrl'    => $verificationUrl,
        ]);

        return ApiResponse::json(
            $response,
            HttpStatus::CREATED,
            'Usuario registrado exitosamente. Se ha enviado un correo para verificar la cuenta.',
            [
                'user_id' => $userId
            ]
        );
    }
}

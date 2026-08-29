<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\UserMailServiceInterface;
use App\Users\Validators\Auth\EmailRequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;

class SendPasswordResetEmailAction
{
    private const GENERIC_SUCCESS_MESSAGE = 'Si la dirección de correo ingresada coincide con una cuenta registrada, '
                                            . 'recibirás un enlace para restablecer tu contraseña.';

    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private UserMailServiceInterface $userMailService;
    private EmailRequestValidator $validator;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        UserMailServiceInterface $userMailService,
        EmailRequestValidator $validator,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->userMailService = $userMailService;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower($validatedData['email']);

        $user = $this->userRepo->findByEmail($email);

        if (empty($user)) {
            return ApiResponse::json(
                $response,
                HttpStatus::OK,
                self::GENERIC_SUCCESS_MESSAGE
            );
        }

        $tokenPlain = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));

        $this->pdo->beginTransaction();
        try {
            $saved = $this->passwordResetRepo->save(
                (int) $user['id'],
                $tokenPlain,
                $expiresAt
            );

            if (!$saved) {
                throw new RuntimeException('Error al guardar el token de restablecimiento de contraseña.');
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error al solicitar restablecimiento de contraseña', [
                'user_id' => $user['id'],
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $frontendUrl = $_ENV['FRONTEND_URL'] ?? '';

        (empty($frontendUrl)) && throw new RuntimeException(
            "La variable de entorno 'FRONTEND_URL' no está definida o está vacía."
        );

        $resetUrl = sprintf(
            '%s/reset/password?email=%s&token=%s',
            rtrim($frontendUrl, '/'),
            urlencode($email),
            urlencode($tokenPlain)
        );

        $fullName = trim(ucfirst($user['first_name'] ?? '') . ' ' . ucfirst($user['last_name'] ?? ''));

        $this->userMailService->send([
            'toEmail'      => $email,
            'userFullName' => $fullName,
            'subject'      => 'Restablecer contraseña - GESTIÓN PALMA DIGITAL',
            'viewTemplate' => 'Emails.ResetPassword',
            'actionUrl'    => $resetUrl,
        ]);

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            self::GENERIC_SUCCESS_MESSAGE
        );
    }
}

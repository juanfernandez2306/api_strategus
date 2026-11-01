<?php

declare(strict_types=1);

namespace App\Users\Services\Mail;

use App\Users\DTOs\Auth\RegisterUserDTO;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

class UserRegistrationEmailService implements UserRegistrationEmailServiceInterface
{
    private UserMailServiceInterface $userMailService;
    private LoggerInterface $logger;

    public function __construct(
        UserMailServiceInterface $userMailService,
        LoggerInterface $logger
    ) {
        $this->userMailService = $userMailService;
        $this->logger = $logger;
    }


    public function sendVerification(
        int $userId,
        RegisterUserDTO $userDto,
        string $tokenPlain
    ): void {
        try {
            $frontendUrl = $_ENV['FRONTEND_URL'];

            if (empty($frontendUrl)) {
                throw new RuntimeException(
                    "La variable de entorno 'FRONTEND_URL' no está definida o está vacía."
                );
            }

            $verificationUrl = sprintf(
                '%s/verify/email?email=%s&token=%s',
                rtrim($frontendUrl),
                urlencode($userDto->email),
                urlencode($tokenPlain)
            );

            $this->userMailService->send([
                'toEmail'      => $userDto->email,
                'userFullName' => $userDto->getFullName(),
                'subject'      => 'Verifica tu cuenta - GESTIÓN PALMA DIGITAL',
                'viewTemplate' => 'Emails.VerifyEmail',
                'actionUrl'    => $verificationUrl,
            ]);
        } catch (Exception $e) {
            $this->logger->critical(
                'Usuario registrado exitosamente, pero falló el envío del correo de verificación',
                [
                    'user_id' => $userId,
                    'email'   => $userDto->email,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString()
                ]
            );
        }
    }
}

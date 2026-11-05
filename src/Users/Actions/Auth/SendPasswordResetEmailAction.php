<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\DTOs\SendTemplatedMailDTO;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Shared\Services\Mail\TemplatedMailServiceInterface;
use App\Users\DTOs\Auth\UserDTO;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
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
    private EmailRequestValidator $validator;
    private LoggerInterface $logger;
    private TemplatedMailServiceInterface $templatedMailService;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        EmailRequestValidator $validator,
        LoggerInterface $logger,
        TemplatedMailServiceInterface $templatedMailService
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->templatedMailService = $templatedMailService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $validatedData = $this->validator->validate($data);

        $email = mb_strtolower($validatedData['email']);

        $userData = $this->userRepo->findByEmail($email);

        $userDTO = UserDTO::fromArray($userData);

        if ($userDTO === null) {
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
                $userDTO->userId,
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
                'user_id' => $userDTO->userId,
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

        
        $mailDTO = new SendTemplatedMailDTO(
            toEmail: $email,
            toName: $userDTO->getFullName(),
            subject: 'Restablecer contraseña - GESTIÓN PALMA DIGITAL',
            viewTemplate: 'Emails.ResetPassword',
            viewData: [
                'userFullName' => $userDTO->getFullName(),
                'actionUrl'    => $resetUrl,
            ]
        );

        $this->templatedMailService->send($mailDTO);

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            self::GENERIC_SUCCESS_MESSAGE
        );
    }
}

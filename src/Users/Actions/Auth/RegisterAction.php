<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\DTOs\SendTemplatedMailDTO;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Shared\Services\Mail\TemplatedMailServiceInterface;
use App\Users\DTOs\Auth\RegisterUserDTO;
use App\Users\Validators\Auth\RegisterValidator;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
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
    private LoggerInterface $logger;

    private TemplatedMailServiceInterface $templatedMailService;

    public function __construct(
        PDO $pdo,
        RegisterValidator $validator,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        LoggerInterface $logger,
        TemplatedMailServiceInterface $templatedMailService
    ) {
        $this->pdo = $pdo;
        $this->validator = $validator;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->logger = $logger;
        $this->templatedMailService = $templatedMailService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $validatedData = $this->validator->validate($data);

        $userDto = RegisterUserDTO::fromValidated($validatedData);

        $this->pdo->beginTransaction();

        try {
            $userId = $this->userRepo->create($userDto);

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
                'email' => $userDto->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }

        $verificationUrl = $this->buildVerificationUrl($userDto, $tokenPlain);

        $isEmailSent = $this->templatedMailService->send(new SendTemplatedMailDTO(
            toEmail: $userDto->email,
            toName: $userDto->getFullName(),
            subject: 'Verifica tu cuenta - GESTIÓN PALMA DIGITAL',
            viewTemplate: 'Emails.VerifyEmail',
            viewData: [
                'userFullName' => $userDto->getFullName(),
                'actionUrl'    => $verificationUrl,
            ]
        ));

        $msg = "Usuario registrado exitosamente." . (
            $isEmailSent
                ? " Se ha enviado un correo para verificar la cuenta."
                : " Hubo un problema al enviar el correo de verificación. Solicítalo más tarde."
        );


        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::CREATED,
            message: $msg
        );
    }

    private function buildVerificationUrl(
        RegisterUserDTO $userDto,
        string $tokenPlain
    ): string {
        $frontendUrl = $_ENV['FRONTEND_URL'] ??
            throw new RuntimeException("La variable 'FRONTEND_URL' no está definida.");

        return sprintf(
            '%s/verify/email?email=%s&token=%s',
            rtrim($frontendUrl, '/'),
            urlencode($userDto->email),
            urlencode($tokenPlain)
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Shared\DTOs\SendTemplatedMailDTO;
use App\Shared\Services\Mail\TemplatedMailServiceInterface;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\VerifyEmailValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use App\Users\DTOs\Auth\VerifyEmailDTO;

class VerifyEmailAction
{
    private PDO $pdo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private UserRepositoryInterface $userRepo;
    private VerifyEmailValidator $validator;
    private LoggerInterface $logger;
    private TemplatedMailServiceInterface $templatedMailService;

    public function __construct(
        PDO $pdo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        UserRepositoryInterface $userRepo,
        VerifyEmailValidator $validator,
        LoggerInterface $logger,
        TemplatedMailServiceInterface $templatedMailService,
    ) {
        $this->pdo = $pdo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->userRepo = $userRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->templatedMailService = $templatedMailService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getQueryParams());
        $validatedData = $this->validator->validate($data);
        $dataDTO = VerifyEmailDTO::fromArray($validatedData);

        $email = $dataDTO->email;
        $tokenPlain = $dataDTO->token;

        $resetRecord = $this->passwordResetRepo->findByEmailAndToken($email, $tokenPlain);

        if ($resetRecord == null) {
            return ApiResponse::json(
                $response,
                HttpStatus::NOT_FOUND,
                'El enlace de verificación es inválido o no existe.'
            );
        }

        if ($resetRecord->emailVerifiedAt !== null) {
            return ApiResponse::json(
                $response,
                HttpStatus::OK,
                'El correo electrónico ya ha sido verificado anteriormente.'
            );
        }

        if ($resetRecord->expiresAt < date('Y-m-d H:i:s')) {
            return ApiResponse::json(
                $response,
                HttpStatus::BAD_REQUEST,
                'El enlace de verificación ha expirado. Por favor, solicite uno nuevo.'
            );
        }

        $this->pdo->beginTransaction();
        try {
            $updated = $this->userRepo->markEmailAsVerified((int) $resetRecord->userId);
            $deleted = $this->passwordResetRepo->deleteByUserId((int) $resetRecord->userId);

            (!$updated || !$deleted) && throw new RuntimeException('Error al procesar la verificación.');

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logger->error('Error al procesar la verificación de correo electrónico', [
                'user_id' => $resetRecord->userId,
                'email'   => $email,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            throw $e;
        }

        $fullName = $resetRecord->getFullName();

        $isMailSent = $this->notifyAdminsAboutNewUser(
            $resetRecord->userId,
            $fullName, 
            $email
        );

        if (!$isMailSent) {
            $this->logger->warning(
                'El correo electrónico se verificó con éxito, pero falló el envío ' . 
                'de la notificación a los administradores.', 
                [
                    'user_id' => $resetRecord->userId,
                    'email'   => $email,
                    'name'    => $fullName,
                ]
            );
        }

        $message = 'Correo electrónico verificado con éxito.' . 
            ($isMailSent 
                ? ' Se ha notificado a los administradores.'
                : ' No se pudo enviar la notificación por correo a los administradores.'
            );

        return ApiResponse::json(
            $response,
            HttpStatus::OK,
            $message
        );
    }

    private function notifyAdminsAboutNewUser(
        int $userId,
        string $registeredUserName, 
        string $registeredUserEmail): bool
    {
        $adminEmails = $this->userRepo->findAdminEmails();

        if (empty($adminEmails)) {
            $this->logger->warning(
                'No se encontraron usuarios administradores activos '. 
                'para notificar sobre el nuevo usuario registrado.', 
                [
                    'user_id' => $userId,
                    'email'   => $registeredUserEmail,
                    'name'    => $registeredUserName,
                ]
            );

            return false;
        }

        $mailDTO = new SendTemplatedMailDTO(
            toEmail: $adminEmails,
            subject: 'Nuevo Usuario Registrado y Verificado',
            viewTemplate: 'Emails.NewUserAdminNotification',
            viewData: [
                'registeredUserName'  => $registeredUserName,
                'registeredUserEmail' => $registeredUserEmail,
            ],
            toName: 'Administrador'
        );

        return $this->templatedMailService->send($mailDTO);
    }
}
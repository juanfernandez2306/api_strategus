<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Validators\Auth\RegisterValidator;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\MailRegisterInterface;
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
    private MailRegisterInterface $mailRegisterService;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        RegisterValidator $validator,
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        MailRegisterInterface $mailRegisterService,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->validator = $validator;
        $this->userRepo = $userRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->mailRegisterService = $mailRegisterService;
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

        $this->mailRegisterService->send(
            $validatedData['email'],
            $fullName,
            $tokenPlain
        );

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

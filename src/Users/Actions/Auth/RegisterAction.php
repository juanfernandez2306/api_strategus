<?php

declare(strict_types=1);

namespace App\Users\Actions\Auth;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Validators\Auth\RegisterValidator;
use App\Users\Repositories\Crud\UserCrudRepositoryInterface;
use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Services\Mail\MailRegisterInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;
use RuntimeException;

class RegisterAction
{
    private PDO $pdo;
    private RegisterValidator $validator;
    private UserCrudRepositoryInterface $userCrudRepo;
    private PasswordResetRepositoryInterface $passwordResetRepo;
    private MailRegisterInterface $mailRegisterService;

    public function __construct(
        PDO $pdo,
        RegisterValidator $validator,
        UserCrudRepositoryInterface $userCrudRepo,
        PasswordResetRepositoryInterface $passwordResetRepo,
        MailRegisterInterface $mailRegisterService
    ) {
        $this->pdo = $pdo;
        $this->validator = $validator;
        $this->userCrudRepo = $userCrudRepo;
        $this->passwordResetRepo = $passwordResetRepo;
        $this->mailRegisterService = $mailRegisterService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);


        $validatedData = $this->validator->validate($data);


        $this->pdo->beginTransaction();

        try {
            $userId = $this->userCrudRepo->create($validatedData);

            ($userId <= 0) && throw new RuntimeException("No se pudo obtener el ID del usuario recién creado.");

            $tokenPlain = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));


            $savedToken = $this->passwordResetRepo->save($userId, $tokenPlain, $expiresAt);

            (!$savedToken) && throw new RuntimeException("Error al registrar el token de verificación del usuario.");


            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }


        $fullName = ucfirst($validatedData['first_name']) . ' ' . ucfirst($validatedData['last_name']);

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

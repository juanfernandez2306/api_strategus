<?php

declare(strict_types=1);

namespace App\Users\Actions\Crud;

use App\Shared\DTOs\SendTemplatedMailDTO;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Shared\Services\Mail\TemplatedMailServiceInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Crud\UpdateUserValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;
use App\Users\DTOs\Auth\UserDTO;

class UpdateUserAction
{
    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private UpdateUserValidator $validator;
    private LoggerInterface $logger;

    private TemplatedMailServiceInterface $templatedMailService;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        UpdateUserValidator $validator,
        LoggerInterface $logger,
        TemplatedMailServiceInterface $templatedMailService
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->templatedMailService = $templatedMailService;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $data = array_merge(
            (array) ($request->getParsedBody() ?? []),
            ['id' => $args['id'] ?? null]
        );

        $validatedData = $this->validator->validate($data);

        $targetUserId = (int) $validatedData['id'];
        $userData = $this->userRepo->findById($targetUserId);

        $currentUser = UserDTO::fromArray($userData);

        if ($currentUser === null) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::NOT_FOUND,
                message: 'El usuario especificado no existe.'
            );
        }

        $oldUserIsActive = (bool) $currentUser->userIsActive;
        $newUserIsActive = (bool) $validatedData['is_active'];

        $this->pdo->beginTransaction();
        try {
            $updated = $this->userRepo->update($validatedData);

            if (!$updated) {
                throw new RuntimeException('No se pudieron actualizar los datos del usuario.');
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Error al actualizar usuario por el administrador', [
                'target_user_id' => $targetUserId,
                'error'          => $e->getMessage()
            ]);
            throw $e;
        }

        if ($oldUserIsActive !== $newUserIsActive) {
            
            $subject = $newUserIsActive
                ? 'Tu cuenta ha sido reactivada - GESTIÓN PALMA DIGITAL'
                : 'Aviso: Tu cuenta ha sido desactivada - GESTIÓN PALMA DIGITAL';

            $this->templatedMailService->send(new SendTemplatedMailDTO(
                toEmail: $currentUser->email,
                toName: $currentUser->getFullName(),
                subject: $subject,
                viewTemplate: 'Emails.AccountStatusChanged',
                viewData: [
                    'isActive' => $newUserIsActive,
                ]
            ));

            
        }

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Usuario actualizado exitosamente.'
        );
    }
}

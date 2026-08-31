<?php

declare(strict_types=1);

namespace App\Users\Actions\Crud;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Services\Mail\UserMailServiceInterface;
use App\Users\Validators\Crud\UpdateUserValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use RuntimeException;

class UpdateUserAction
{
    private PDO $pdo;
    private UserRepositoryInterface $userRepo;
    private UpdateUserValidator $validator;
    private UserMailServiceInterface $userMailService;
    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        UserRepositoryInterface $userRepo,
        UpdateUserValidator $validator,
        UserMailServiceInterface $userMailService,
        LoggerInterface $logger
    ) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->validator = $validator;
        $this->userMailService = $userMailService;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $data = array_merge(
            (array) ($request->getParsedBody() ?? []),
            ['id' => $args['id'] ?? null]
        );

        $validatedData = $this->validator->validate($data);

        $targetUserId = (int) $validatedData['id'];
        $currentUser = $this->userRepo->findById($targetUserId);

        if (empty($currentUser)) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::NOT_FOUND,
                message: 'El usuario especificado no existe.'
            );
        }

        $oldUserIsActive = (bool) $currentUser['is_active'];
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
            $firstName = mb_convert_case($currentUser['first_name'] ?? '', MB_CASE_TITLE, 'UTF-8');
            $lastName  = mb_convert_case($currentUser['last_name'] ?? '', MB_CASE_TITLE, 'UTF-8');
            $fullName  = trim("{$firstName} {$lastName}");

            $subject = $newUserIsActive
                ? 'Tu cuenta ha sido reactivada - GESTIÓN PALMA DIGITAL'
                : 'Aviso: Tu cuenta ha sido desactivada - GESTIÓN PALMA DIGITAL';

            $this->userMailService->send([
                'toEmail'      => $currentUser['email'],
                'userFullName' => $fullName,
                'subject'      => $subject,
                'viewTemplate' => 'Emails.AccountStatusChanged',
                'context'      => [
                    'isActive' => $newUserIsActive,
                ]
            ]);
        }

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Usuario actualizado exitosamente.'
        );
    }
}

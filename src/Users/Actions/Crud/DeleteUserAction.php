<?php

declare(strict_types=1);

namespace App\Users\Actions\Crud;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Crud\DeleteUserValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDOException;
use Exception;

class DeleteUserAction
{
    private UserRepositoryInterface $userRepo;
    private LoggerInterface $logger;
    private DeleteUserValidator $validator;

    public function __construct(
        UserRepositoryInterface $userRepo,
        LoggerInterface $logger,
        DeleteUserValidator $validator
    ) {
        $this->userRepo = $userRepo;
        $this->logger = $logger;
        $this->validator = $validator;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $validatedData = $this->validator->validate([
            'id' => $args['id'] ?? null,
        ]);

        $userId = (int) $validatedData['id'];
        $user = $this->userRepo->findById($userId);

        if (empty($user)) {
            return ApiResponse::json(
                response: $response,
                statusCode: HttpStatus::NOT_FOUND,
                message: 'El usuario especificado no existe.'
            );
        }

        try {
            $deleted = $this->userRepo->delete($userId);

            if (!$deleted) {
                return ApiResponse::json(
                    response: $response,
                    statusCode: HttpStatus::BAD_REQUEST,
                    message: 'No se pudo procesar la eliminación del usuario.'
                );
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = "No es posible eliminar el usuario porque "
                           . "tiene registros de actividad vinculados en el sistema.";

                return ApiResponse::json(
                    response: $response,
                    statusCode: HttpStatus::CONFLICT,
                    message: $message
                );
            }

            $this->logger->error('Error de base de datos al intentar eliminar usuario', [
                'target_user_id' => $userId,
                'error'          => $e->getMessage()
            ]);
            throw $e;
        }

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Usuario eliminado correctamente.'
        );
    }
}

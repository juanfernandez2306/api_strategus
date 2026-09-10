<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Users\Repositories\Auth\PdoTokenRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private PdoTokenRepository $repository;

    public function __construct(PdoTokenRepository $repository)
    {
        $this->repository = $repository;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse(
                'Token de autenticación no proporcionado.',
                ['auth_header' => 'vacio']
            );
        }

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorizedResponse(
                'Formato de autenticación inválido. Debe ser Bearer <token>.',
                ['auth_header_received' => $authHeader]
            );
        }

        $plainToken = $matches[1];
        $tokenHashed = hash('sha256', $plainToken);

        // 🔍 DEBUG 1: Consultar la BD
        $accessToken = $this->repository->getAccessToken($tokenHashed);

        if (empty($accessToken)) {
            return $this->unauthorizedResponse(
                'Token inválido o sesión expirada.',
                [
                    'plain_token_received' => $plainToken,
                    'token_hashed_searched' => $tokenHashed,
                    'db_result' => 'Array vacío [] (no se encontró coincidencia en personal_access_tokens)'
                ]
            );
        }

        // 🔍 DEBUG 2: Evaluar si role_id existe o viene nulo
        $roleId = $accessToken['role_id'] ?? null;

        if ($roleId === null) {
            return $this->unauthorizedResponse(
                'El token es válido, pero el campo role_id no está presente en la respuesta de la Base de Datos.',
                [
                    'raw_db_row' => $accessToken,
                    'keys_retrieved' => array_keys($accessToken)
                ]
            );
        }

        if ((int)$accessToken['is_active'] !== 1) {
            return $this->unauthorizedResponse(
                'Acceso denegado: El usuario se encuentra inactivo.',
                ['is_active' => $accessToken['is_active'] ?? null]
            );
        }

        if (empty($accessToken['email_verified_at'])) {
            return $this->unauthorizedResponse(
                'Acceso denegado: Su dirección de correo electrónico no ha sido verificada.',
                ['email_verified_at' => $accessToken['email_verified_at'] ?? null]
            );
        }

        if (strtotime($accessToken['expires_at']) < time()) {
            return $this->unauthorizedResponse(
                'La sesión ha expirado. Por favor, inicie sesión nuevamente.',
                [
                    'expires_at' => $accessToken['expires_at'] ?? null,
                    'current_time' => date('Y-m-d H:i:s')
                ]
            );
        }

        // Asignación de atributos a la Request
        $request = $request->withAttribute('user_id', $accessToken['user_id']);
        $request = $request->withAttribute('role_id', $roleId);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message, array $debugInfo = []): Response
    {
        return ApiResponse::json(
            response: new SlimResponse(),
            statusCode: HttpStatus::UNAUTHORIZED,
            message: $message,
            errors: [
                'debug_info' => $debugInfo
            ]
        );
    }
}

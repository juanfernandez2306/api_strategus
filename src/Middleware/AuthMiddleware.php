<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Shared\DTOs\CookieConfigDTO;
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
    private CookieConfigDTO $cookieConfig;

    public function __construct(PdoTokenRepository $repository, ?CookieConfigDTO $cookieConfig = null)
    {
        $this->repository = $repository;
        $this->cookieConfig = $cookieConfig ?? CookieConfigDTO::fromArray([]);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $plainToken = null;
        $cookieName = $this->cookieConfig->name;

        $cookies = $request->getCookieParams();

        if (!empty($cookies[$cookieName])) {
            $plainToken = $cookies[$cookieName];
        }

        if (empty($plainToken)) {
            $authHeader = $request->getHeaderLine('Authorization');

            if (empty($authHeader)) {
                return $this->unauthorizedResponse(
                    'Token de autenticación no proporcionado.',
                    ['auth_header' => 'vacio']
                );
            }

            if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                return $this->unauthorizedResponse(
                    'Formato de autenticación inválido. Debe ser Bearer <token>.',
                    ['auth_header_received' => $authHeader]
                );
            }

            $plainToken = $matches[1];
        }

        $tokenHashed = hash('sha256', $plainToken);
        $accessToken = $this->repository->getAccessToken($tokenHashed);

        if ($accessToken === null) {
            return $this->unauthorizedResponse(
                'Token inválido o sesión expirada.',
                [
                    'token_hashed_searched' => $tokenHashed,
                    'db_result' => 'No se encontró coincidencia en personal_access_tokens'
                ]
            );
        }

        if (!$accessToken->isValidUser()) {
            return $this->unauthorizedResponse(
                'Acceso denegado: El usuario está inactivo o la sesión ha expirado.',
                [
                    'is_active' => $accessToken->isActive,
                    'expires_at' => $accessToken->expiresAt?->format('Y-m-d H:i:s')
                ]
            );
        }

        if ($accessToken->emailVerifiedAt === null) {
            return $this->unauthorizedResponse(
                'Acceso denegado: Su dirección de correo electrónico no ha sido verificada.',
                ['email_verified_at' => null]
            );
        }

        $request = $request->withAttribute('user_id', $accessToken->userId);
        $request = $request->withAttribute('role_id', $accessToken->roleId);

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

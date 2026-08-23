<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use App\Users\Repositories\Auth\AccessTokenUserRepository;

class AuthMiddleware implements MiddlewareInterface
{
    private AccessTokenUserRepository $repository;
    public function __construct(AccessTokenUserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Token de autenticación no proporcionado.');
        }

        
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorizedResponse(
                'Formato de autenticación inválido. Debe ser Bearer <token>.'
            );
        }

        $plainToken = $matches[1];

        $tokenHashed = hash('sha256', $plainToken);

        $accessToken = $this->repository->getAccessToken($tokenHashed);
        
        if (empty($accessToken)) {
            return $this->unauthorizedResponse(
                'Token inválido o sesión expirada.'
            );
        }

        if ((int)$accessToken['is_active'] !== 1) {
            return $this->unauthorizedResponse(
                'Acceso denegado: El usuario se encuentra inactivo.'
            );
        }

        if (empty($accessToken['email_verified_at'])) {
            return $this->unauthorizedResponse(
                'Acceso denegado: Su dirección de correo electrónico no ha sido verificada.'
            );
        }

        
        if (strtotime($accessToken['expires_at']) < time()) {
            return $this->unauthorizedResponse(
                'La sesión ha expirado. Por favor, inicie sesión nuevamente.'
            );
        }

        
        $request = $request->withAttribute('user_id', $accessToken['user_id']);
        $request = $request->withAttribute('role_id', $accessToken['role_id'] ?? null);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Acceso denegado de forma segura por el Middleware.',
            'error'   => $message
        ], JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}

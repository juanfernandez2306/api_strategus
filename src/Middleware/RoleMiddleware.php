<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class RoleMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {

        $userRole = $request->getAttribute('role_id');

        if ($userRole === null || !in_array((int) $userRole, $this->allowedRoles, true)) {
            return $this->forbiddenResponse(
                'No tienes los privilegios necesarios para realizar esta acción.'
            );
        }

        return $handler->handle($request);
    }


    private function forbiddenResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Acceso restringido.',
            'error'   => $message
        ], JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}

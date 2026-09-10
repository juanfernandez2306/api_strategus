<?php

namespace App\Middleware;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
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
            return ApiResponse::json(
                response: new SlimResponse(),
                statusCode: HttpStatus::FORBIDDEN,
                message: 'No tienes los privilegios necesarios para realizar esta acción.'
            );
        }

        return $handler->handle($request);
    }
}

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

    /**
     * El constructor recibe los IDs de los roles permitidos (ej: [1] para Admin, [1, 2] para Admin y Supervisor)
     */
    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // 1. Recuperar el role_id que el AuthMiddleware inyectó en la petición
        $userRole = $request->getAttribute('usuario_role');

        // 2. Verificar si el usuario tiene asignado un rol y si está dentro de los permitidos
        if ($userRole === null || !in_array((int)$userRole, $this->allowedRoles, true)) {
            return $this->forbiddenResponse('No tienes los privilegios necesarios para realizar esta acción.');
        }

        // Si su rol es válido, continúa al controlador
        return $handler->handle($request);
    }

    /**
     * Retorna una respuesta JSON estandarizada 403 Forbidden
     */
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
            ->withStatus(403); // 403 = Forbidden (Autenticado pero sin permisos)
        }
}
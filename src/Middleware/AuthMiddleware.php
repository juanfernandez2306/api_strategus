<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use App\Usuarios\Repository\UsuarioRepository;

class AuthMiddleware implements MiddlewareInterface
{
    private UsuarioRepository $repository;

    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // 1. Obtener la cabecera 'Authorization'
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Token de autenticación no proporcionado.');
        }

        // 2. Extraer el token del formato "Bearer <token>"
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Formato de autenticación inválido. Debe ser Bearer <token>.');
        }

        $plainToken = $matches[1];

        // 3. Buscar el token en la base de datos (aplicando hash sha256 si los guardas encriptados)
        // Nota: Reutiliza o adapta el método con el que gestionas tus tokens cotidianos
        $tokenHashed = hash('sha256', $plainToken);
        
        // Asumiendo que tienes un método similar a 'validateAccessToken' en tu repositorio:
        $accessToken = $this->repository->getAccessToken($tokenHashed);

        if (!$accessToken) {
            return $this->unauthorizedResponse('Token inválido o sesión expirada.');
        }

        if ((int)$accessToken['status'] !== 1) {
            return $this->unauthorizedResponse('Acceso denegado: El usuario se encuentra inactivo.');
        }

        if (empty($accessToken['email_verified_at'])) {
            return $this->unauthorizedResponse('Acceso denegado: Su dirección de correo electrónico no ha sido verificada.');
        }

        // 4. Validar si el token ya expiró en tiempo
        if (strtotime($accessToken['expires_at']) < time()) {
            return $this->unauthorizedResponse('La sesión ha expirado. Por favor, inicie sesión nuevamente.');
        }

        // 5. Inyectar los datos del usuario autenticado en los atributos de la petición
        // Esto permite que cualquier controlador posterior sepa QUIÉN está haciendo la petición
        $request = $request->withAttribute('usuario_id', $accessToken['usuario_id']);
        $request = $request->withAttribute('usuario_role', $accessToken['role_id'] ?? null);

        // Todo está en orden, permitimos que la petición continúe hacia el controlador
        return $handler->handle($request);
    }

    /**
     * Retorna una respuesta JSON estandarizada 401 Unauthorized
     */
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
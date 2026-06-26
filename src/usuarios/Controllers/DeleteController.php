<?php
namespace App\Usuarios\Controllers;

// Importamos las interfaces estándar para manejar peticiones y respuestas HTTP (PSR-7)
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;

class DeleteController
{
    private UsuarioRepository $repository;

    // Inyectamos el repositorio
    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    // Este método se ejecuta automáticamente cuando Slim apunta a esta clase
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // -------------------------------------------------------------
        // PASO 1: Capturar el ID del usuario desde la URL
        // -------------------------------------------------------------
        // El ID viaja en la URL (ej: /usuarios/5), Slim lo captura en el array $args
        $id = (int) $args['id'];

        // -------------------------------------------------------------
        // PASO 2: Solicitar el borrado al Repositorio
        // -------------------------------------------------------------
        // Ejecutamos el método delete() que devuelve un array con ['status', 'msg']
        $result = $this->repository->delete($id);

        // -------------------------------------------------------------
        // PASO 3: Procesar la respuesta y decidir el Código HTTP
        // -------------------------------------------------------------
        
        // Si status es false, significa que el borrado falló
        if (!$result['status']) {
            
            // Evaluamos la causa del error analizando el mensaje devuelto por el repositorio
            // Si el mensaje menciona "historial", sabemos que es por la restricción de clave foránea (Error 1451)
            if (strpos($result['msg'], 'historial') !== false) {
                $statusCode = 409; // 409 = Conflict (Conflicto de integridad en la base de datos)
            } else {
                $statusCode = 404; // 404 = Not Found (El usuario no existía en el sistema)
            }

            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $result['msg']
            ], $statusCode);
        }

        // -------------------------------------------------------------
        // PASO 4: Respuesta exitosa si se pudo borrar
        // -------------------------------------------------------------
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $result['msg'] // "Usuario eliminado físicamente..."
        ], 200); // 200 = OK
    }

    /**
     * Función auxiliar para estructurar respuestas JSON limpias
     */
    private function jsonResponse(Response $response, array $data, int $statusCode): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
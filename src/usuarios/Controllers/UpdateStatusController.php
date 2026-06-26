<?php
namespace App\Usuarios\Controllers;

// Importamos las interfaces estándar para manejar peticiones y respuestas HTTP (PSR-7)
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;

class UpdateStatusController
{
    private UsuarioRepository $repository;

    // Inyectamos el repositorio que ya maneja la base de datos
    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    // Este método se ejecuta automáticamente cuando Slim apunta a esta clase
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // -------------------------------------------------------------
        // PASO 1: Capturar el ID de la URL y el nuevo estado del cuerpo
        // -------------------------------------------------------------
        // El ID viaja seguro en la URL: /usuarios/{id}/status
        $id = (int) $args['id']; 

        // Convertimos el estado recibido a un entero (0 o 1)
        $nuevoEstado = (int) $args['status']; ;

        if (!in_array($nuevoEstado, [0, 1], true)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El campo status es requerido y debe ser 0 (inactivo) o 1 (activo).'
            ], 400); // 400 = Bad Request (Petición incorrecta)
        }

        // -------------------------------------------------------------
        // PASO 3: Ejecutar el cambio en el Repositorio
        // -------------------------------------------------------------
        // Ejecutamos el método de tu repositorio que hace el UPDATE
        $success = $this->repository->updateStatus($id, $nuevoEstado);

        if (!$success) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'No se pudo actualizar el estado del usuario en la base de datos.'
            ], 500); // 500 = Internal Server Error
        }

        // -------------------------------------------------------------
        // PASO 4: Responder con éxito al Frontend
        // -------------------------------------------------------------
        // Devolvemos una confirmación para que el interruptor cambie de color visualmente en la pantalla
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Estado del usuario actualizado correctamente.',
            'status'  => $nuevoEstado
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
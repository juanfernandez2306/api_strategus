<?php
namespace App\Usuarios\Controllers;

// Importamos las interfaces estándar para manejar peticiones y respuestas HTTP (PSR-7)
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;

class UpdateController
{
    private UsuarioRepository $repository;

    // Inyectamos el repositorio que ya tiene toda la lógica de la base de datos
    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    // Este método se ejecuta automáticamente cuando Slim apunta a esta clase
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // -------------------------------------------------------------
        // PASO 1: Capturar los datos que vienen desde el Frontend/Cliente
        // -------------------------------------------------------------
        
        // El ID del usuario viaja en la URL (ej: /usuarios/5), Slim lo guarda en $args
        $id = (int) $args['id']; 

        // Los datos del formulario (JSON o Post) viajan en el cuerpo de la petición
        $body = $request->getParsedBody();

        // Limpiamos los textos quitando espacios vacíos accidentales al inicio/final
        $nombre   = trim($body['nombre'] ?? '');
        $apellido = trim($body['apellido'] ?? '');
        $email    = filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL);
        // El role_id puede ser opcional o nulo, si viene lo convertimos a entero
        $roleId   = !empty($body['role_id']) ? (int) $body['role_id'] : null;
        $status   = !empty($body['status']) ? (int) $body['status'] : 0;

        // -------------------------------------------------------------
        // PASO 2: Validaciones básicas antes de tocar la Base de Datos
        // -------------------------------------------------------------
        if (empty($nombre) || empty($apellido)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El nombre y el apellido son obligatorios.'
            ], 400); // 400 = Bad Request (Petición incorrecta)
        }

        if (!$email) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Debes proporcionar un correo electrónico válido.'
            ], 400);
        }

        // -------------------------------------------------------------
        // PASO 3: Llamar al Repositorio y procesar el resultado
        // -------------------------------------------------------------
        
        // Preparamos el array estructurado que el repositorio espera recibir
        $datosParaActualizar = [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'email'    => $email,
            'role_id'  => $roleId,
            'status'    => $status,
        ];

        // Ejecutamos el método update que armamos y guardamos su array de respuesta
        $result = $this->repository->update($id, $datosParaActualizar);

        // -------------------------------------------------------------
        // PASO 4: Responder al Frontend según lo que pasó en la BD
        // -------------------------------------------------------------
        
        // Si el repositorio devolvió status = false (hubo un error de correo duplicado o rol inválido)
        if (!$result['status']) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $result['msg'] // "El correo ya está en uso" o "El rol no existe"
            ], 409); // 409 = Conflict (Conflicto de datos en el servidor)
        }

        // Si llegó aquí, todo salió perfecto (Hubo cambios o los datos eran idénticos, pero no hubo errores)
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $result['msg'] // "Datos actualizados con éxito" o "No se realizaron cambios"
        ], 200); // 200 = OK (Operación exitosa)
    }

    /**
     * Función auxiliar para estructurar respuestas JSON de forma ultra legible y limpia
     */
    private function jsonResponse(Response $response, array $data, int $statusCode): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
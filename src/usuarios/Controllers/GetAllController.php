<?php
namespace App\Usuarios\Controllers;

// Importamos las interfaces estándar para manejar peticiones y respuestas HTTP (PSR-7)
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;

class GetAllController
{
    private UsuarioRepository $repository;

    // Inyectamos el repositorio que ya sabe cómo hacer la consulta con el LEFT JOIN
    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    // Este método se ejecuta automáticamente cuando Slim apunta a esta clase
    public function __invoke(Request $request, Response $response): Response
    {
        // -------------------------------------------------------------
        // PASO 1: Llamar al Repositorio para obtener la lista
        // -------------------------------------------------------------
        // Ejecuta el método getAll() que corregimos (el que trae id, nombre, email y el nombre del rol)
        $usuarios = $this->repository->getAll();

        $usuariosFormateados = array_map(function ($user) {
            
            // Verificamos que existan las llaves para evitar un "Undefined array key"
            if (isset($user['nombre'])) {
                $user['nombre'] = mb_convert_case($user['nombre'], MB_CASE_TITLE, "UTF-8");
            }
            if (isset($user['apellido'])) {
                $user['apellido'] = mb_convert_case($user['apellido'], MB_CASE_TITLE, "UTF-8");
            }
            
            return $user;
        }, $usuarios);

        // -------------------------------------------------------------
        // PASO 2: Estructurar la respuesta para el Frontend
        // -------------------------------------------------------------
        // Armamos un array con una estructura clara: un indicador de éxito y la lista de datos
        $responseData = [
            'success' => true,
            'data'    => $usuariosFormateados
        ];

        // -------------------------------------------------------------
        // PASO 3: Enviar la respuesta en formato JSON
        // -------------------------------------------------------------
        // Convertimos el array a texto JSON (asegurando que los acentos se lean bien con JSON_UNESCAPED_UNICODE)
        $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));

        // Devolvemos la respuesta especificando que es un JSON y con un código 200 (Éxito)
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
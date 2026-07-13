<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;
use App\Usuarios\Validators\UpdateValidator;

class UpdateController
{
    private UsuarioRepository $repository;
    private UpdateValidator $validator;

    
    public function __construct(
        UsuarioRepository $repository, 
        UpdateValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id']; 
        $body = $request->getParsedBody() ?? [];

        // -------------------------------------------------------------
        // PASO 1: Normalización segura en minúsculas (UTF-8)
        // -------------------------------------------------------------
        $nombreRaw   = $body['nombre'] ?? '';
        $apellidoRaw = $body['apellido'] ?? '';

        $body['nombre']   = mb_strtolower(trim($nombreRaw), "UTF-8");
        $body['apellido'] = mb_strtolower(trim($apellidoRaw), "UTF-8");
        
        // Convertimos a tipos nativos para que Rakit valide correctamente tipos numéricos
        if (isset($body['role_id'])) {
            $body['role_id'] = (int) $body['role_id'];
        }
        if (isset($body['status'])) {
            $body['status'] = (int) $body['status'];
        }

        // -------------------------------------------------------------
        // PASO 2: Validación formal con Rakit
        // -------------------------------------------------------------
        $errores = $this->validator->validate($body, [
            'nombre'   => 'El nombre debe tener al menos 3 caracteres y contener solo letras.',
            'apellido' => 'El apellido debe tener al menos 3 caracteres y contener solo letras.',
            'role_id'  => 'Debe seleccionar un rol válido para el usuario.',
            'status'   => 'El estado del usuario no es válido.'
        ]);

        // Si la validación falla, disparamos el código de error controlado
        if ($errores) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors'  => $errores
            ], 420); // Código 420 adaptado a tus respuestas de validación
        }
        
        // -------------------------------------------------------------
        // PASO 3: Ejecutar la actualización en el Repositorio
        // -------------------------------------------------------------
        $datosParaActualizar = [
            'nombre'   => $body['nombre'],
            'apellido' => $body['apellido'],
            'role_id'  => $body['role_id'],
            'status'   => $body['status'],
        ];

        $result = $this->repository->update($id, $datosParaActualizar);

        // -------------------------------------------------------------
        // PASO 4: Responder al Frontend
        // -------------------------------------------------------------
        if (!$result['status']) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $result['msg']
            ], 409);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $result['msg']
        ], 200);
    }

    private function jsonResponse(Response $response, array $data, int $statusCode): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
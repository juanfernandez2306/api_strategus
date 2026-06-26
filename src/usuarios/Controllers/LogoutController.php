<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;

class LogoutController
{
    private UsuarioRepository $repository;

    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Obtener la cabecera 'Authorization' enviada por el cliente/Postman
        $authHeader = $request->getHeaderLine('Authorization');
        
        // 2. Validar que la cabecera exista y tenga el formato correcto "Bearer <token>"
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Token de autenticación no proporcionado o formato inválido.'
            ], 401);
        }

        // 3. Extraer el token en texto plano (el string largo que devolvió el login)
        $plainTextToken = $matches[1];

        // 4. Calcular el hash SHA-256 para poder buscarlo en la Base de Datos
        $hashedToken = hash('sha256', $plainTextToken);

        
        $this->repository->deleteToken($hashedToken);

        // 6. Responder con éxito
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Sesión cerrada correctamente (Token revocado).'
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
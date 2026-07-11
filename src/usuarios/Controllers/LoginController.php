<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;
use App\Usuarios\Validators\LoginValidator; // Asegúrate de que el namespace coincida

class LoginController
{
    private UsuarioRepository $repository;

    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Capturar el cuerpo de la petición POST
        $body = $request->getParsedBody() ?? [];

        $validator = new LoginValidator();

        $errors = $validator->validate($body);

        $errors = $validator->validate($body, [
            'password:regex' => 'La contraseña debe tener al menos 6 caracteres e incluir letras, números y un carácter especial (ej. @, #, $, !).'
        ]);
        
        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors'  => $errors
            ], 400);
        }

        $email = $body['email'];
        $password = $body['password'];
        // Identificador del dispositivo (por ejemplo: 'Postman', 'Web App', 'iPhone')
        $deviceName = $body['device_name'] ?? 'Generic Device'; 

        // 3. Buscar el registro de autenticación del usuario por su email
        $usuario = $this->repository->getAuthData($email);

        // 4. Verificar si el usuario existe y si la contraseña coincide con el Hash
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401); // 401 = Unauthorized
        }

        // 5. Verificar si el usuario está activo (status == 1)
        if ((int)$usuario['status'] === 0) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Su cuenta se encuentra inactiva. Contacte a un administrador.'
            ], 403); // 403 = Forbidden
        }

        // 6. Generar un Token de texto plano seguro (80 caracteres aleatorios)
        $plainTextToken = bin2hex(random_bytes(40));

        // 7. Persistir el Token encriptado en sha256 dentro de la base de datos (Expira en 30 días)
        $this->repository->storeToken($usuario['id'], $deviceName, $plainTextToken);

        // 8. Responder con éxito enviando el token en texto plano que guardará el Frontend
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Autenticación exitosa.',
            'token'   => $plainTextToken,
            'usuario' => [
                'id'     => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email'  => $email
            ]
        ], 200);
    }

    /**
     * Función auxiliar para estandarizar las respuestas en formato JSON
     */
    private function jsonResponse(Response $response, array $data, int $statusCode): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
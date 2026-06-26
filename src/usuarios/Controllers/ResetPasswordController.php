<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;
use App\Usuarios\Validators\ResetPasswordValidator;

class ResetPasswordController
{
    private UsuarioRepository $repository;
    private ResetPasswordValidator $validator;

    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
        $this->validator = new ResetPasswordValidator();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Capturar los datos recibidos del formulario/Postman
        $body = $request->getParsedBody() ?? [];

        // 2. Validar campos estructurales (incluyendo que password y password_confirm coincidan)
        $errores = $this->validator->validate($body);
        if (!empty($errores)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error de validación en los datos enviados.',
                'errors'  => $errores
            ], 400);
        }

        $email = trim($body['email']);
        $tokenParam = $body['token'];
        $newPassword = $body['password'];

        // 3. Verificar si existe un token de recuperación activo asociado a ese email
        $dbToken = $this->repository->getVerificationToken($email);

        if (!$dbToken) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El enlace de recuperación es inválido o ya ha sido utilizado.'
            ], 440); // 440 = Login Timeout / Expired
        }

        // 4. Validar la coincidencia exacta del hash del token por seguridad
        if (!hash_equals($dbToken['token'], hash('sha256', $tokenParam))) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El token de seguridad proporcionado es inválido.'
            ], 400);
        }

        // 5. Validar que el token temporal no haya expirado en el tiempo (límite de 2 horas)
        if (strtotime($dbToken['expires_at']) < time()) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El enlace de recuperación ha expirado. Por favor, solicita uno nuevo.'
            ], 410); // 410 = Gone
        }

        // 6. Aplicar hash seguro a la nueva contraseña elegida por el usuario
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        // 7. Delegar de forma segura la transacción completa al Repositorio
        // (Esto actualiza la clave, revoca sesiones activas y limpia el password_reset usado)
        $success = $this->repository->resetPasswordTransaction($email, $hashedPassword);

        if (!$success) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Ocurrió un error interno en el servidor al intentar actualizar tu contraseña.'
            ], 500);
        }

        // 8. Respuesta de éxito rotundo
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Tu contraseña ha sido restablecida con éxito. Ya puedes iniciar sesión con tus nuevas credenciales.'
        ], 200);
    }

    /**
     * Función auxiliar para estructurar respuestas JSON estandarizadas
     */
    private function jsonResponse(Response $response, array $data, int $statusCode): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
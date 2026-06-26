<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;

class VerifyEmailController
{
    private UsuarioRepository $repository;

    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // Capturar los parámetros de la URL (?token=...&email=...)
        $queryParams = $request->getQueryParams();
        $tokenParam = $queryParams['token'] ?? '';
        $emailParam = $queryParams['email'] ?? '';

        if (empty($tokenParam) || empty($emailParam)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Faltan parámetros requeridos para la verificación.'
            ], 400);
        }

        // 1. Buscar si existe un token pendiente para este email
        $dbToken = $this->repository->getVerificationToken($emailParam);

        if (!$dbToken) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'No se encontró ninguna solicitud de verificación para este correo o el enlace ya fue utilizado.'
            ], 404);
        }

        // 2. Validar que el token coincida (aplicando hash ya que lo guardamos encriptado)
        if (!hash_equals($dbToken['token'], hash('sha256', $tokenParam))) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El token de verificación proporcionado es inválido.'
            ], 400);
        }

        // 3. Validar si el token ya expiró
        if (strtotime($dbToken['expires_at']) < time()) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El enlace de verificación ha expirado. Por favor, solicite uno nuevo.'
            ], 410); // 410 = Gone (Ya no disponible)
        }

        // 4. Proceder a verificar el usuario y limpiar la tabla
        $success = $this->repository->verifyUserEmail($emailParam);

        if (!$success) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Hubo un error interno al intentar verificar tu cuenta.'
            ], 500);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => '¡Enhorabuena! Tu cuenta de correo ha sido verificada con éxito. Ya puedes iniciar sesión.'
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
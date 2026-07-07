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
        $queryParams = $request->getQueryParams();
        $tokenParam = $queryParams['token'] ?? '';
        $emailParam = $queryParams['email'] ?? '';

        // URL a la que redirigirás al usuario para que inicie sesión en tu frontend
        $loginUrl = $_ENV['FRONTEND_URL']; 

        if (empty($tokenParam) || empty($emailParam)) {
            return $this->htmlResponse($response, [
                'status_class' => 'error',
                'icon' => '❌',
                'title' => 'Enlace Inválido',
                'message' => 'Faltan parámetros requeridos para poder verificar tu cuenta.',
                'button_text' => 'Ir al Inicio',
                'button_link' => $loginUrl,
                'btn_class' => 'btn-error'
            ], 400);
        }

        // 1. Buscar si existe un token pendiente
        $dbToken = $this->repository->getVerificationToken($emailParam);

        if (!$dbToken) {
            return $this->htmlResponse($response, [
                'status_class' => 'error',
                'icon' => '🔍',
                'title' => 'Enlace no encontrado',
                'message' => 'No se encontró ninguna solicitud de verificación o el enlace ya fue utilizado.',
                'button_text' => 'Ir al Login',
                'button_link' => $loginUrl,
                'btn_class' => 'btn-error'
            ], 404);
        }

        // 2. Validar que el token coincida
        if (!hash_equals($dbToken['token'], hash('sha256', $tokenParam))) {
            return $this->htmlResponse($response, [
                'status_class' => 'error',
                'icon' => '⚠️',
                'title' => 'Token Inválido',
                'message' => 'El token de verificación proporcionado es incorrecto o ha sido alterado.',
                'button_text' => 'Reintentar',
                'button_link' => $loginUrl,
                'btn_class' => 'btn-error'
            ], 400);
        }

        // 3. Validar si el token ya expiró
        if (strtotime($dbToken['expires_at']) < time()) {
            return $this->htmlResponse($response, [
                'status_class' => 'error',
                'icon' => '⏰',
                'title' => 'Enlace Expirado',
                'message' => 'El enlace de verificación ha caducado. Por favor, solicita uno nuevo desde la plataforma.',
                'button_text' => 'Solicitar nuevo enlace',
                'button_link' => $loginUrl,
                'btn_class' => 'btn-error'
            ], 410);
        }

        // 4. Proceder a verificar el usuario
        $success = $this->repository->verifyUserEmail($emailParam);

        if (!$success) {
            return $this->htmlResponse($response, [
                'status_class' => 'error',
                'icon' => '⚙️',
                'title' => 'Error Interno',
                'message' => 'Hubo un error en el servidor al intentar verificar tu cuenta. Por favor, inténtalo más tarde.',
                'button_text' => 'Volver',
                'button_link' => $loginUrl,
                'btn_class' => 'btn-error'
            ], 500);
        }

        // ÉXITO
        return $this->htmlResponse($response, [
            'status_class' => 'success',
            'icon' => '✔',
            'title' => '¡Cuenta Verificada!',
            'message' => '¡Enhorabuena! Tu cuenta de correo ha sido verificada con éxito. Ya puedes iniciar sesión de forma segura.',
            'button_text' => 'Iniciar Sesión',
            'button_link' => $loginUrl,
            'btn_class' => ''
        ], 200);
    }

    /**
     * Renderiza la plantilla HTML inyectando los datos dinámicos
     */
    private function htmlResponse(Response $response, array $data, int $statusCode): Response
    {
        // Puedes guardar el HTML anterior en un archivo (ej: template.html) y cargarlo con file_get_contents
        // O dejarlo aquí en una variable heredoc para mantenerlo todo junto de momento:
        $templatePathHTML = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'Emails' . DIRECTORY_SEPARATOR . 'response-email.html';
        $html = file_get_contents($templatePathHTML);

        // Reemplazamos los placeholders por los valores reales
        $html = str_replace('{{STATUS_CLASS}}', $data['status_class'], $html);
        $html = str_replace('{{ICON}}', $data['icon'], $html);
        $html = str_replace('{{TITLE}}', $data['title'], $html);
        $html = str_replace('{{MESSAGE}}', $data['message'], $html);
        $html = str_replace('{{BUTTON_LINK}}', $data['button_link'], $html);
        $html = str_replace('{{BUTTON_TEXT}}', $data['button_text'], $html);
        $html = str_replace('{{BTN_CLASS}}', $data['btn_class'], $html);

        $response->getBody()->write($html);
        
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($statusCode);
    }
}
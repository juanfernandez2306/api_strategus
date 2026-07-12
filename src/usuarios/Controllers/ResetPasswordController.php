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
        $this->validator = new ResetPasswordValidator(); // Instancia el validador estructural
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $method = $request->getMethod(); // Enrutamiento interno por verbo HTTP

        if ($method === 'GET') {
            return $this->renderForm($request, $response);
        }

        if ($method === 'POST') {
            return $this->handleReset($request, $response);
        }

        return $response->withStatus(405); // Método no permitido
    }

    /**
     * Gestión de GET: Carga y sirve la vista del formulario HTML con datos reales
     */
    private function renderForm(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams(); // Bloqueo preventivo en la UI si el enlace está roto
        $token = $queryParams['token'] ?? '';
        $email = $queryParams['email'] ?? '';

        if (empty($token) || empty($email)) {
            return $this->renderMessagePage(
                $response, 
                'Enlace inválido', 
                'Los parámetros requeridos para la recuperación están incompletos.', 
                'error', 
                400
            );
        }

        // 1. Verificación previa del token en la base de datos antes de pintar el formulario
        $dbToken = $this->repository->getVerificationToken($email);

        if (!$dbToken || !hash_equals($dbToken['token'], hash('sha256', $token)) || strtotime($dbToken['expires_at']) < time()) {
            return $this->renderMessagePage(
                $response, 
                'Enlace Inválido o Expirado', 
                'El enlace de recuperación ya no es válido, ya fue utilizado o ha caducado. Solicita una nueva recuperación.', 
                'error', 
                400
            );
        }

        // 2. OBTENER LOS DATOS REALES DESDE LA BASE DE DATOS
        $usuario = $this->repository->findByEmail($email); 
        $nombreCompleto = 'Usuario GEPAD'; // Valor por defecto fallback

        if ($usuario) {
            // Concatenamos el nombre y apellido devueltos por la sentencia SQL
            $nombre = !empty($usuario['nombre']) ? trim($usuario['nombre']) : '';
            $apellido = !empty($usuario['apellido']) ? trim($usuario['apellido']) : '';
            
            $nombreCompleto = trim($nombre . ' ' . $apellido);
            
            if (empty($nombreCompleto)) {
                $nombreCompleto = $usuario['email'];
            }
        }

        // 3. Cargar la plantilla HTML
        $htmlPath = __DIR__ . '/../Views/Emails/reset-password.html';
        
        if (file_exists($htmlPath)) {
            $htmlContent = file_get_contents($htmlPath);
            
            // 4. INYECCIÓN DINÁMICA DE SERVIDOR (Reemplazo controlado en el String del Front)
            // Reemplaza las lecturas de los parámetros URL del front para inyectar directo los del Backend
            $htmlContent = str_replace("const emailParam = params.get('email') || '';", "const emailParam = '{$email}';", $htmlContent);
            $htmlContent = str_replace("const tokenParam = params.get('token') || '';", "const tokenParam = '{$token}';", $htmlContent);
            
            // Sobreescribimos la inferencia por split('@') utilizando el nombre y apellido real de tu DB
            $htmlContent = str_replace(
                "const nombreInferido = emailParam.split('@')[0].replace(/[^a-zA-Z]/g, ' ');", 
                "const nombreInferido = '{$nombreCompleto}';", 
                $htmlContent
            );
        } else {
            return $this->renderMessagePage(
                $response, 
                'Error de Sistema', 
                'El formulario de restablecimiento no fue encontrado en el servidor.', 
                'error', 
                500
            );
        }
        
        $response->getBody()->write($htmlContent);
        return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
    }

    /**
     * Gestión de POST: Procesa el cambio físico de la contraseña
     */
    private function handleReset(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? []; // 1. Capturar los datos del payload del formulario

        // 2. Ejecutar validación Rakit
        $errores = $this->validator->validate($body);
        if (!empty($errores)) {
            return $this->renderMessagePage(
                $response, 
                'Error de Validación', 
                'Los datos enviados no cumplen con los requisitos mínimos de seguridad o las contraseñas no coinciden.', 
                'error', 
                400,
                "<a href='javascript:history.back()' class='btn btn-error'>← Regresar al formulario</a>"
            );
        }

        $email = trim($body['email']);
        $tokenParam = $body['token'];
        $newPassword = $body['password'];

        // 3. Buscar el token activo en la base de datos
        $dbToken = $this->repository->getVerificationToken($email);

        if (!$dbToken) {
            return $this->renderMessagePage(
                $response, 
                'Enlace Inválido', 
                'El enlace es inválido o ya ha sido utilizado anteriormente.', 
                'error', 
                440
            );
        }

        // 4. Validar integridad criptográfica (hash_equals)
        if (!hash_equals($dbToken['token'], hash('sha256', $tokenParam))) {
            return $this->renderMessagePage(
                $response, 
                'Token Inválido', 
                'El token de seguridad proporcionado no es válido o fue alterado.', 
                'error', 
                400
            );
        }

        // 5. Validar expiración de tiempo (2 horas límite)
        if (strtotime($dbToken['expires_at']) < time()) {
            return $this->renderMessagePage(
                $response, 
                'Enlace Expirado', 
                'El enlace ha expirado. Por favor, solicita una nueva recuperación.', 
                'error', 
                410
            );
        }

        // 6. Cifrar de forma segura la contraseña con Bcrypt
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        // 7. Guardar en Base de Datos vía transacción en el repositorio
        $success = $this->repository->resetPasswordTransaction($email, $hashedPassword);

        if (!$success) {
            return $this->renderMessagePage(
                $response, 
                'Error de Servidor', 
                'Ocurrió un error interno al intentar procesar la solicitud. Por favor intente más tarde.', 
                'error', 
                500
            );
        }

        // 8. Mensaje de éxito visual final adaptado para Mobile-First
        return $this->renderMessagePage(
            $response, 
            '¡Contraseña Actualizada!', 
            'Tu nueva clave de acceso ya está activa. Puedes cerrar esta pestaña de forma segura y abrir la aplicación para iniciar sesión con normalidad.', 
            'success', 
            200
        );
    }

    /**
     * Generador dinámico de layouts limpios y responsivos (Mobile-First) para respuestas controladas.
     */
    private function renderMessagePage(Response $response, string $title, string $message, string $type, int $statusCode, string $buttonHtml = ''): Response
    {
        $primaryColor = ($type === 'success') ? '#10b981' : '#e11d48';
        $bgColor = ($type === 'success') ? '#f0fdf4' : '#fef2f2';
        $borderColor = ($type === 'success') ? '#bbf7d0' : '#fca5a5';

        $html = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { font-family: sans-serif; margin: 0; padding: 15px; background-color: #f9f9f9; display: flex; justify-content: center; align-items: center; min-height: 90vh; box-sizing: border-box; }
                .card { max-width: 450px; width: 100%; padding: 25px; border: 1px solid #eee; border-radius: 8px; background-color: #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; box-sizing: border-box; }
                .status-box { background-color: {$bgColor}; border: 1px solid {$borderColor}; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
                h2 { color: {$primaryColor}; margin-top: 0; font-size: 22px; }
                p { color: #4b5563; font-size: 15px; line-height: 1.5; margin: 0 0 15px 0; }
                p:last-child { margin-bottom: 0; }
                .btn { display: inline-block; width: 100%; padding: 12px; border-radius: 4px; font-weight: bold; font-size: 16px; text-decoration: none; box-sizing: border-box; transition: background 0.2s; }
                .btn-error { background: #e11d48; color: white; border: none; cursor: pointer; }
                .btn-error:hover { background: #be123c; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='status-box'>
                    <h2>{$title}</h2>
                </div>
                <p>{$message}</p>
                <div style='margin-top: 20px;'>{$buttonHtml}</div>
            </div>
        </body>
        </html>
        ";

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html')->withStatus($statusCode);
    }
}
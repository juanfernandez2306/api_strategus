<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;
use App\Usuarios\Services\Mailer;

class ForgotPasswordController
{
    private UsuarioRepository $repository;
    private Mailer $mailer;

    public function __construct(UsuarioRepository $repository)
    {
        $this->repository = $repository;
        $this->mailer = new Mailer();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $email = trim($body['email'] ?? '');

        // 1. Validar que el campo email no venga vacío
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Debe proporcionar una dirección de correo electrónico válida.'
            ], 400);
        }

        // 2. Si el correo NO existe en la base de datos, por seguridad no le decimos al atacante 
        // "este correo no existe". Es mejor dar una respuesta genérica para evitar enumeración de usuarios.
        if (!$this->repository->existsEmail($email)) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Si el correo electrónico coincide con una cuenta registrada, recibirá un enlace de recuperación en unos minutos.'
            ], 200);
        }

        // 3. Generar token único de recuperación
        $resetToken = bin2hex(random_bytes(32));
        $this->repository->storePasswordResetToken($email, $resetToken);

        // 4. Construir URL dinámica usando el .env
        $baseUrl = $_ENV['APP_URL'];
        $enlaceRecuperacion = $baseUrl . "/usuarios/reset-password?token=" . $resetToken . "&email=" . urlencode($email);

        // 5. Cargar plantilla HTML externa
        $templatePath = __DIR__ . '/../Views/Emails/forgot-password.html';
        if (file_exists($templatePath)) {
            $htmlBody = file_get_contents($templatePath);
            $htmlBody = str_replace('{{enlace}}', $enlaceRecuperacion, $htmlBody);
        } else {
            $htmlBody = "Para restablecer tu contraseña ve al siguiente enlace: " . $enlaceRecuperacion;
        }

        // 6. Enviar correo electrónico
        $this->mailer->send(
            $email,
            "Usuario GESTION PALMA DIGITAL",
            "Restablecer tu contraseña - SIGEPAD",
            $htmlBody
        );

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Si el correo electrónico coincide con una cuenta registrada, recibirá un enlace de recuperación en unos minutos.'
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
<?php
namespace App\Usuarios\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Usuarios\Repository\UsuarioRepository;
use App\Usuarios\Validators\CreateValidator;
use App\Usuarios\Services\Mailer;

class CreateController
{
    private UsuarioRepository $repository;
    private CreateValidator $validator;
    private Mailer $mailer;

    // Inyectamos tanto el repositorio como el validador
    public function __construct(UsuarioRepository $repository, CreateValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
        $this->mailer = new Mailer();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // -------------------------------------------------------------
        // PASO 1: Capturar los datos del cuerpo de la petición
        // -------------------------------------------------------------
        $body = $request->getParsedBody() ?? [];

        // -------------------------------------------------------------
        // PASO 2: Validar datos con Rakit mediante tu CreateValidator
        // -------------------------------------------------------------
        $errores = $this->validator->validate($body);

        $errores = $this->validator->validate($body, [
            'nombre' => 'El nombre debe tener al menos 3 caracteres y contener solo letras.',
            'apellido' => 'El apellido debe tener al menos 3 caracteres y contener solo letras.',
            'password_confirm:same' => 'Las contraseñas ingresadas no coinciden.',
            'password:regex' => 'La contraseña debe tener al menos 6 caracteres e incluir letras, números y un carácter especial (ej. @, #, $, !).'
        ]);

        if (!empty($errores)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error de validación en los datos enviados.',
                'errors'  => $errores // Devuelve la lista con el primer error de cada campo
            ], 400); // 400 = Bad Request
        }

        // Si pasa la validación, limpiamos los datos para la base de datos
        if (isset($body['nombre'])) {
            $body['nombre'] = mb_strtolower(trim($body['nombre']), "UTF-8");
        }
        if (isset($body['apellido'])) {
            $body['apellido'] = mb_strtolower(trim($body['apellido']), "UTF-8");
        }
        if (isset($body['email'])) {
            $body['email'] = mb_strtolower(trim($body['email']), "UTF-8");
        }

        $nombre   = $body['nombre'];
        $apellido = $body['apellido'];
        $email    = $body['email'];
        $password = $body['password'];

        // -------------------------------------------------------------
        // PASO 3: Validar que el correo NO esté registrado todavía
        // -------------------------------------------------------------
        if ($this->repository->existsEmail($email)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'El correo electrónico ya se encuentra registrado en el sistema.'
            ], 409); // 409 = Conflict
        }

        // -------------------------------------------------------------
        // PASO 4: Guardar el nuevo usuario en la Base de Datos
        // -------------------------------------------------------------
        $nuevoUsuario = [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'email'    => $email,
            'password' => $password // Tu repositorio aplica password_hash
        ];

        $success = $this->repository->create($nuevoUsuario);

        if (!$success) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Hubo un error interno y no se pudo registrar al usuario.'
            ], 500); // 500 = Internal Server Error
        }

        // -------------------------------------------------------------
        // 🚀 NUEVO PASO: GENERAR TOKEN Y DISPARAR CORREO DE VERIFICACIÓN
        // -------------------------------------------------------------
        
        // 1. Creamos un token aleatorio seguro de 64 caracteres en texto plano
        $verificationToken = bin2hex(random_bytes(32));
        
        // 2. Lo guardamos en la tabla 'password_resets' llamando al método del repositorio
        // (Nota: Asegúrate de tener implementado el método 'storeVerificationToken' en tu repositorio)
        $this->repository->storeVerificationToken($email, $verificationToken);

        $baseUrl = $_ENV['APP_URL'];

        // 3. Estructuramos la URL de verificación que apuntará a tu API o tu Frontend
        $enlaceVerificacion = $baseUrl . "/usuarios/verify-email?token=" . $verificationToken . "&email=" . urlencode($email);
        
        // 4. Diseñamos la plantilla HTML del correo electrónico
        $templatePath = __DIR__ . '/../Views/Emails/verify-email.html';
        
        if (file_exists($templatePath)) {
            $htmlBody = file_get_contents($templatePath);
            
            // Reemplazamos los comodines por las variables reales del controlador
            $htmlBody = str_replace('{{nombre}}', htmlspecialchars($nombre), $htmlBody);
            $htmlBody = str_replace('{{enlace}}', $enlaceVerificacion, $htmlBody);
        } else {
            // Un respaldo simple por si el archivo llega a borrarse por accidente
            $htmlBody = "Por favor verifica tu cuenta en el siguiente enlace: " . $enlaceVerificacion;
        }

        // 5. Enviamos el correo de manera asíncrona al cliente HTTP
        $this->mailer->send(
            $email, 
            $nombre . " " . $apellido, 
            "Verifica tu cuenta de correo - API-GEPAD", 
            $htmlBody
        );

        // -------------------------------------------------------------
        // PASO 5: Respuesta exitosa (Creado)
        // -------------------------------------------------------------
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Usuario registrado con éxito. Por favor, revisa tu bandeja de entrada para verificar tu cuenta de correo electrónico.'
        ], 201);

    }

    /**
     * Función auxiliar para estructurar respuestas JSON limpias
     */
    private function jsonResponse(Response $response, array $data, int $statusCode): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
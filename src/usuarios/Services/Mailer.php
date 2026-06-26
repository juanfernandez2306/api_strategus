<?php
namespace App\Usuarios\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private array $config;

    public function __construct()
    {
        // Reemplaza estos valores con los que te dio Mailtrap
        $this->config = [
            'host'     => $_ENV['MAIL_HOST'],
            'port'     => (int) $_ENV['MAIL_PORT'],
            'username' => $_ENV['MAIL_USER'],
            'password' => $_ENV['MAIL_PASSWORD'],
            'from'     => $_ENV['MAIL_FROM_ADDRESS'],
            'from_name'=> $_ENV['MAIL_FROM_NAME']
        ];
    }

    /**
     * Método genérico para enviar correos electrónicos
     */
    public function send(string $toEmail, string $toName, string $subject, string $bodyHTML): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Configuración del Servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->Port       = $this->config['port'];
            $mail->CharSet    = 'UTF-8';

            // Destinatarios
            $mail->setFrom($this->config['from'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);

            // Contenido del Correo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHTML;

            $mail->send();
            return true;
        } catch (Exception $e) {
            // En desarrollo, puedes registrar el error en los logs si lo deseas: $e->getMessage()
            return false;
        }
    }
}
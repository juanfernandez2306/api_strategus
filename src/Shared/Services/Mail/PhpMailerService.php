<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PhpMailerService implements InterfaceMailService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [
            'host'       => $_ENV['MAIL_HOST'],
            'port'       => (int) ($_ENV['MAIL_PORT']),
            'username'   => $_ENV['MAIL_USER'],
            'password'   => $_ENV['MAIL_PASSWORD'],
            'from'       => $_ENV['MAIL_FROM_ADDRESS'],
            'from_name'  => $_ENV['MAIL_FROM_NAME'],
            'encryption' => $_ENV['MAIL_ENCRYPTION']
        ];
    }

    public function send(string $toEmail, string $toName, string $subject, string $bodyHTML): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->Port       = $this->config['port'];
            $mail->CharSet    = 'UTF-8';

            if (!empty($this->config['encryption'])) {
                $mail->SMTPSecure = $this->config['encryption'];
            }

            $mail->setFrom($this->config['from'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHTML;

            return $mail->send();
        } catch (Exception $e) {
            error_log("Error al enviar email a {$toEmail}: " . $e->getMessage());
            return false;
        }
    }
}
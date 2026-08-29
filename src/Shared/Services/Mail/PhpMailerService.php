<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PhpMailerService implements MailServiceInterface
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, ?array $config = null)
    {
        $this->logger = $logger;
        $this->config = $config ?? $this->loadEnvironmentConfig();
    }

    private function loadEnvironmentConfig(): array
    {
        $requiredKeys = [
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USER',
            'MAIL_PASSWORD',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME'
        ];

        foreach ($requiredKeys as $key) {
            if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
                $errorMessage = sprintf(
                    "Error de configuración: La variable de entorno '%s' no está definida o está vacía.",
                    $key
                );

                $this->logger->critical($errorMessage);

                throw new RuntimeException($errorMessage);
            }
        }

        return [
            'host'       => $_ENV['MAIL_HOST'],
            'port'       => (int) $_ENV['MAIL_PORT'],
            'username'   => $_ENV['MAIL_USER'],
            'password'   => $_ENV['MAIL_PASSWORD'],
            'from'       => $_ENV['MAIL_FROM_ADDRESS'],
            'from_name'  => $_ENV['MAIL_FROM_NAME'],
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS
        ];
    }

    public function send(
        string|array $toEmail,
        string|array $toName,
        string $subject,
        string $bodyHTML
    ): bool {
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

            // Cast a array para uniformar el tratamiento de recipient(s)
            $emails = (array) $toEmail;
            $names  = (array) $toName;

            foreach ($emails as $index => $email) {
                $name = $names[$index] ?? '';
                $mail->addAddress($email, $name);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHTML;

            return $mail->send();
        } catch (PHPMailerException $e) {
            $recipientsLog = is_array($toEmail) ? implode(', ', $toEmail) : $toEmail;

            $this->logger->error("Error al enviar email a {$recipientsLog}: " . $e->getMessage(), [
                'recipients' => $toEmail,
                'subject'    => $subject,
                'exception'  => $e
            ]);

            return false;
        }
    }
}

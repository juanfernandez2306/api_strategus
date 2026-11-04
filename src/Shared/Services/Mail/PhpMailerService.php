<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

use App\Shared\DTOs\MailDTO;
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
        $this->config = $config;

        $this->validateConfig();
    }

    private function validateConfig(): void
    {
        $requiredKeys = ['host', 'port', 'username', 'password', 'from', 'from_name'];
        foreach ($requiredKeys as $key) {
            if (empty($this->config[$key])) {
                $msg = sprintf("Error de configuración de correo: La clave '%s' está vacía.", $key);
                $this->logger->critical($msg);
                throw new RuntimeException($msg);
            }
        }
    }

    public function send(MailDTO $mailDTO): bool
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

            $emails = (array) $mailDTO->toEmail;
            $names  = (array) $mailDTO->toName;

            foreach ($emails as $index => $email) {
                $name = $names[$index];
                $mail->addAddress($email, $name);
            }

            $mail->isHTML(true);
            $mail->Subject = $mailDTO->subject;
            $mail->Body    = $mailDTO->bodyHTML;

            return $mail->send();
        } catch (PHPMailerException $e) {
            $recipientsLog = is_array($mailDTO->toEmail) ?
                implode(', ', $mailDTO->toEmail) :
                $mailDTO->toEmail;

            $this->logger->error(
                "Error al enviar email a {$recipientsLog}: " . $e->getMessage(),
                [
                    'recipients' => $mailDTO->toEmail,
                    'subject'    => $mailDTO->subject,
                    'exception'  => $e
                    ]
            );

            return false;
        }
    }
}

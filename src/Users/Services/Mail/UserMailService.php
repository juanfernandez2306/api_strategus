<?php

declare(strict_types=1);

namespace App\Users\Services\Mail;

use App\Shared\Services\Mail\MailServiceInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class UserMailService implements UserMailServiceInterface
{
    private MailServiceInterface $mailService;
    private LoggerInterface $logger;
    private string $logoUrl;

    public function __construct(
        MailServiceInterface $mailService,
        LoggerInterface $logger
    ) {
        $this->mailService = $mailService;
        $this->logger = $logger;

        $this->logoUrl = $this->resolveLogoUrl();
    }

    private function resolveLogoUrl(): string
    {
        $urlAppBackend = $_ENV['APP_URL'];

        if (empty($urlAppBackend)) {
            $msg = "La variable de entorno 'APP_URL' no está definida o está vacía.";
            $this->logger->critical($msg);
            throw new RuntimeException($msg);
        }

        if ($urlAppBackend === 'http://localhost/api-gepad') {
            $urlAppBackend = 'https://api.juanfgeo.com';
        }

        return rtrim($urlAppBackend, '/') . '/assets/img/logo_sigepad.png';
    }

    public function send(array $emailContext): bool
    {
        try {
            $templateContext = array_merge([
                'logoUrl' => $this->logoUrl,
            ], $emailContext);

            $htmlBody = view($emailContext['viewTemplate'], $templateContext);

            return $this->mailService->send(
                $emailContext['toEmail'],
                $emailContext['userFullName'],
                $emailContext['subject'],
                $htmlBody
            );
        } catch (RuntimeException $e) {
            $this->logger->error(sprintf(
                "Error al enviar correo '%s' para %s: %s",
                $emailContext['subject'] ?? 'Sin Asunto',
                $emailContext['toEmail'] ?? 'Sin Email',
                $e->getMessage()
            ));

            throw $e;
        }
    }
}

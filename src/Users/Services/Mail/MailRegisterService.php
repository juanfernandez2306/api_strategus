<?php

declare(strict_types=1);

namespace App\Users\Services\Mail;

use App\Shared\Services\Mail\InterfaceMailService;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MailRegisterService implements InterfaceMailRegister
{
    private InterfaceMailService $mailService;
    private LoggerInterface $logger;
    private string $templatePath;
    private string $frontendUrl;

    public function __construct(
        InterfaceMailService $mailService,
        LoggerInterface $logger,
        ?string $frontendUrl = null
    ) {
        $this->mailService = $mailService;
        $this->logger = $logger;
        $this->templatePath = dirname(__DIR__, 2) . '/Views/Emails/VerifyEmail.html';
        $this->frontendUrl = $this->loadFrontendUrl($frontendUrl);
    }

    private function loadFrontendUrl(?string $frontendUrl): string
    {
        try {
            $url = $frontendUrl ?? ($_ENV['FRONTEND_URL'] ?? '');

            empty($url) && throw new RuntimeException("La variable de entorno 'FRONTEND_URL' no está definida o está vacía.");

            return rtrim($url, '/');
        } catch (RuntimeException $e) {
            $this->logger->critical($e->getMessage());
            throw $e;
        }
    }

    public function send(string $toEmail, string $toName, string $tokenPlain): bool
    {
        try {
            (!file_exists($this->templatePath)) && throw new RuntimeException("No se encontró el archivo de la plantilla HTML en la ruta: {$this->templatePath}");

            $htmlBody = file_get_contents($this->templatePath);
            $verificationUrl = "{$this->frontendUrl}/verify/email?token={$tokenPlain}";

            $htmlBody = str_replace(
                ['{{nombre}}', '{{enlace}}'],
                [htmlspecialchars($toName, ENT_QUOTES, 'UTF-8'), htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8')],
                $htmlBody
            );

            return $this->mailService->send(
                $toEmail,
                $toName,
                'Verifica tu cuenta - GESTIÓN DE PALMA DIGITAL',
                $htmlBody
            );

        } catch (RuntimeException $e) {
            $this->logger->error("No se encontró la plantilla de correo de verificación para {$toEmail}: " . $e->getMessage());
            throw $e;
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Users\Services;

use App\Shared\DTOs\SendTemplatedMailDTO;
use App\Shared\Services\Mail\TemplatedMailServiceInterface;
use RuntimeException;

class AuthEmailService
{
    public function __construct(
        private TemplatedMailServiceInterface $templatedMailService
    ) {}

    /**
     * @param string|array<int, string> $toEmail
     * @param array<string, mixed> $viewData
     */
    public function sendGenericEmail(
        string|array $toEmail,
        string $toName,
        string $subject,
        string $viewTemplate,
        array $viewData
    ): bool {
        $mailDTO = new SendTemplatedMailDTO(
            toEmail: $toEmail,
            toName: $toName,
            subject: $subject,
            viewTemplate: $viewTemplate,
            viewData: $viewData
        );

        return $this->templatedMailService->send($mailDTO);
    }

    public static function generateUrl(string $path, string $tokenPlain): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ??
            throw new RuntimeException("La variable 'FRONTEND_URL' no está definida.");

        return sprintf(
            '%s/%s?token=%s',
            rtrim($frontendUrl, '/'),
            ltrim($path, '/'),
            urlencode($tokenPlain)
        );
    }
}

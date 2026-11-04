<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

class SendTemplatedMailDTO
{
    /**
     * @param string|array<int, string> $toEmail
     * @param string|array<int, string> $toName
     * @param array<string, mixed> $viewData Variables específicas que usará la plantilla Twig
     */
    public function __construct(
        public readonly string|array $toEmail,
        public readonly string|array $toName,
        public readonly string $subject,
        public readonly string $viewTemplate,
        public readonly array $viewData = []
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

class MailDTO
{
    /**
     * @param string|array<int, string> $toEmail
     * @param string|array<int, string> $toName
     */
    public function __construct(
        public readonly string|array $toEmail,
        public readonly string|array $toName,
        public readonly string $subject,
        public readonly string $bodyHTML
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): self
    {
        return new self(
            toEmail: $data['toEmail'],
            toName: $data['toName'],
            subject: $data['subject'],
            bodyHTML: $data['bodyHTML']
        );
    }
}

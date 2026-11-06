<?php

declare(strict_types=1);

namespace App\Users\DTOs\Auth;

use DateTimeImmutable;

class PasswordResetTokenDTO
{
    public function __construct(
        public readonly int $tokenId,
        public readonly int $userId,
        public readonly DateTimeImmutable $tokenExpiresAt,
        public readonly ?DateTimeImmutable $emailVerifiedAt,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tokenId: (int) $data['id'],
            userId: (int) $data['user_id'],
            tokenExpiresAt: new DateTimeImmutable($data['expires_at']),
            emailVerifiedAt: isset($data['email_verified_at'])
                ? new DateTimeImmutable($data['email_verified_at'])
                : null,
            firstName: (string) $data['first_name'],
            lastName: (string) $data['last_name'],
            email: mb_strtolower(trim($data['email']))
        );
    }

    public function getFullName(): string
    {
        $firstName = mb_convert_case(trim($this->firstName), MB_CASE_TITLE, 'UTF-8');
        $lastName  = mb_convert_case(trim($this->lastName), MB_CASE_TITLE, 'UTF-8');

        return "{$firstName} {$lastName}";
    }

    public function isTokenExpired(): bool
    {
        return new DateTimeImmutable() > $this->tokenExpiresAt;
    }
}

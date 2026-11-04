<?php

declare(strict_types=1);

namespace App\Users\DTOs\Auth;

use DateTimeImmutable;

class FindByEmailAndTokenDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $emailVerifiedAt,
        public readonly string $firstName,
        public readonly string $lastName
    ) {
    }

    public static function fromArray(array $data): ?self
    {
        if (empty($data)) {
            return null;
        }

        return new self(
            id: (int) ($data['id']),
            userId: (int) ($data['user_id']),
            expiresAt: new DateTimeImmutable($data['expires_at']),
            emailVerifiedAt: !empty($data['email_verified_at'])
                ? new DateTimeImmutable($data['email_verified_at'])
                : null,
            firstName: (string) ($data['first_name']),
            lastName: (string) ($data['last_name'])
        );
    }

    public function getFullName(): string
    {
        $firstName = mb_convert_case($this->firstName, MB_CASE_TITLE, 'UTF-8');
        $lastName  = mb_convert_case($this->lastName, MB_CASE_TITLE, 'UTF-8');

        return trim("{$firstName} {$lastName}");
    }
}

<?php

declare(strict_types=1);

namespace App\Users\DTOs\Auth;

use DateTimeImmutable;

class UserDTO
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $userId = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly ?DateTimeImmutable $emailVerifiedAt = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?string $newPassword = null,
        public readonly ?string $birthDate = null,
        public readonly ?string $dniType = null,
        public readonly ?string $dniNumber = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $token = null
    ) {
    }

    public static function fromArray(array $data): ?self
    {
        if (empty($data)) {
            return null;
        }

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            emailVerifiedAt: !empty($data['email_verified_at'])
                ? new DateTimeImmutable($data['email_verified_at'])
                : null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            email: isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            password: $data['password'] ?? null,
            newPassword: $data['newPassword'] ?? null,
            birthDate: $data['birth_date'] ?? null,
            dniType: $data['dni_type'] ?? null,
            dniNumber: $data['dni_number'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            token: isset($data['token']) ? trim($data['token']) : null
        );
    }

    public function getFullName(): ?string
    {
        if ($this->firstName === null && $this->lastName === null) {
            return null;
        }

        $firstName = $this->firstName ? mb_convert_case($this->firstName, MB_CASE_TITLE, 'UTF-8') : '';
        $lastName  = $this->lastName ? mb_convert_case($this->lastName, MB_CASE_TITLE, 'UTF-8') : '';

        return trim("{$firstName} {$lastName}");
    }
}

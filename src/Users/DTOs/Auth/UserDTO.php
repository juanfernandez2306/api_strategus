<?php

declare(strict_types=1);

namespace App\Users\DTOs\Auth;

use DateTimeImmutable;

readonly class UserDTO
{
    public function __construct(
        public int $id,
        public int $roleId,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public bool $isActive,
        public ?DateTimeImmutable $emailVerifiedAt = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            roleId: (int) $data['role_id'],
            firstName: (string) $data['first_name'],
            lastName: (string) $data['last_name'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            isActive: (bool) $data['is_active'],
            emailVerifiedAt: !empty($data['email_verified_at'])
                ? new DateTimeImmutable((string) $data['email_verified_at'])
                : null
        );
    }


    public function getFullName(): string
    {
        $firstName = mb_convert_case(trim($this->firstName), MB_CASE_TITLE, 'UTF-8');
        $lastName  = mb_convert_case(trim($this->lastName), MB_CASE_TITLE, 'UTF-8');

        return "{$firstName} {$lastName}";
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}

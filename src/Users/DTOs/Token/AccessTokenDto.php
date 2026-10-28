<?php

declare(strict_types=1);

namespace App\Users\DTOs\Token;

use DateTimeImmutable;

readonly class AccessTokenDto
{
    public function __construct(
        public int $userId,
        public int $roleId,
        public bool $isActive,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $emailVerifiedAt
    ) {
    }


    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            roleId: (int) $data['role_id'],
            isActive: (bool) $data['is_active'],
            expiresAt: !empty($data['expires_at']) ?
                        new DateTimeImmutable($data['expires_at']) : null,
            emailVerifiedAt: !empty($data['email_verified_at']) ?
                        new DateTimeImmutable($data['email_verified_at']) : null
        );
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return new DateTimeImmutable() > $this->expiresAt;
    }

    public function isValidUser(): bool
    {
        return $this->isActive && !$this->isExpired();
    }
}

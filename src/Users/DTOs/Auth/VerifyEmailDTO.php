<?php

declare(strict_types=1);

namespace App\Users\DTOs\Auth;

class VerifyEmailDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $token
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: mb_strtolower(trim($data['email'])),
            token: trim($data['token'])
        );
    }
}

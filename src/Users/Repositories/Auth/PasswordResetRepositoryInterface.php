<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface PasswordResetRepositoryInterface
{
    public function save(int $userId, string $tokenPlain, string $expiresAt): bool;

    public function findValidToken(string $hashedToken): ?array;

    public function deleteByUserId(int $userId): bool;

    public function deleteByToken(string $hashedToken): bool;
}

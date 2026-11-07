<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface PasswordResetRepositoryInterface
{
    public function save(int $userId, string $tokenPlain, string $expiresAt): bool;

    /**
     * @return array<string, mixed>
     */
    public function findByToken(string $tokenPlain): array;

    public function deleteByUserId(int $userId): bool;

    public function deleteByToken(string $hashedToken): bool;

    public function hasRecentToken(int $userId, int $cooldownMinutes = 30): bool;
}

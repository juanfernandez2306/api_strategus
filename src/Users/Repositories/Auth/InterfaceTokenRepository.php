<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface InterfaceTokenRepository
{
    public function save(int $userId, string $tokenName, string $tokenPlain): bool;

    public function delete(string $hashedToken): bool;

    public function deleteAllByUserId(int $userId): bool;
}

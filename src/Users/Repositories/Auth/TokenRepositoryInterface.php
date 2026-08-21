<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface TokenRepositoryInterface
{
    
    public function save(int $userId, string $tokenName, string $tokenPlain): bool;
    
    public function delete(string $token): bool;

    public function deleteAllByUserId(int $userId): bool;
    
}
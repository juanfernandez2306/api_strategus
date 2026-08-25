<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface UserRepositoryInterface
{
    public function existsByEmail(string $email): bool;

    public function countActiveUsers(): int;
}

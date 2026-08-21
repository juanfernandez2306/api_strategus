<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface InterfaceUserRepository
{
    public function existsByEmail(string $email): bool;
}
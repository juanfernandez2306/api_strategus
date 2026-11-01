<?php

declare(strict_types=1);

namespace App\Users\Services\Mail;

use App\Users\DTOs\Auth\RegisterUserDTO;

interface UserRegistrationEmailServiceInterface
{
    public function sendVerification(int $userId, RegisterUserDTO $userDto, string $tokenPlain): void;
}

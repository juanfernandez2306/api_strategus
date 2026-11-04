<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

use App\Shared\DTOs\SendTemplatedMailDTO;

interface TemplatedMailServiceInterface
{
    public function send(SendTemplatedMailDTO $dto): bool;
}

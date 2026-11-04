<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

use App\Shared\DTOs\MailDTO;

interface MailServiceInterface
{
    public function send(MailDTO $mailDTO): bool;
}

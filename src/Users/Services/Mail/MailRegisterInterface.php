<?php

declare(strict_types=1);

namespace App\Users\Services\Mail;

interface MailRegisterInterface
{
    public function send(string $toEmail, string $toName, string $tokenPlain): bool;
}

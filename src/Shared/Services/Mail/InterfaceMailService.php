<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

interface InterfaceMailService
{
    public function send(string $toEmail, string $toName, string $subject, string $bodyHTML): bool;
}

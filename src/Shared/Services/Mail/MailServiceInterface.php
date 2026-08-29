<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

interface MailServiceInterface
{
    /**
     * @param string|array<int, string> $toEmail Dirección o arreglo de direcciones
     * @param string|array<int, string> $toName  Nombre o arreglo de nombres asociados
     */
    public function send(
        string|array $toEmail,
        string|array $toName,
        string $subject,
        string $bodyHTML
    ): bool;
}

<?php

declare(strict_types=1);

namespace App\Users\Services\Mail;

interface UserMailServiceInterface
{
    /**
     * @param array{
     *     toEmail: string,
     *     userFullName: string,
     *     subject: string,
     *     viewTemplate: string,
     *     actionUrl?: string
     * } $emailContext
     */
    public function send(array $emailContext): bool;
}

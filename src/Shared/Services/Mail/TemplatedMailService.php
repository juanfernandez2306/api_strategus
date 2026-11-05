<?php

declare(strict_types=1);

namespace App\Shared\Services\Mail;

use App\Shared\DTOs\MailDTO;
use App\Shared\DTOs\SendTemplatedMailDTO;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TemplatedMailService implements TemplatedMailServiceInterface
{
    /**
     * @param array<string, mixed> $globalContext
     */
    public function __construct(
        private readonly MailServiceInterface $mailService,
        private readonly LoggerInterface $logger,
        private readonly array $globalContext = []
    ) {
    }

    public function send(SendTemplatedMailDTO $dto): bool
    {
        try {
            $templateContext = array_merge($this->globalContext, $dto->viewData);

            $htmlBody = view($dto->viewTemplate, $templateContext);

            $mailDTO = new MailDTO(
                toEmail: $dto->toEmail,
                toName: $dto->toName,
                subject: $dto->subject,
                bodyHTML: $htmlBody
            );

            return $this->mailService->send($mailDTO);
        } catch (RuntimeException $e) {
            $recipients = is_array($dto->toEmail) ?
                implode(', ', $dto->toEmail) :
                $dto->toEmail;

            $this->logger->error(sprintf(
                "Error al enviar correo '%s' hacia [%s] usando la vista '%s': %s",
                $dto->subject,
                $recipients,
                $dto->viewTemplate,
                $e->getMessage()
            ), [
                'exception' => $e,
                'template'  => $dto->viewTemplate,
                'recipients' => $dto->toEmail
            ]);

            throw $e;
        }
    }
}

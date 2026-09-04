<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

final class MonitoringUuidAlreadyExistsException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct(
            sprintf('El registro de monitoreo con UUID "%s" ya existe.', $uuid)
        );
    }
}

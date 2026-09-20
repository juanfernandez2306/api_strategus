<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;
use Throwable;

final class StrategusMonitoringException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function uuidAlreadyExists(string $uuid, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('El registro de monitoreo con UUID "%s" ya existe.', $uuid),
            0,
            $previous
        );
    }

    public static function userNotFound(int $userId, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('El usuario con ID "%d" no existe o no es válido.', $userId),
            0,
            $previous
        );
    }

    public static function growingAreaNotFound(?int $growingAreaCode, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('El área de lote con código "%s" no existe.', $growingAreaCode ?? 'S/I'),
            0,
            $previous
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Exception;

class ValidationException extends Exception
{
    /** @var array<string, mixed> */
    private array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        array $errors,
        string $message = "Errores de validación",
        int $code = 422
    ) {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

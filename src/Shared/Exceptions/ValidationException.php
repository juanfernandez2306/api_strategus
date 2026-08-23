<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(
        array $errors, 
        string $message = "Errores de validación", 
        int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Validator\Rules;

use Rakit\Validation\Rule;
use Ramsey\Uuid\Uuid;

class RamseyUuidRule extends Rule
{
    protected $message = "El campo :attribute no tiene un formato UUID válido.";

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return Uuid::isValid($value);
    }
}

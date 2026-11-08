<?php

declare(strict_types=1);

namespace App\Shared\Validators\Rules;

use Brick\DateTime\DateTimeException;
use Brick\DateTime\LocalTime;
use Rakit\Validation\Rule;

class TimeHmsRule extends Rule
{
    protected $message = "El campo :attribute debe tener un formato de hora válido (HH:mm:ss).";

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            LocalTime::parse($value);
            return true;
        } catch (DateTimeException $e) {
            return false;
        }
    }
}

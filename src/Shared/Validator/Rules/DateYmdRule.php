<?php

declare(strict_types=1);

namespace App\Shared\Validator\Rules;

use DateTimeImmutable;
use Rakit\Validation\Rule;

class DateYmdRule extends Rule
{
    protected $message = "El campo :attribute debe tener un formato válido de fecha (YYYY-MM-DD)";

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
        if (preg_match($dateRegex, $value) !== 1) {
            return false;
        }

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dateTime && $dateTime->format('Y-m-d') === $value;
    }
}

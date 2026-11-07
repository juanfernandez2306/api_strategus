<?php

declare(strict_types=1);

namespace App\Users\Validators\Rules;

use Rakit\Validation\Rule;
use DateTimeImmutable;

class ValidBirthDateRule extends Rule
{
    protected $message = "La fecha de nacimiento no es válida.";

    public function check(mixed $value): bool
    {
        if (!is_string($value) || empty($value)) {
            $this->message = "La fecha de nacimiento no es válida.";
            return false;
        }

        // Validar formato estricto Y-m-d
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            $this->message = "La fecha de nacimiento no es válida.";
            return false;
        }

        // Calcular la fecha límite de hace 18 años de forma interna
        $maxDate = (new DateTimeImmutable())->modify('-18 years')->format('Y-m-d');

        // Validar si el usuario es menor de 18 años
        if ($value > $maxDate) {
            $this->message = "El usuario debe ser mayor de 18 años.";
            return false;
        }

        return true;
    }
}

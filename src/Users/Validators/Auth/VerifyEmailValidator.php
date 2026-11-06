<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Validators\BaseValidator;
use Ramsey\Uuid\Uuid;
use Rakit\Validation\Rule;

class VerifyEmailValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();

        $this->validator->addValidator('ramsey_uuid', new class extends Rule {
            protected $message = "El campo :attribute no tiene un formato UUID válido.";

            public function check(mixed $value): bool
            {
                return is_string($value) && Uuid::isValid($value);
            }
        });
    }

    /** @return array<string, string> */
    protected function customAttributes(): array
    {
        return [
            'token' => 'token de verificación',
        ];
    }

    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'token' => 'required|ramsey_uuid',
        ];
    }
}

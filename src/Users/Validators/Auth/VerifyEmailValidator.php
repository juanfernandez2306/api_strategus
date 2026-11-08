<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Shared\Validator\BaseValidator;
use Ramsey\Uuid\Uuid;
use Rakit\Validation\Rule;

class VerifyEmailValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();
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

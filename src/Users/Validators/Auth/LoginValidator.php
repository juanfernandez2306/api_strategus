<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Validators\BaseValidator;

class LoginValidator extends BaseValidator
{
    protected function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required'
        ];
    }
}

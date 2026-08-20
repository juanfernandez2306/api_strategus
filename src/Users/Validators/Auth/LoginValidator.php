<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

class LoginValidator extends AuthBaseValidator
{
    protected function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required'
        ];
    }
}
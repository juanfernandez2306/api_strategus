<?php
namespace App\Usuarios\Validators;

class LoginValidator extends BaseValidator
{
    
    protected function rules(): array
    {
        $regexPassword = 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{6,}$/';

        return [
            'email'            => ['required', 'email'],
            'password'         => ['required', 'min:6', $regexPassword]
        ];
    }
}
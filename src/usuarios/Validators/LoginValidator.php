<?php
namespace App\Usuarios\Validators;

class LoginValidator extends BaseValidator
{
    
    protected function rules(): array
    {
        $regexNumerosLetras = 'regex:/^[a-zA-Z0-9]+$/';

        return [
            'email'            => ['required', 'email'],
            'password'         => ['required', 'min:6', $regexNumerosLetras]
        ];
    }
}
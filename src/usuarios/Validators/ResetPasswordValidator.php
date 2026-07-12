<?php
namespace App\Usuarios\Validators;

use App\Usuarios\Validators\RegexPatterns;

class ResetPasswordValidator extends BaseValidator
{
    /**
     * Definición de las reglas obligatorias de validación exigidas por BaseValidator.
     */
    protected function rules(): array
    {
        

        return [
            'token'            => ['required'],
            'email'            => ['required', 'email'],
            'password'         => ['required', 'regex:' . RegexPatterns::PASSWORD],
            'password_confirm' => ['required', 'same:password']
        ];
    }
}
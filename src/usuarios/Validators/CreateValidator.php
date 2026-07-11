<?php
namespace App\Usuarios\Validators;

use App\Usuarios\Validators\RegexPatterns;

class CreateValidator extends BaseValidator
{
    /**
     * Aquí definimos ÚNICAMENTE las reglas al estilo Laravel
     */
    protected function rules(): array
    {
        $regexSoloLetras = 'regex:/^[a-zA-ZÑñ]+$/';

        return [
            'nombre'           => [
                                    'required', 
                                    'max:50', 
                                    'regex:' . RegexPatterns::NOMBRE
                                ],
            'apellido'         => [
                                    'required',
                                    'max:50', 
                                    'regex:' . RegexPatterns::NOMBRE],
            'email'            => ['required', 'email'],
            'password'         => [
                                    'required', 
                                    'regex:' . RegexPatterns::PASSWORD
                                ],
            'password_confirm' => ['required', 'same:password']
        ];
    }
}
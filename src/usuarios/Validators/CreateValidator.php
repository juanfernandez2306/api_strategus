<?php
namespace App\Usuarios\Validators;

class CreateValidator extends BaseValidator
{
    /**
     * Aquí definimos ÚNICAMENTE las reglas al estilo Laravel
     */
    protected function rules(): array
    {
        $regexSoloLetras = 'regex:/^[a-zA-ZÑñ]+$/';
        $regexNumerosLetras = 'regex:/^[a-zA-Z0-9]+$/';

        return [
            'nombre'           => ['required', 'min:3', 'max:50', $regexSoloLetras],
            'apellido'         => ['required', 'min:3', 'max:50', $regexSoloLetras],
            'email'            => ['required', 'email'],
            'password'         => ['required', 'min:6', $regexNumerosLetras],
            'password_confirm' => ['required', 'same:password']
        ];
    }
}
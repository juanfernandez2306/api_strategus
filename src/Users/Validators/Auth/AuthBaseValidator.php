<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Validators\BaseValidator;

abstract class AuthBaseValidator extends BaseValidator
{
    public const PASSWORD_REGEX = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{6,}$/';
    public const PERSON_NAME_REGEX = '/^[A-Za-z]{3,}$/';


    public function __construct()
    {
        parent::__construct();
        
        $this->validator->setMessages([
            'regex' => 'El formato del campo :attribute no cumple con los requisitos esperados.'
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'first_name'            => 'primer nombre',
            'last_name'             => 'primer apellido',
            'email'                 => 'correo electrónico',
            'password'              => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
            'token'                 => 'token de verificación'
        ];
    }
}
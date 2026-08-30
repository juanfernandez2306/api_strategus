<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Validators\BaseValidator;
use App\Users\Validators\Auth\RegisterValidator;
use App\Users\Validators\Auth\VerifyEmailValidator;

class ResetPasswordValidator extends BaseValidator
{
    public const TOKEN_REGEX = VerifyEmailValidator::TOKEN_REGEX;
    public const PASSWORD_REGEX = RegisterValidator::PASSWORD_REGEX;

    public function __construct()
    {
        parent::__construct();

        $msgRegexPassword = "La contraseña debe tener al menos 6 caracteres, "
                          . "incluir letras, números y al menos un carácter especial.";

        $this->validator->setMessages([
            'email:email'                => 'El correo electrónico debe tener un formato válido.',
            'token:regex'                => 'El token de verificación no cumple con el formato requerido.',
            'password:regex'             => $msgRegexPassword,
            'password_confirmation:same' => 'El campo confirmación de contraseña debe coincidir con la contraseña.'
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'email'                 => 'correo electrónico',
            'token'                 => 'token de verificación',
            'password'              => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
        ];
    }

    protected function rules(): array
    {
        return [
            'email'                 => 'required|email|max:150',
            'token'                 => 'required|regex:' . self::TOKEN_REGEX,
            'password'              => 'required|regex:' . self::PASSWORD_REGEX,
            'password_confirmation' => 'required|same:password'
        ];
    }
}

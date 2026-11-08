<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Shared\Validator\BaseValidator;
use App\Users\Validators\Auth\RegisterValidator;
use Ramsey\Uuid\Uuid;
use Rakit\Validation\Rule;

class ResetPasswordValidator extends BaseValidator
{
    public const PASSWORD_REGEX = RegisterValidator::PASSWORD_REGEX;

    public function __construct()
    {
        parent::__construct();

        $msgRegexPassword = "La contraseña debe tener al menos 6 caracteres, "
                          . "incluir letras, números y al menos un carácter especial.";

        $this->validator->setMessages([
            'token:ramsey_uuid'          => 'El token de verificación no cumple con el formato requerido.',
            'password:regex'             => $msgRegexPassword,
            'password_confirmation:same' => 'El campo confirmación de contraseña debe coincidir con la contraseña.'
        ]);
    }

    /** @return array<string, string> */
    protected function customAttributes(): array
    {
        return [
            'token'                 => 'token de verificación',
            'password'              => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
        ];
    }

    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'token'                 => 'required|ramsey_uuid',
            'password'              => 'required|regex:' . self::PASSWORD_REGEX,
            'password_confirmation' => 'required|same:password'
        ];
    }
}

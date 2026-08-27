<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Validators\BaseValidator;

class VerifyEmailValidator extends BaseValidator
{
    public const TOKEN_REGEX = '/^[a-fA-F0-9]{64}$/';

    public function __construct()
    {
        parent::__construct();

        $this->validator->setMessages([
            'email:email' => 'El correo electrónico debe tener un formato válido.',
            'token:regex' => 'El token de verificación no cumple con el formato requerido.'
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'token' => 'token de verificación',
        ];
    }

    protected function rules(): array
    {
        return [
            'email' => 'required|email|max:150',
            'token' => 'required|regex:' . self::TOKEN_REGEX,
        ];
    }
}

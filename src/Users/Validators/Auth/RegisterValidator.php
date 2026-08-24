<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Shared\Exceptions\ValidationException;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\BaseValidator;
use App\Users\Validators\Rules\UniqueEmailRule;

class RegisterValidator extends BaseValidator
{
    public const PASSWORD_REGEX = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{6,}$/';
    public const PERSON_NAME_REGEX = '/^[A-Za-zÑñ]{3,}$/';

    public function __construct(UserRepositoryInterface $userRepository)
    {
        parent::__construct();

        $this->validator->addValidator('unique_email', new UniqueEmailRule($userRepository));

        $msgRegexPassword = "La contraseña debe tener al menos 6 caracteres,"
                            . " incluir letras, números y al menos un carácter especial.";

        $this->validator->setMessages([
            'first_name:regex'           => 'El nombre solo debe contener letras, sin tíldes ni espacios.',
            'last_name:regex'            => 'El apellido solo debe contener letras, sin tíldes ni espacios.',
            'password:regex'             => $msgRegexPassword,
            'password_confirmation:same' => 'El campo confirmación de contraseña debe coincidir con la contraseña.'
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
        ];
    }

    protected function rules(): array
    {
        return [
            'first_name'            => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'last_name'             => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'email'                 => 'required|email|max:150|unique_email',
            'password'              => 'required|regex:' . self::PASSWORD_REGEX,
            'password_confirmation' => 'required|same:password'
        ];
    }
}

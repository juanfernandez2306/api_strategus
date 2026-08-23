<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Repositories\Auth\InterfaceUserRepository;
use App\Users\Validators\Rules\UniqueEmailRule;

class RegisterValidator extends AuthBaseValidator
{
    public function __construct(InterfaceUserRepository $userRepository)
    {
        parent::__construct();

        $this->validator->addValidator('unique_email', new UniqueEmailRule($userRepository));
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


    public function validate(array $data, array $customMessages = []): array
    {
        $passwordRegexMessage = 'La contraseña debe tener al menos 6 caracteres, ' .
                                'incluir letras, números y al menos un carácter especial.';

        $messages = array_merge([
            'email.required'  => 'El campo correo electrónico es obligatorio.',
            'password.regex' => $passwordRegexMessage,
            'first_name.regex' => 'El nombre solo debe contener letras.',
            'last_name.regex'  => 'El apellido solo debe contener letras.'
        ], $customMessages);

        return parent::validate($data, $messages);
    }
}

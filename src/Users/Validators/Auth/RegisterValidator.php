<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Rules\UniqueEmailRule;

class RegisterValidator extends AuthBaseValidator
{
    protected function rules(): array
    {
        return [
            'first_name'            => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'last_name'             => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'email'                 => 'required|email|max:150',
            'password'              => 'required|regex:' . self::PASSWORD_REGEX,
            'password_confirmation' => 'required|same:password'
        ];
    }

    
    public function validate(array $data, array $customMessages = []): array
    {
        $messages = array_merge([
            'password.regex' => 'La contraseña debe tener al menos 6 caracteres, incluir letras, números y al menos un carácter especial.',
            'first_name.regex' => 'El nombre solo debe contener letras.',
            'last_name.regex'  => 'El apellido solo debe contener letras.'
        ], $customMessages);

        return parent::validate($data, $messages);
    }
}
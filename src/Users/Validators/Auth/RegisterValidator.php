<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\BaseValidator;
use App\Users\Validators\Rules\UniqueEmailRule;
use App\Users\Validators\Rules\MaxActiveUsersRule;
use App\Users\Validators\Rules\UniqueDocumentRule;

class RegisterValidator extends BaseValidator
{
    public const PASSWORD_REGEX = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{6,}$/';
    public const PERSON_NAME_REGEX = '/^[A-Za-zÑñ]{3,}$/';
    public const DNI_REGEX = '/^[0-9]{6,12}$/';
    public const PHONE_REGEX = '/^\+[1-9]\d{1,14}$/';

    private string $eighteenYearsAgo;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        parent::__construct();

        $this->validator->addValidator(
            'unique_email',
            new UniqueEmailRule($userRepository)
        );

        $this->validator->addValidator(
            'max_active_users',
            new MaxActiveUsersRule($userRepository)
        );

        $this->validator->addValidator(
            'unique_document',
            new UniqueDocumentRule($userRepository)
        );

        $this->eighteenYearsAgo = date('Y-m-d', strtotime('-18 years'));

        $msgRegexPassword = "La contraseña debe tener al menos 6 caracteres,"
                            . " incluir letras, números y al menos un carácter especial.";

        $this->validator->setMessages([
            'first_name:regex'           => 'El nombre solo debe contener letras, sin tíldes ni espacios.',
            'last_name:regex'            => 'El apellido solo debe contener letras, sin tíldes ni espacios.',
            'password:regex'             => $msgRegexPassword,
            'password_confirmation:same' => 'El campo confirmación de contraseña debe coincidir con la contraseña.',
            'birth_date:before'          => 'El usuario debe ser mayor de 18 años',
            'dni_type'                   => 'Verifica el tipo de documento V, E, CC',
            'dni_number:regex'           => 'El número de cédula debe contener entre 6 y 12 dígitos numéricos.',
            'dni_number:unique_document' => 'El tipo y número de cédula ya se encuentran registrados.',
            'phone_number'               => 'El teléfono debe tener formato internacional válido'
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
            'birth_date'            => 'fecha de nacimiento',
            'dni_number'            => 'cédula de identidad',
            'dni_type'              => 'tipo de documento dni',
            'phone_number'          => 'numero de teléfono'
        ];
    }

    protected function rules(): array
    {
        return [
            'first_name'            => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'last_name'             => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'email'                 => 'required|email|max:150|unique_email|max_active_users:30',
            'password'              => 'required|regex:' . self::PASSWORD_REGEX,
            'password_confirmation' => 'required|same:password',
            'birth_date'            => 'required|date|before:' . $this->eighteenYearsAgo,
            'dni_type'              => 'required|in:p,v,e,cc',
            'dni_number'            => 'required|numeric|regex:' . self::DNI_REGEX . '|unique_document:dni_type',
            'phone_number'          => 'required|regex:' . self::PHONE_REGEX
        ];
    }
}

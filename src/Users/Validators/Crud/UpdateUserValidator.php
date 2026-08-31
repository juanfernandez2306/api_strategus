<?php

declare(strict_types=1);

namespace App\Users\Validators\Crud;

use App\Users\Validators\BaseValidator;
use App\Users\Validators\Auth\RegisterValidator;

class UpdateUserValidator extends BaseValidator
{
    public const PERSON_NAME_REGEX = RegisterValidator::PERSON_NAME_REGEX;

    public function __construct()
    {
        parent::__construct();

        $this->validator->setMessages([
            'id:numeric'        => 'El identificador debe ser un valor numérico válido.',
            'id:min'            => 'El identificador debe ser mayor a 0.',
            'first_name:regex' => 'El nombre solo debe contener letras, sin tildes ni espacios.',
            'last_name:regex'  => 'El apellido solo debe contener letras, sin tildes ni espacios.',
            'is_active:boolean' => 'El campo estado activo debe ser un valor booleano o numérico (0/1)',
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'id' => 'Identificador',
            'first_name' => 'primer nombre',
            'last_name'  => 'primer apellido',
            'is_active'  => 'estado de la cuenta',
        ];
    }

    protected function rules(): array
    {
        return [
            'id'         => 'required|numeric|min:1',
            'first_name' => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'last_name'  => 'required|min:3|max:50|regex:' . self::PERSON_NAME_REGEX,
            'is_active'  => 'required|boolean',
        ];
    }
}

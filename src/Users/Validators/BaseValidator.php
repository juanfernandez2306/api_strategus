<?php

declare(strict_types=1);

namespace App\Users\Validators;

use App\Shared\Exceptions\ValidationException;
use Rakit\Validation\Validator;

abstract class BaseValidator
{
    protected Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();

        $this->validator->setMessages([
            'required' => 'El campo :attribute es obligatorio.',
            'min'      => 'El campo :attribute debe tener al menos :min caracteres.',
            'max'      => 'El campo :attribute no debe superar los :max caracteres.',
            'same'     => 'Los campos :attribute y :field deben coincidir.',
            'regex'    => 'El campo :attribute no cumple con el formato requerido.'
        ]);
    }

    abstract protected function rules(): array;

    protected function customAttributes(): array
    {
        return [];
    }

    public function validate(array $data, array $customMessages = []): array
    {
        $validation = $this->validator->make($data, $this->rules(), $customMessages);

        if (!empty($this->customAttributes())) {
            $validation->setAliases($this->customAttributes());
        }

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors()->firstOfAll();

            throw new ValidationException($errors);
        }

        return $validation->getValidData();
    }
}

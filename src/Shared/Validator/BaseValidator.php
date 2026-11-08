<?php

declare(strict_types=1);

namespace App\Shared\Validator;

use App\Shared\Exceptions\ValidationException;
use App\Shared\Validator\Rules\RamseyUuidRule;
use App\Shared\Validator\Rules\DateYmdRule;
use App\Shared\Validator\Rules\TimeHmsRule;
use Rakit\Validation\Validator;

abstract class BaseValidator
{
    protected Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();

        $this->validator->addValidator('ramsey_uuid', new RamseyUuidRule());

        $this->validator->addValidator('date_ymd', new DateYmdRule());

        $this->validator->addValidator('time_hms', new TimeHmsRule());

        $this->validator->setMessages([
            'required' => 'El campo :attribute es obligatorio.',
            'min'      => 'El campo :attribute debe tener al menos :min caracteres.',
            'max'      => 'El campo :attribute no debe superar los :max caracteres.',
            'same'     => 'Los campos :attribute y :field deben coincidir.',
            'regex'    => 'El campo :attribute no cumple con el formato requerido.',
            'ramsey_uuid' => 'El campo :attribute no tiene un formato UUID válido.',
            'date_ymd' => 'El campo :attribute no es una fecha válida (YYYY-mm-dd).',
            'time_hms' => 'El campo :attribute no es una hora válida (HH:mm:ss).'
        ]);
    }

    /** @return array<string, string> */
    abstract protected function rules(): array;

    /** @return array<string, string> */
    protected function customAttributes(): array
    {
        return [];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $customMessages
     * @return array<string, string>
     */
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

<?php
namespace App\Strategus\Validators;

use Rakit\Validation\Validator;
use Rakit\Validation\Validation;

class MonitoreoValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    /**
     * Valida los datos de un único monitoreo
     * * @param array $data Datos del request
     * @return Validation
     */
    public function validate(array $data): Validation
    {
        $regexHora = 'regex:/^\d{2}:\d{2}:\d{2}$/'; // HH:mm:ss

        return $this->validator->validate($data, [
            'uuid'           => ['required', 'uuid'],
            'latitud'        => ['required', 'numeric'],
            'longitud'       => ['required', 'numeric'],
            'fecha_registro' => ['required', 'date:Y-m-d'],
            'hora_registro'  => ['required', $regexHora],
            'galeria'        => ['required', 'integer', 'min:0', 'max:10'],
            'precision'      => ['required', 'numeric'],
            'fecha_revision' => ['nullable', 'date:Y-m-d'],
            'hora_revision'  => ['nullable', $regexHora]
        ]);
    }
}
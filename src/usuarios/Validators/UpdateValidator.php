<?php
namespace App\Usuarios\Validators;

use App\Usuarios\Validators\RegexPatterns;

class UpdateValidator extends BaseValidator
{
    /**
     * Definición de las reglas obligatorias de validación para la actualización.
     */
    protected function rules(): array
    {
        return [
            'nombre'   => [
                            'required', 
                            'max:50', 
                            'regex:' . RegexPatterns::NOMBRE
                        ],
            'apellido' => [
                            'required',
                            'max:50', 
                            'regex:' . RegexPatterns::NOMBRE
                        ],
            'role_id'  => ['required', 'numeric'],
            'status'   => ['required', 'in:0,1'] // Fuerza a que sea estrictamente 0 (inactivo) o 1 (activo)
        ];
    }
}
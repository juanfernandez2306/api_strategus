<?php
namespace App\Usuarios\Validators;

use Rakit\Validation\Validator;

abstract class BaseValidator
{
    protected Validator $validator;

    public function __construct()
    {
        // Instanciamos el validador de Rakit una sola vez aquí
        $this->validator = new Validator();
        
        $this->validator->setMessages([
            'required' => 'El campo :attribute es obligatorio.',
            'email'    => 'El correo electrónico no es válido.',
            'same'     => 'Los campos :attribute y :to deben coincidir.',
            'regex'    => 'El formato del campo :attribute no es válido.'
        ]);
    }

    /**
     * Método abstracto que obligará a cada validador a definir sus reglas.
     */
    abstract protected function rules(): array;

    /**
     * Permitimos un segundo parámetro opcional para mensajes específicos
     */
    public function validate(array $data, array $customMessages = []): array
    {
        // Si hay mensajes específicos, se los pasamos a la validación actual
        $validation = $this->validator->validate($data, $this->rules(), $customMessages);

        if ($validation->fails()) {
            return $validation->errors()->firstOfAll();
        }

        return [];
    }
}
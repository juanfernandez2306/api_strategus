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
     * Ejecuta la validación y devuelve los errores (si los hay) o un array vacío.
     */
    public function validate(array $data): array
    {
        $validation = $this->validator->validate($data, $this->rules());

        if ($validation->fails()) {
            // Retorna solo el primer error de cada campo fallido
            return $validation->errors()->firstOfAll();
        }

        return []; // Todo limpio, cero errores
    }
}
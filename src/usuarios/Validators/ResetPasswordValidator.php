<?php
namespace App\Usuarios\Validators;

use Rakit\Validation\Validator;

class ResetPasswordValidator
{
    public function validate(array $data): array
    {
        $validator = new Validator();

        // Personalizamos mensajes en español
        $validator->setMessages([
            'required' => 'El campo :attribute es obligatorio.',
            'min'      => 'La contraseña debe tener al menos :min caracteres.',
            'same'     => 'Las contraseñas ingresadas no coinciden.'
        ]);

        $validation = $validator->make($data, [
            'token'            => 'required',
            'email'            => 'required|email',
            'password'         => 'required|min:6',
            'password_confirm' => 'required|same:password' // 👈 Valida que coincidan
        ]);

        $validation->validate();

        if ($validation->fails()) {
            // Retorna un arreglo limpio con el primer error de cada campo
            return $validation->errors()->firstOfAll();
        }

        return [];
    }
}
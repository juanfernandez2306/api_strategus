<?php

declare(strict_types=1);

namespace App\Users\Validators\Crud;

use App\Users\Validators\BaseValidator;

class DeleteUserValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();

        $this->validator->setMessages([
            'id:numeric' => 'El identificador debe ser un valor numérico válido.',
            'id:min'     => 'El identificador debe ser mayor a 0.',
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'id' => 'identificador',
        ];
    }

    protected function rules(): array
    {
        return [
            'id' => 'required|numeric|min:1',
        ];
    }
}

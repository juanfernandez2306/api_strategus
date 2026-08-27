<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Users\Validators\BaseValidator;

class EmailRequestValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();

        $this->validator->setMessages([
            'email:email' => 'El correo electrónico debe tener un formato válido.'
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'email' => 'correo electrónico',
        ];
    }

    protected function rules(): array
    {
        return [
            'email' => 'required|email|max:150',
        ];
    }
}

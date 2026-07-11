<?php
namespace App\Usuarios\Validators;

use App\Usuarios\Validators\RegexPatterns;

class LoginValidator extends BaseValidator
{
    
    protected function rules(): array
    {
        
        return [
            'email'            => ['required', 'email'],
            'password'         => [
                                'required', 
                                'regex:' . RegexPatterns::PASSWORD
                                ]
        ];
    }
}
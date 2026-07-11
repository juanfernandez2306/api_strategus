<?php
namespace App\Usuarios\Validators;

class RegexPatterns
{
    // Centralizamos la expresión regular aquí
    public const PASSWORD = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{6,}$/';
    
    
    public const NOMBRE = '/^[A-Za-z]{3,}$/';
}
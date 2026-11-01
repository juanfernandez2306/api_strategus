<?php

declare(strict_types=1);

namespace App\Users\Validators\Rules;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use Rakit\Validation\Rule;
use Rakit\Validation\Validation;

/**
 * @property array $parameters
 * @property Validation $validation
 */
class UniqueDocumentRule extends Rule
{
    protected $message = "El tipo y número de documento ya se encuentran registrados en el sistema.";

    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function check(mixed $value): bool
    {
        if (!is_string($value) || empty($value)) {
            return false;
        }

        $dniNumber = $value;

        $dniTypeField = $this->parameters[0] ?? 'dni_type';

        // Obtenemos de forma segura el valor del campo hermano mediante la instancia de validación
        $dniType = $this->validation->getValue($dniTypeField) ?? $this->validation->getValue('dni_type');

        if (!is_string($dniType) || empty($dniType)) {
            return false;
        }

        return !$this->userRepository->existsByDocument(
            $dniType,
            $dniNumber
        );
    }
}
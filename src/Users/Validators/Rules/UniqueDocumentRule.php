<?php

declare(strict_types=1);

namespace App\Users\Validators\Rules;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use InvalidArgumentException;
use Rakit\Validation\Rule;

/**
 * @property array $parameters
 * @property array $data
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

        if (!isset($this->parameters[0])) {
            throw new InvalidArgumentException(
                "La regla 'unique_document' requiere que se " .
                "especifique el campo de tipo de documento. "
            );
        }

        $dniTypeField = $this->parameters[0];

        if (!isset($this->data[$dniTypeField])) {
            return false;
        }

        $dniType = $this->data[$dniTypeField];


        if (!is_string($dniType) || empty($dniType)) {
            return false;
        }

        return !$this->userRepository->existsByDocument(
            dniType: $dniType,
            dniNumber: $dniNumber
        );
    }
}

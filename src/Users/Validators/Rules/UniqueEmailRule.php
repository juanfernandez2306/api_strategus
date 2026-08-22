<?php

declare(strict_types=1);

namespace App\Users\Validators\Rules;

use App\Users\Repositories\Auth\InterfaceUserRepository;
use Rakit\Validation\Rule;

class UniqueEmailRule extends Rule
{
    protected $message = "El correo electrónico ya se encuentra registrado.";

    private InterfaceUserRepository $userRepository;

    public function __construct(InterfaceUserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param mixed $value
    */
    public function check($value): bool
    {
        if (!is_string($value) || empty($value)) {
            return false;
        }

        return !$this->userRepository->existsByEmail($value);
    }
}

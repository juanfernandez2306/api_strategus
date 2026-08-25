<?php

declare(strict_types=1);

namespace App\Users\Validators\Rules;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use Rakit\Validation\Rule;

class UniqueEmailRule extends Rule
{
    protected $message = "El correo electrónico ya se encuentra registrado.";

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

        return !$this->userRepository->existsByEmail($value);
    }
}

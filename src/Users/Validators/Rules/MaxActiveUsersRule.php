<?php

declare(strict_types=1);

namespace App\Users\Validators\Rules;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use Rakit\Validation\Rule;

class MaxActiveUsersRule extends Rule
{
    protected $message = "Se ha alcanzado el límite máximo de usuarios activos permitidos.";

    protected $fillableParams = ['max'];

    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }


    public function check(mixed $value): bool
    {
        $maxAllowed = (int) ($this->parameter('max') ?? 30);

        $activeUsersCount = $this->userRepository->countActiveUsers();

        return $activeUsersCount < $maxAllowed;
    }
}

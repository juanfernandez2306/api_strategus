<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

interface UserRepositoryInterface
{
    public function existsByEmail(string $email): bool;

    public function countActiveUsers(): int;

    public function create(array $data): int;

    public function getAll(int $limit = 10, int $offset = 0): array;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function markEmailAsVerified(int $userId): bool;
}

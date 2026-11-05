<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

use App\Users\DTOs\Auth\UserDTO;

interface UserRepositoryInterface
{
    public function existsByEmail(string $email): bool;

    public function countActiveUsers(): int;

    public function create(UserDTO $data): int;

    public function getAll(int $limit = 10, int $offset = 0): array;

    public function update(array $data): bool;

    public function delete(int $id): bool;

    public function markEmailAsVerified(int $userId): bool;

    public function findByEmail(string $email): array;

    public function findById(int $id): array;

    public function findAdminEmails(): array;

    public function updatePassword(int $userId, string $newPassword): bool;

    public function existsByDocument(string $dniType, string $dniNumber): bool;
}

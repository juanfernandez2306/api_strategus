<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

use App\Users\DTOs\Auth\UserDTO;

interface UserRepositoryInterface
{
    public function existsByEmail(string $email): bool;

    public function countActiveUsers(): int;

    public function create(UserDTO $data): int;

    /** @return array<string, mixed> */
    public function getAll(int $limit = 10, int $offset = 0): array;

    /** @param array<string, mixed> $data */
    public function update(array $data): bool;

    public function delete(int $id): bool;

    public function markEmailAsVerified(int $userId): bool;

    /** @return array<string, mixed> */
    public function findByEmail(string $email): array;

    /** @return array<string, mixed> */
    public function findById(int $id): array;

    /** @return array<string, mixed> */
    public function findAdminEmails(): array;

    public function updatePassword(int $userId, string $newPassword): bool;

    public function existsByDocument(string $dniType, string $dniNumber): bool;
}

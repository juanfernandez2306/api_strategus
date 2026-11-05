<?php

declare(strict_types=1);

namespace App\Strategus\Repositories;

interface OilPalmGrowingAreaRepositoryInterface
{
    public function findCodeByLocation(float $latitude, float $longitude): ?int;

    /**
     * @return array<string, mixed>
     */
    public function findByCode(int $code): array;

    /**
     * @return array<string, mixed>
     */
    public function findByUuid(string $uuid): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function getAll(int $limit = 30, int $offset = 0): array;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): bool;

    public function delete(int $id): bool;
}

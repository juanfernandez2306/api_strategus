<?php

declare(strict_types=1);

namespace App\GrowingAreas\Repositories;

interface OilPalmGrowingAreaRepositoryInterface
{
    public function findCodeByLocation(float $latitude, float $longitude): ?int;

    public function findByCode(int $code): array;

    public function findByUuid(string $uuid): array;

    public function getAll(int $limit = 30, int $offset = 0): array;

    public function create(array $data): int;

    public function update(array $data): bool;

    public function delete(int $id): bool;
}

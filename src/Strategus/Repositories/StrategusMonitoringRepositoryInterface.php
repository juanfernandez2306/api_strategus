<?php

declare(strict_types=1);

namespace App\Strategus\Repositories;

interface StrategusMonitoringRepositoryInterface
{
    public function create(array $input): bool;

    public function findByUuid(string $uuid): array;

    public function getByGrowingArea(int $growingAreaCode, int $limit = 50, int $offset = 0): array;

    public function getAll(int $limit = 50, int $offset = 0): array;

    public function update(string $uuid, array $data, ?int $growingAreaCode): bool;

    public function delete(string $uuid): bool;

    public function getExportableData(
        string $startDate,
        string $endDate,
        ?int $growingAreaCode = null
    ): array;

    public function getDailySummaryByArea(?string $date = null): array;

    public function getRecentMapMarkers(int $days = 30): array;

    public function hasDuplicateInRadius(array $data): bool;

    public function getWeeklyChartData(): array;
}

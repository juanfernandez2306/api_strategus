<?php

declare(strict_types=1);

namespace App\Strategus\Repositories;

use App\Strategus\DTOs\Monitoring\ExistingRecordByUuidsOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\DTOs\Monitoring\SpatialMatchOutputDTO;

interface StrategusMonitoringRepositoryInterface
{
    public function create(PositionRecordItemInputDTO $record): bool;

    public function updateReviewedAt(PositionRecordItemInputDTO $record): bool;

    /**
     * @return array<string, mixed>
     */
    public function findByUuid(string $uuid): array;

    /**
     * @param string[] $uuids
     * @return array<string, ExistingRecordByUuidsOutputDTO>
     */
    public function findExistingByUuids(array $uuids);

    /**
     * @return array<array<string, mixed>>
     */
    public function getByGrowingArea(int $growingAreaCode, int $limit = 50, int $offset = 0): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function getAll(int $limit = 50, int $offset = 0): array;

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $uuid, array $data, ?int $growingAreaCode): bool;

    public function delete(string $uuid): bool;

    /**
     * @return array<array<string, mixed>>
     */
    public function getExportableData(
        string $startDate,
        string $endDate,
        ?int $growingAreaCode = null
    ): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function getDailySummaryByArea(?string $date = null): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function getRecentMapMarkers(int $days = 30): array;

    public function findDuplicateInRadius(PositionRecordItemInputDTO $record): SpatialMatchOutputDTO;

    /**
     * @return array<array<string, mixed>>
     */
    public function getWeeklyChartData(): array;
}

<?php

declare(strict_types=1);

namespace App\Strategus\Services;

use App\Strategus\DTOs\Monitoring\BulkSyncOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\Repositories\OilPalmGrowingAreaRepositoryInterface;
use App\Strategus\Repositories\StrategusMonitoringRepositoryInterface;
use PDO;
use Throwable;

final readonly class SyncPositionRecordsUseCase
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private OilPalmGrowingAreaRepositoryInterface $growingAreaRepository,
        private StrategusMonitoringRepositoryInterface $monitoringRepository,
        private PDO $pdo
    ) {
    }

    /**
     * @param PositionRecordItemInputDTO[] $dtoRecords
     */
    public function execute(array $dtoRecords, int $userId): BulkSyncOutputDTO
    {
        $deletedUuids = [];
        $syncedIncompleteUuids = [];
        $updatedUuids = [];
        $backendDeletedUuids = [];

        $insertedCount = 0;
        $updatedCount = 0;
        $discardedNoAreaCount = 0;
        $spatialDuplicateCount = 0;

        foreach (array_chunk($dtoRecords, self::CHUNK_SIZE) as $chunk) {
            $chunkUuids = array_map(fn(PositionRecordItemInputDTO $r) => $r->uuid, $chunk);
            $existingDbRecords = $this->monitoringRepository->findExistingByUuids($chunkUuids);

            $this->pdo->beginTransaction();

            try {
                /** @var PositionRecordItemInputDTO $record */
                foreach ($chunk as $record) {
                    if (isset($existingDbRecords[$record->uuid])) {
                        $existingRow = $existingDbRecords[$record->uuid];

                        if (empty($existingRow['reviewed_at']) && $record->isReviewedDateComplete()) {
                            $this->monitoringRepository->updateReviewedAt($record);
                            $updatedCount++;
                            $updatedUuids[] = $record->uuid;
                        }

                        $deletedUuids[] = $record->uuid;
                        continue;
                    }

                    $growingAreaCode = $this->growingAreaRepository->findCodeByLocation(
                        latitude: $record->latitude,
                        longitude: $record->longitude
                    );

                    if ($growingAreaCode === null) {
                        $discardedNoAreaCount++;
                        $deletedUuids[] = $record->uuid;
                        continue;
                    }

                    $incomingRecord = $record->withContext($userId, $growingAreaCode);

                    $spatialMatch = $this->monitoringRepository->findDuplicateInRadius($incomingRecord);

                    if ($spatialMatch->found) {
                        $spatialDuplicateCount++;

                        if (!$spatialMatch->isReviewed && $incomingRecord->isReviewedDateComplete()) {
                            $this->monitoringRepository->delete($spatialMatch->uuid);
                            $backendDeletedUuids[] = $spatialMatch->uuid;

                            $this->monitoringRepository->create($incomingRecord);
                            $insertedCount++;
                            $this->categorizeUuidByCompleteness(
                                $incomingRecord,
                                $deletedUuids,
                                $syncedIncompleteUuids
                            );
                        } else {
                            $deletedUuids[] = $incomingRecord->uuid;
                        }

                        continue;
                    }

                    $this->monitoringRepository->create($incomingRecord);
                    $insertedCount++;
                    $this->categorizeUuidByCompleteness(
                        $incomingRecord,
                        $deletedUuids,
                        $syncedIncompleteUuids
                    );
                }

                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $e;
            }
        }

        return new BulkSyncOutputDTO(
            deletedUuids: $deletedUuids,
            syncedIncompleteUuids: $syncedIncompleteUuids,
            insertedCount: $insertedCount,
            updatedCount: $updatedCount,
            discardedNoAreaCount: $discardedNoAreaCount,
            spatialDuplicateCount: $spatialDuplicateCount,
            updatedUuids: $updatedUuids,
            backendDeletedUuids: $backendDeletedUuids
        );
    }

    private function categorizeUuidByCompleteness(
        PositionRecordItemInputDTO $record,
        array &$deletedUuids,
        array &$syncedIncompleteUuids
    ): void {
        if ($record->isReviewedDateComplete()) {
            $deletedUuids[] = $record->uuid;
        } else {
            $syncedIncompleteUuids[] = $record->uuid;
        }
    }
}

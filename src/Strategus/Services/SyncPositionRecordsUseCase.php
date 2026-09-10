<?php

declare(strict_types=1);

namespace App\Strategus\Services;

use App\Strategus\DTOs\Monitoring\BulkSyncOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\Repositories\OilPalmGrowingAreaRepositoryInterface;
use App\Strategus\Repositories\StrategusMonitoringRepositoryInterface;
use App\Strategus\Validators\PositionRecordValidator;
use App\Shared\Exceptions\MonitoringUuidAlreadyExistsException;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SyncPositionRecordsUseCase
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private PositionRecordValidator $validator,
        private OilPalmGrowingAreaRepositoryInterface $growingAreaRepository,
        private StrategusMonitoringRepositoryInterface $monitoringRepository,
        private PDO $pdo,
        private LoggerInterface $logger
    ) {
    }

    public function execute(array $rawRecords, int $userId): BulkSyncOutputDTO
    {
        $validatedRecords = $this->validator->validateBulk($rawRecords);

        $deletedUuids = [];
        $syncedIncompleteUuids = [];
        $insertedCount = 0;

        $debugCounts = [
            'total_received' => count($validatedRecords),
            'discarded_no_growing_area' => 0,
            'spatial_duplicate_ignored' => 0,
            'uuid_already_exists_handled' => 0,
            'successfully_inserted' => 0,
        ];

        $chunks = array_chunk($validatedRecords, self::CHUNK_SIZE);

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->pdo->beginTransaction();

            try {
                foreach ($chunk as $rawItem) {
                    $latitude = (float) $rawItem['latitude'];
                    $longitude = (float) $rawItem['longitude'];
                    $uuid = (string) $rawItem['uuid'];

                    $growingAreaCode = $this->growingAreaRepository->findCodeByLocation(
                        latitude: $latitude,
                        longitude: $longitude
                    );

                    
                    if ($growingAreaCode === null) {
                        $debugCounts['discarded_no_growing_area']++;
                        $deletedUuids[] = $uuid;
                        continue;
                    }

                    $incomingRecord = PositionRecordItemInputDTO::fromArray(
                        userId: $userId,
                        growingAreaCode: $growingAreaCode,
                        rawAttributesValidated: $rawItem
                    );

                    $spatialDuplicateMatch = $this->monitoringRepository->findDuplicateInRadius($incomingRecord);

                    
                    if ($spatialDuplicateMatch->found) {
                        if (!$spatialDuplicateMatch->isReviewed && $incomingRecord->isReviewedDateComplete()) {
                            $this->monitoringRepository->delete($spatialDuplicateMatch->uuid);

                            if ($this->tryInsertRecord($incomingRecord)) {
                                $insertedCount++;
                                $debugCounts['successfully_inserted']++;
                                $this->categorizeUuidByCompleteness(
                                    record: $incomingRecord,
                                    deletedUuids: $deletedUuids,
                                    syncedIncompleteUuids: $syncedIncompleteUuids
                                );
                            }
                        } else {
                            $debugCounts['spatial_duplicate_ignored']++;
                            $deletedUuids[] = $incomingRecord->uuid;
                        }

                        continue;
                    }

                    try {
                        $this->monitoringRepository->create($incomingRecord);
                        $insertedCount++;
                        $debugCounts['successfully_inserted']++;
                        $this->categorizeUuidByCompleteness(
                            record: $incomingRecord,
                            deletedUuids: $deletedUuids,
                            syncedIncompleteUuids: $syncedIncompleteUuids
                        );
                    } catch (MonitoringUuidAlreadyExistsException $e) {
                        $debugCounts['uuid_already_exists_handled']++;
                        $existingDbRecord = $this->monitoringRepository->findByUuid($incomingRecord->uuid);

                        if (!empty($existingDbRecord) && empty($existingDbRecord['reviewed_at'])) {
                            if ($incomingRecord->isReviewedDateComplete()) {
                                $this->monitoringRepository->updateReviewedAt($incomingRecord);
                                $deletedUuids[] = $incomingRecord->uuid;
                            }
                        }
                    }
                }

                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $this->logger->error('Error durante la sincronización por fragmentos', [
                    'user_id'     => $userId,
                    'chunk_index' => $chunkIndex,
                    'chunk_size'  => count($chunk),
                    'error'       => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        $this->logger->info('Resumen del flujo de Sincronización', $debugCounts);

        return new BulkSyncOutputDTO(
            deletedUuids: $deletedUuids,
            syncedIncompleteUuids: $syncedIncompleteUuids,
            insertedCountRegister: $insertedCount
        );
    }

    private function tryInsertRecord(PositionRecordItemInputDTO $record): bool
    {
        try {
            return $this->monitoringRepository->create($record);
        } catch (MonitoringUuidAlreadyExistsException $e) {
            return false;
        }
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

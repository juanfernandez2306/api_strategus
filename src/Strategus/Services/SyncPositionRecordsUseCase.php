<?php

declare(strict_types=1);

namespace App\Strategus\Services;

use App\Strategus\Repositories\OilPalmGrowingAreaRepositoryInterface;
use App\Shared\Exceptions\MonitoringUuidAlreadyExistsException;
use App\Strategus\DTOs\Monitoring\BulkSyncOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\Repositories\StrategusMonitoringRepositoryInterface;
use App\Strategus\Validators\PositionRecordValidator;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SyncPositionRecordsUseCase
{
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

        $this->pdo->beginTransaction();

        try {
            foreach ($validatedRecords as $rawItem) {
                $latitude = (float) $rawItem['latitude'];
                $longitude = (float) $rawItem['longitude'];
                $uuid = (string) $rawItem['uuid'];

                $growingAreaCode = $this->growingAreaRepository->findCodeByLocation(
                    latitude: $latitude,
                    longitude: $longitude
                );

                if ($growingAreaCode === null) {
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
                            $this->categorizeUuidByCompleteness(
                                record: $incomingRecord,
                                deletedUuids: $deletedUuids,
                                syncedIncompleteUuids: $syncedIncompleteUuids
                            );
                        }
                    } else {
                        $deletedUuids[] = $incomingRecord->uuid;
                    }

                    continue;
                }

                try {
                    $this->monitoringRepository->create($incomingRecord);
                    $insertedCount++;
                    $this->categorizeUuidByCompleteness(
                        record: $incomingRecord,
                        deletedUuids: $deletedUuids,
                        syncedIncompleteUuids: $syncedIncompleteUuids
                    );
                } catch (MonitoringUuidAlreadyExistsException $e) {
                    $existingDbRecord = $this->monitoringRepository->findByUuid($incomingRecord->uuid);

                    if (!empty($existingDbRecord)) {
                        $isDbReviewed = !empty($existingDbRecord['reviewed_at']);

                        if (!$isDbReviewed && $incomingRecord->isReviewedDateComplete()) {
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

            $this->logger->error('Error durante la sincronización masiva de posiciones', [
                'user_id'     => $userId,
                'total_batch' => count($rawRecords),
                'error'       => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'trace'       => $e->getTraceAsString(),
            ]);

            throw $e;
        }

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

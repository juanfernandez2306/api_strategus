<?php

declare(strict_types=1);

namespace App\Strategus\Services;

use App\Strategus\DTOs\Monitoring\ClassifiedUuidsOutputDTO;
use App\Strategus\DTOs\Monitoring\ExistingRecordByUuidsOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\Repositories\StrategusMonitoringRepositoryInterface;
use InvalidArgumentException;

class ExistingRecordResolver
{
    public function __construct(
        private StrategusMonitoringRepositoryInterface $monitoringRepository
    ) {
    }

    /**
     * @param PositionRecordItemInputDTO[] $records
     */
    public function resolve(array $records): ClassifiedUuidsOutputDTO
    {
        if (empty($records)) {
            return new ClassifiedUuidsOutputDTO(toCreate: [], toUpdate: []);
        }

        $records = $this->ensureUniqueRecords($records);

        $uuids = array_map(fn(PositionRecordItemInputDTO $r) => $r->uuid, $records);

        /**
         * @var array<string, ExistingRecordByUuidsOutputDTO> $existingRecordsMap
         */
        $existingRecordsMap = $this->monitoringRepository->findExistingByUuids($uuids);

        $toCreate = [];
        $toUpdate = [];

        foreach ($records as $record) {
            if (isset($existingRecordsMap[$record->uuid])) {
                $toUpdate[$record->uuid] = $existingRecordsMap[$record->uuid];
            } else {
                $toCreate[] = $record;
            }
        }

        return new ClassifiedUuidsOutputDTO(
            toCreate: $toCreate,
            toUpdate: $toUpdate
        );
    }

    /**
     * @param PositionRecordItemInputDTO[] $records
     * @return PositionRecordItemInputDTO[]
     * @throws InvalidArgumentException
     */
    private function ensureUniqueRecords(array $records): array
    {
        $uniqueRecords = [];

        foreach ($records as $record) {
            $uuid = $record->uuid;

            if (isset($uniqueRecords[$uuid])) {
                if ($uniqueRecords[$uuid] == $record) {
                    continue;
                }

                $errorMessage = sprintf(
                    'Conflicto: Se encontraron registros duplicados con el UUID "%s" ' .
                    'pero con contenido diferente.',
                    $uuid
                );

                throw new InvalidArgumentException($errorMessage);
            }

            $uniqueRecords[$uuid] = $record;
        }

        return array_values($uniqueRecords);
    }
}

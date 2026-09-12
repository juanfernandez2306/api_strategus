<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

final readonly class BulkSyncOutputDTO
{
    public function __construct(
        public array $deletedUuids,
        public array $syncedIncompleteUuids,
        public int $insertedCount,
        public int $updatedCount = 0,
        public int $discardedNoAreaCount = 0,
        public int $spatialDuplicateCount = 0,
        public array $updatedUuids = [],
        public array $backendDeletedUuids = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'deletedUuids'           => array_values(
                array_unique($this->deletedUuids)
            ),
            'syncedIncompleteUuids'  => array_values(
                array_unique($this->syncedIncompleteUuids)
            ),
            'insertedCount'          => $this->insertedCount,
            'updatedCount'           => $this->updatedCount,
            'discardedNoAreaCount'   => $this->discardedNoAreaCount,
            'spatialDuplicateCount'  => $this->spatialDuplicateCount,
            'updatedUuids'           => array_values(
                array_unique($this->updatedUuids)
            ),
            'backendDeletedUuids'    => array_values(
                array_unique($this->backendDeletedUuids)
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

final readonly class BulkSyncOutputDTO
{
    public function __construct(
        public array $deletedUuids,
        public array $syncedIncompleteUuids,
        public int $insertedCountRegister
    ) {
    }

    public function toArray(): array
    {
        return [
            'deletedUuids'          => array_values(
                array_unique($this->deletedUuids)
            ),
            'syncedIncompleteUuids' => array_values(
                array_unique($this->syncedIncompleteUuids)
            ),
            'insertedCountRegister' => $this->insertedCountRegister,
        ];
    }
}

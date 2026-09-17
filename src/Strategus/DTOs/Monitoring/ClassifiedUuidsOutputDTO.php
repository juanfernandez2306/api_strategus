<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

final readonly class ClassifiedUuidsOutputDTO
{
    /**
     * @param PositionRecordItemInputDTO[] $toCreate
     * @param array<string, ExistingRecordByUuidsOutputDTO> $toUpdate
     */
    public function __construct(
        public array $toCreate,
        public array $toUpdate
    ) {
    }
}

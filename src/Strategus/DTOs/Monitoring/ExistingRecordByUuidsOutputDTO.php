<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

final readonly class ExistingRecordByUuidsOutputDTO
{
    public function __construct(
        public string $uuid,
        public bool $isReviewed
    ) {
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            uuid: (string) $row['uuid'],
            isReviewed: (bool) $row['isReviewed']
        );
    }

    public static function fromCollection(?array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['uuid'])) {
                continue;
            }

            $dto = self::fromDatabaseRow($row);
            $map[$dto->uuid] = $dto;
        }

        return $map;
    }
}

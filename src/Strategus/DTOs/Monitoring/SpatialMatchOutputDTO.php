<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

final readonly class SpatialMatchOutputDTO
{
    public function __construct(
        public bool $found,
        public ?string $uuid = null,
        public bool $isReviewed = false
    ) {
    }

    public static function notFound(): self
    {
        return new self(
            found: false,
            uuid: null,
            isReviewed: false
        );
    }

    /**
     * @param array{uuid?: string, is_reviewed?: int|bool}|null $row
     */
    public static function fromDatabaseRow(?array $row): self
    {
        if (empty($row)) {
            return self::notFound();
        }

        return new self(
            found: true,
            uuid: (string) $row['uuid'],
            isReviewed: (bool) $row['is_reviewed']
        );
    }
}

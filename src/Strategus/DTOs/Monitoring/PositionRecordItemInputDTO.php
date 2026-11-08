<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

use DateTimeImmutable;
use Exception;

final readonly class PositionRecordItemInputDTO
{
    public function __construct(
        public readonly string $uuid,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly DateTimeImmutable $recordedAt,
        public readonly int $galleryCount,
        public readonly float $gpsAccuracy,
        public readonly bool $isPlantReviewed,
        public readonly bool $isSynced,
        public readonly ?DateTimeImmutable $reviewedAt = null,
        public readonly ?int $userId = null,
        public readonly ?int $growingAreaCode = null
    ) {
    }

    /**
     * @param array<string, mixed> $rawData
     * @throws Exception
     */
    public static function fromArray(array $rawData): self
    {
        $recordedAtStr = sprintf(
            '%s %s',
            (string) $rawData['recordedDate'],
            (string) $rawData['recordedTime']
        );

        $reviewedAt = null;
        if (
            !empty($rawData['reviewedDate']) &&
            !empty($rawData['reviewedTime'])
        ) {
            $reviewedAtStr = sprintf(
                '%s %s',
                (string) $rawData['reviewedDate'],
                (string) $rawData['reviewedTime']
            );
            $reviewedAt = new DateTimeImmutable($reviewedAtStr);
        }

        return new self(
            uuid: (string) $rawData['uuid'],
            latitude: (float) $rawData['latitude'],
            longitude: (float) $rawData['longitude'],
            recordedAt: new DateTimeImmutable($recordedAtStr),
            galleryCount: (int) $rawData['galleryCount'],
            gpsAccuracy: (float) $rawData['gpsAccuracy'],
            isPlantReviewed: (bool) $rawData['isPlantReviewed'],
            isSynced: (bool) $rawData['isSynced'],
            reviewedAt: $reviewedAt
        );
    }

    public function withContext(int $userId, int $growingAreaCode): self
    {
        return new self(
            uuid: $this->uuid,
            latitude: $this->latitude,
            longitude: $this->longitude,
            recordedAt: $this->recordedAt,
            galleryCount: $this->galleryCount,
            gpsAccuracy: $this->gpsAccuracy,
            isPlantReviewed: $this->isPlantReviewed,
            isSynced: $this->isSynced,
            reviewedAt: $this->reviewedAt,
            userId: $userId,
            growingAreaCode: $growingAreaCode,
        );
    }

    public function isReviewedDateComplete(): bool
    {
        return $this->reviewedAt !== null;
    }

    public function getRecordedAtFormatted(): string
    {
        return $this->recordedAt->format('Y-m-d H:i:s');
    }

    public function getReviewedAtFormatted(): ?string
    {
        return $this->reviewedAt?->format('Y-m-d H:i:s');
    }

    public function getWktPoint(): string
    {
        return sprintf('POINT(%f %f)', $this->longitude, $this->latitude);
    }
}

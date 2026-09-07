<?php

declare(strict_types=1);

namespace App\Strategus\DTOs\Monitoring;

final readonly class PositionRecordItemInputDTO
{
    public function __construct(
        public string $uuid,
        public int $userId,
        public int $growingAreaCode,
        public float $latitude,
        public float $longitude,
        public string $recordedDate,
        public string $recordedTime,
        public int $galleryCount,
        public float $gpsAccuracy,
        public bool $isPlantReviewed,
        public bool $isSynced,
        public ?string $reviewedDate = null,
        public ?string $reviewedTime = null
    ) {
    }

    public static function fromArray(
        int $userId,
        int $growingAreaCode,
        array $rawAttributesValidated
    ): self {
        return new self(
            uuid: (string) $rawAttributesValidated['uuid'],
            userId: $userId,
            growingAreaCode: $growingAreaCode,
            latitude: (float) $rawAttributesValidated['latitude'],
            longitude: (float) $rawAttributesValidated['longitude'],
            recordedDate: (string) $rawAttributesValidated['recordedDate'],
            recordedTime: (string) $rawAttributesValidated['recordedTime'],
            galleryCount: (int) $rawAttributesValidated['galleryCount'],
            gpsAccuracy: (float) $rawAttributesValidated['gpsAccuracy'],
            isPlantReviewed: (bool) $rawAttributesValidated['isPlantReviewed'],
            isSynced: (bool) $rawAttributesValidated['isSynced'],
            reviewedDate: isset($rawAttributesValidated['reviewedDate'])
                        ? (string) $rawAttributesValidated['reviewedDate']
                        : null,
            reviewedTime: isset($rawAttributesValidated['reviewedTime'])
                        ? (string) $rawAttributesValidated['reviewedTime']
                        : null
        );
    }

    public function isReviewedDateComplete(): bool
    {
        return !empty($this->reviewedDate) && !empty($this->reviewedTime);
    }

    public function getRecordedAtFormatted(): string
    {
        return sprintf('%s %s', $this->recordedDate, $this->recordedTime);
    }

    public function getReviewedAtFormatted(): ?string
    {
        return $this->isReviewedDateComplete()
            ? sprintf('%s %s', $this->reviewedDate, $this->reviewedTime)
            : null;
    }

    public function getWktPoint(): string
    {
        return sprintf('POINT(%f %f)', $this->longitude, $this->latitude);
    }
}

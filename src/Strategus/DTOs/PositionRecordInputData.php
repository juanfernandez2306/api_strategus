<?php

declare(strict_types=1);

namespace App\Strategus\DTOs;

final readonly class PositionRecordInputData
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
        public ?string $reviewedTime = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: (string) $data['uuid'],
            userId: (int) $data['userId'],
            growingAreaCode: (int) $data['growingAreaCode'],
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            recordedDate: (string) $data['recordedDate'],
            recordedTime: (string) $data['recordedTime'],
            galleryCount: (int) $data['galleryCount'],
            gpsAccuracy: (float) $data['gpsAccuracy'],
            isPlantReviewed: (bool) $data['isPlantReviewed'],
            isSynced: (bool) $data['isSynced'],
            reviewedDate: $data['reviewedDate'] ?? null,
            reviewedTime: $data['reviewedTime'] ?? null,
        );
    }


    public function getRecordedAtFormatted(): string
    {
        return sprintf('%s %s', $this->recordedDate, $this->recordedTime);
    }

    public function getReviewedAtFormatted(): ?string
    {
        if (!empty($this->reviewedDate) && !empty($this->reviewedTime)) {
            return sprintf('%s %s', $this->reviewedDate, $this->reviewedTime);
        }

        return null;
    }

    public function getWktPoint(): string
    {
        return sprintf('POINT(%f %f)', $this->longitude, $this->latitude);
    }
}

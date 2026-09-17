<?php

declare(strict_types=1);

namespace App\Strategus\Repositories;

use App\Shared\Exceptions\MonitoringUuidAlreadyExistsException;
use App\Strategus\DTOs\Monitoring\ExistingRecordByUuidsOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\DTOs\Monitoring\SpatialMatchOutputDTO;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PdoStrategusMonitoringRepository implements StrategusMonitoringRepositoryInterface
{
    private PDO $pdo;
    private ?LoggerInterface $logger;

    public function __construct(PDO $pdo, ?LoggerInterface $logger = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Helper para garantizar la lectura de bytes desde un string o LOB Resource de PDO.
     */
    private function extractBytes(mixed $binaryData): string
    {
        return is_resource($binaryData) ? stream_get_contents($binaryData) : (string) $binaryData;
    }

    public function create(PositionRecordItemInputDTO $record): bool
    {
        $sql = "INSERT INTO strategus_monitorings (
                    uuid,
                    user_id,
                    growing_area_code,
                    location,
                    recorded_at,
                    gallery_count,
                    gps_accuracy,
                    reviewed_at
                ) VALUES (
                    :uuid,
                    :user_id,
                    :growing_area_code,
                    ST_PointFromText(:location, 4326),
                    :recorded_at,
                    :gallery_count,
                    :gps_accuracy,
                    :reviewed_at
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':uuid', Uuid::fromString($record->uuid)->getBytes(), PDO::PARAM_LOB);
            $stmt->bindValue(':user_id', $record->userId, PDO::PARAM_INT);
            $stmt->bindValue(':growing_area_code', $record->growingAreaCode, PDO::PARAM_INT);
            $stmt->bindValue(':location', $record->getWktPoint());
            $stmt->bindValue(':recorded_at', $record->getRecordedAtFormatted());
            $stmt->bindValue(':gallery_count', $record->galleryCount, PDO::PARAM_INT);
            $stmt->bindValue(':gps_accuracy', $record->gpsAccuracy);
            $stmt->bindValue(':reviewed_at', $record->getReviewedAtFormatted());

            return $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->logger?->error('PDO Integrity Constraint Violation in create()', [
                    'exception_message' => $e->getMessage(),
                    'sql_state'         => $e->getCode(),
                    'uuid'              => $record->uuid,
                    'user_id'           => $record->userId,
                    'growing_area_code' => $record->growingAreaCode,
                ]);
            }

            throw $e;
        }
    }

    public function updateReviewedAt(PositionRecordItemInputDTO $record): bool
    {
        $sql = "UPDATE strategus_monitorings 
                SET reviewed_at = :reviewed_at 
                WHERE uuid = :uuid";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':reviewed_at', $record->getReviewedAtFormatted());
        $stmt->bindValue(':uuid', Uuid::fromString($record->uuid)->getBytes(), PDO::PARAM_LOB);

        return $stmt->execute();
    }

    public function findByUuid(string $uuid): array
    {
        $sql = "SELECT 
                    uuid,
                    user_id,
                    growing_area_code,
                    ST_X(location) AS longitude,
                    ST_Y(location) AS latitude,
                    recorded_at,
                    gallery_count,
                    gps_accuracy,
                    reviewed_at,
                    synced_at
                FROM strategus_monitorings 
                WHERE uuid = :uuid 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uuid', Uuid::fromString($uuid)->getBytes(), PDO::PARAM_LOB);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [];
        }

        $result['uuid'] = Uuid::fromBytes($this->extractBytes($result['uuid']))->toString();

        return $result;
    }

    public function findExistingByUuids(array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }

        $binaryUuids = array_map(
            fn(string $uuid) => Uuid::fromString($uuid)->getBytes(),
            $uuids
        );

        $placeholders = implode(',', array_fill(0, count($binaryUuids), '?'));

        $sql = "SELECT 
                    uuid,
                    (reviewed_at IS NOT NULL) AS is_reviewed
                FROM strategus_monitorings 
                WHERE uuid IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);

        foreach (array_values($binaryUuids) as $index => $binaryUuid) {
            $stmt->bindValue($index + 1, $binaryUuid, PDO::PARAM_LOB);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedRows = array_map(function (array $row) {
            return [
                'uuid'       => Uuid::fromBytes($this->extractBytes($row['uuid']))->toString(),
                'isReviewed' => (bool) $row['is_reviewed'],
            ];
        }, $rows ?: []);

        return ExistingRecordByUuidsOutputDTO::fromCollection($formattedRows);
    }

    public function getByGrowingArea(int $growingAreaCode, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT 
                    uuid,
                    user_id,
                    growing_area_code,
                    ST_X(location) AS longitude,
                    ST_Y(location) AS latitude,
                    recorded_at,
                    gallery_count,
                    gps_accuracy,
                    reviewed_at,
                    synced_at
                FROM strategus_monitorings 
                WHERE growing_area_code = :growing_area_code 
                ORDER BY recorded_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':growing_area_code', $growingAreaCode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            $row['uuid'] = Uuid::fromBytes($this->extractBytes($row['uuid']))->toString();
            return $row;
        }, $rows);
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT 
                    uuid,
                    user_id,
                    growing_area_code,
                    ST_X(location) AS longitude,
                    ST_Y(location) AS latitude,
                    recorded_at,
                    gallery_count,
                    gps_accuracy,
                    reviewed_at,
                    synced_at
                FROM strategus_monitorings 
                ORDER BY recorded_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            $row['uuid'] = Uuid::fromBytes($this->extractBytes($row['uuid']))->toString();
            return $row;
        }, $rows);
    }

    public function update(string $uuid, array $data, ?int $growingAreaCode): bool
    {
        $recordedAt = sprintf(
            '%s %s',
            $data['recordedDate'],
            $data['recordedTime']
        );

        $reviewedAt = (!empty($data['reviewedDate']) && !empty($data['reviewedTime']))
            ? sprintf(
                '%s %s',
                $data['reviewedDate'],
                $data['reviewedTime']
            )
            : null;

        $pointWkt = sprintf(
            'POINT(%f %f)',
            (float) $data['longitude'],
            (float) $data['latitude']
        );

        $sql = "UPDATE strategus_monitorings SET
                    growing_area_code = :growing_area_code,
                    location          = ST_PointFromText(:location, 4326),
                    recorded_at       = :recorded_at,
                    gallery_count     = :gallery_count,
                    gps_accuracy      = :gps_accuracy,
                    reviewed_at       = :reviewed_at
                WHERE uuid = :uuid";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uuid', Uuid::fromString($uuid)->getBytes(), PDO::PARAM_LOB);
        $stmt->bindValue(':growing_area_code', $growingAreaCode, PDO::PARAM_INT);
        $stmt->bindValue(':location', $pointWkt);
        $stmt->bindValue(':recorded_at', $recordedAt);
        $stmt->bindValue(':gallery_count', (int) $data['galleryCount'], PDO::PARAM_INT);
        $stmt->bindValue(':gps_accuracy', (float) $data['gpsAccuracy']);
        $stmt->bindValue(':reviewed_at', $reviewedAt);

        return $stmt->execute();
    }

    public function delete(string $uuid): bool
    {
        $sql = "DELETE FROM strategus_monitorings WHERE uuid = :uuid";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uuid', Uuid::fromString($uuid)->getBytes(), PDO::PARAM_LOB);

        return $stmt->execute();
    }

    public function getExportableData(
        string $startDate,
        string $endDate,
        ?int $growingAreaCode = null
    ): array {
        $sql = "SELECT 
                    ST_Y(m.location) AS latitude,
                    ST_X(m.location) AS longitude,
                    m.growing_area_code AS growingAreaCode,
                    m.gps_accuracy AS gpsAccuracy,
                    m.gallery_count AS galleryCount,
                    m.recorded_at AS recordedAt,
                    m.reviewed_at AS reviewedAt
                FROM strategus_monitorings m
                WHERE m.recorded_at BETWEEN :start_date AND :end_date";

        $params = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];

        if ($growingAreaCode !== null) {
            $sql .= " AND m.growing_area_code = :growing_area_code";
            $params['growing_area_code'] = $growingAreaCode;
        }

        $sql .= " ORDER BY m.recorded_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            return [
                'latitude'        => (float) $row['latitude'],
                'longitude'       => (float) $row['longitude'],
                'growingAreaCode' => $row['growingAreaCode'] !== null ? (int) $row['growingAreaCode'] : null,
                'gpsAccuracy'     => (float) $row['gpsAccuracy'],
                'galleryCount'    => (int) $row['galleryCount'],
                'recordedAt'      => (string) $row['recordedAt'],
                'reviewedAt'      => $row['reviewedAt'] !== null ? (string) $row['reviewedAt'] : null,
            ];
        }, $results);
    }

    public function getDailySummaryByArea(?string $date = null): array
    {
        $sql = "SELECT 
                    m.growing_area_code AS growingAreaCode,
                    SUM(CASE WHEN DATE(m.recorded_at) = COALESCE(:recorded_date, CURDATE()) 
                        THEN 1 ELSE 0 END) AS markedPalms,
                    SUM(CASE WHEN DATE(m.reviewed_at) = COALESCE(:reviewed_date, CURDATE()) 
                        THEN 1 ELSE 0 END) AS reviewedPalms
                FROM strategus_monitorings m
                WHERE DATE(m.recorded_at) = COALESCE(:where_recorded, CURDATE()) 
                OR DATE(m.reviewed_at) = COALESCE(:where_reviewed, CURDATE())
                GROUP BY m.growing_area_code
                ORDER BY m.growing_area_code ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'recorded_date'  => $date,
            'reviewed_date'  => $date,
            'where_recorded' => $date,
            'where_reviewed' => $date,
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            return [
                'growingAreaCode' => $row['growingAreaCode'] !== null ? (int) $row['growingAreaCode'] : null,
                'markedPalms'     => (int) $row['markedPalms'],
                'reviewedPalms'   => (int) $row['reviewedPalms'],
            ];
        }, $results);
    }

    public function getRecentMapMarkers(int $days = 30): array
    {
        $sql = "SELECT 
                    m.uuid,
                    ST_Y(m.location) AS latitude,
                    ST_X(m.location) AS longitude,
                    CASE WHEN m.reviewed_at IS NOT NULL THEN 1 ELSE 0 END AS isPlantReviewed
                FROM strategus_monitorings m
                WHERE m.recorded_at >= NOW() - INTERVAL :days DAY
                ORDER BY m.recorded_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            return [
                'uuid'            => Uuid::fromBytes($this->extractBytes($row['uuid']))->toString(),
                'latitude'        => (float) $row['latitude'],
                'longitude'       => (float) $row['longitude'],
                'isPlantReviewed' => (bool) $row['isPlantReviewed'],
            ];
        }, $results);
    }

    public function findDuplicateInRadius(
        PositionRecordItemInputDTO $record
    ): SpatialMatchOutputDTO {
        $sql = "SELECT 
                    uuid,
                    (reviewed_at IS NOT NULL) AS is_reviewed
                FROM strategus_monitorings 
                WHERE ST_Distance_Sphere(location, ST_PointFromText(:point, 4326)) <= 2
                AND ABS(DATEDIFF(recorded_at, :recorded_at)) <= 15
                AND uuid <> :uuid
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':point', $record->getWktPoint());
        $stmt->bindValue(':recorded_at', $record->getRecordedAtFormatted());
        $stmt->bindValue(':uuid', Uuid::fromString($record->uuid)->getBytes(), PDO::PARAM_LOB);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['uuid'] = Uuid::fromBytes($this->extractBytes($result['uuid']))->toString();
        }

        return SpatialMatchOutputDTO::fromDatabaseRow($result ?: null);
    }

    public function getWeeklyChartData(): array
    {
        $sql = "SELECT 
                    d.fecha,
                    COALESCE(SUM(m.marcada), 0) AS palmas_marcadas,
                    COALESCE(SUM(m.revisada), 0) AS palmas_revisadas
                FROM (
                    SELECT CURDATE() AS fecha UNION ALL
                    SELECT CURDATE() - INTERVAL 1 DAY UNION ALL
                    SELECT CURDATE() - INTERVAL 2 DAY UNION ALL
                    SELECT CURDATE() - INTERVAL 3 DAY UNION ALL
                    SELECT CURDATE() - INTERVAL 4 DAY UNION ALL
                    SELECT CURDATE() - INTERVAL 5 DAY UNION ALL
                    SELECT CURDATE() - INTERVAL 6 DAY
                ) d
                LEFT JOIN (
                    SELECT DATE(recorded_at) AS fecha, 1 AS marcada, 0 AS revisada 
                    FROM strategus_monitorings
                    WHERE recorded_at >= CURDATE() - INTERVAL 6 DAY
                    
                    UNION ALL
                    
                    SELECT DATE(reviewed_at) AS fecha, 0 AS marcada, 1 AS revisada 
                    FROM strategus_monitorings 
                    WHERE reviewed_at IS NOT NULL 
                      AND reviewed_at >= CURDATE() - INTERVAL 6 DAY
                ) m ON d.fecha = m.fecha
                GROUP BY d.fecha
                ORDER BY d.fecha ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingPlants(int $days = 20): array
    {
        $sql = "SELECT 
                    uuid,
                    ST_Y(location) AS latitude,
                    ST_X(location) AS longitude,
                    DATE_FORMAT(recorded_at, '%Y-%m-%d') AS recordedDate,
                    DATE_FORMAT(recorded_at, '%H:%i:%s') AS recordedTime,
                    gallery_count AS galleryCount,
                    gps_accuracy AS gpsAccuracy,
                    0 AS isPlantReviewed,
                    1 AS isSynced,
                    NULL AS reviewedDate,
                    NULL AS reviewedTime
                FROM strategus_monitorings
                WHERE reviewed_at IS NULL
                AND recorded_at >= NOW() - INTERVAL :days DAY
                ORDER BY recorded_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            return [
                'uuid'            => Uuid::fromBytes($this->extractBytes($row['uuid']))->toString(),
                'latitude'        => (float) $row['latitude'],
                'longitude'       => (float) $row['longitude'],
                'recordedDate'    => (string) $row['recordedDate'],
                'recordedTime'    => (string) $row['recordedTime'],
                'galleryCount'    => (int) $row['galleryCount'],
                'gpsAccuracy'     => (float) $row['gpsAccuracy'],
                'isPlantReviewed' => false,
                'isSynced'        => true,
                'reviewedDate'    => null,
                'reviewedTime'    => null
            ];
        }, $results);
    }
}

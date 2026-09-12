<?php

declare(strict_types=1);

namespace App\Strategus\Repositories;

use App\Shared\Exceptions\MonitoringUuidAlreadyExistsException;
use App\Strategus\DTOs\Monitoring\ExistingRecordByUuidsOutputDTO;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\DTOs\Monitoring\SpatialMatchOutputDTO;
use PDO;
use PDOException;

class PdoStrategusMonitoringRepository implements StrategusMonitoringRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Helper para convertir UUID canonical String (36 chars) a Binario (16 bytes).
     */
    private function uuidToBin(string $uuid): string
    {
        return hex2bin(str_replace('-', '', $uuid));
    }

    /**
     * Helper para convertir Binario (16 bytes) a UUID canonical String (36 chars).
     */
    private function binToUuid(string $binaryUuid): string
    {
        $hex = bin2hex($binaryUuid);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
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

            return $stmt->execute([
                'uuid'              => $this->uuidToBin($record->uuid),
                'user_id'           => $record->userId,
                'growing_area_code' => $record->growingAreaCode,
                'location'          => $record->getWktPoint(),
                'recorded_at'       => $record->getRecordedAtFormatted(),
                'gallery_count'     => $record->galleryCount,
                'gps_accuracy'      => $record->gpsAccuracy,
                'reviewed_at'       => $record->getReviewedAtFormatted(),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new MonitoringUuidAlreadyExistsException($record->uuid);
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

        return $stmt->execute([
            'reviewed_at' => $record->getReviewedAtFormatted(),
            'uuid'        => $this->uuidToBin($record->uuid),
        ]);
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
        $stmt->execute(['uuid' => $this->uuidToBin($uuid)]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [];
        }

        $result['uuid'] = $this->binToUuid($result['uuid']);

        return $result;
    }

    public function findExistingByUuids(array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }

        $binaryUuids = array_map(
            fn(string $uuid) => $this->uuidToBin($uuid),
            $uuids
        );

        $placeholders = implode(',', array_fill(0, count($binaryUuids), '?'));

        $sql = "SELECT 
                    uuid,
                    (reviewed_at IS NOT NULL) AS is_reviewed
                FROM strategus_monitorings 
                WHERE uuid IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($binaryUuids));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedRows = array_map(function (array $row) {
            return [
                'uuid'       => $this->binToUuid($row['uuid']),
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
            $row['uuid'] = $this->binToUuid($row['uuid']);
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
            $row['uuid'] = $this->binToUuid($row['uuid']);
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

        return $stmt->execute([
            'uuid'              => $this->uuidToBin($uuid),
            'growing_area_code' => $growingAreaCode,
            'location'          => $pointWkt,
            'recorded_at'       => $recordedAt,
            'gallery_count'     => (int) $data['galleryCount'],
            'gps_accuracy'      => (float) $data['gpsAccuracy'],
            'reviewed_at'       => $reviewedAt,
        ]);
    }

    public function delete(string $uuid): bool
    {
        $sql = "DELETE FROM strategus_monitorings WHERE uuid = :uuid";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['uuid' => $this->uuidToBin($uuid)]);
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
                'uuid'            => $this->binToUuid($row['uuid']),
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
        $stmt->execute([
            'point'       => $record->getWktPoint(),
            'recorded_at' => $record->getRecordedAtFormatted(),
            'uuid'        => $this->uuidToBin($record->uuid),
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['uuid'] = $this->binToUuid($result['uuid']);
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
                'uuid'            => $this->binToUuid($row['uuid']),
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

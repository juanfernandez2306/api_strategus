<?php

declare(strict_types=1);

namespace App\Strategus\Repositories;

use App\Shared\Exceptions\MonitoringUuidAlreadyExistsException;
use PDO;
use PDOException;

class PdoStrategusMonitoringRepository implements StrategusMonitoringRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $input): bool
    {
        $recordedAt = sprintf(
            '%s %s',
            $input['recordedDate'],
            $input['recordedTime']
        );

        $reviewedAt = (!empty($input['reviewedDate']) && !empty($input['reviewedTime']))
            ? sprintf(
                '%s %s',
                $input['reviewedDate'],
                $input['reviewedTime']
            ) : null;

        $pointWkt = sprintf(
            'POINT(%f %f)',
            (float) $input['longitude'],
            (float) $input['latitude']
        );

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
                    UUID_TO_BIN(:uuid),
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
                'uuid'              => $input['uuid'],
                'user_id'           => (int) $input['userId'],
                'growing_area_code' => $input['growingAreaCode'] ?? null,
                'location'          => $pointWkt,
                'recorded_at'       => $recordedAt,
                'gallery_count'     => (int) $input['galleryCount'],
                'gps_accuracy'      => (float) $input['gpsAccuracy'],
                'reviewed_at'       => $reviewedAt,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new MonitoringUuidAlreadyExistsException($input['uuid']);
            }

            throw $e;
        }
    }

    public function findByUuid(string $uuid): array
    {
        $sql = "SELECT 
                    BIN_TO_UUID(uuid) AS uuid,
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
                WHERE uuid = UUID_TO_BIN(:uuid) 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uuid' => $uuid]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }

    public function getByGrowingArea(int $growingAreaCode, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT 
                    BIN_TO_UUID(uuid) AS uuid,
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT 
                    BIN_TO_UUID(uuid) AS uuid,
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                WHERE uuid = UUID_TO_BIN(:uuid)";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'uuid'              => $uuid,
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
        $sql = "DELETE FROM strategus_monitorings WHERE uuid = UUID_TO_BIN(:uuid)";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['uuid' => $uuid]);
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
                    BIN_TO_UUID(m.uuid) AS uuid,
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
                'uuid'            => (string) $row['uuid'],
                'latitude'        => (float) $row['latitude'],
                'longitude'       => (float) $row['longitude'],
                'isPlantReviewed' => (bool) $row['isPlantReviewed'],
            ];
        }, $results);
    }

    public function hasDuplicateInRadius(array $data): bool
    {
        $pointWkt = sprintf(
            'POINT(%f %f)',
            (float) $data['longitude'],
            (float) $data['latitude']
        );

        $sql = "SELECT EXISTS(
                    SELECT 1 
                    FROM strategus_monitorings 
                    WHERE ST_Distance_Sphere(location, ST_PointFromText(:point, 4326)) <= 2
                    AND ABS(DATEDIFF(recorded_at, :recorded_at)) <= 15
                    AND uuid <> UUID_TO_BIN(:uuid)
                ) AS has_duplicate";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'point'       => $pointWkt,
            'recorded_at' => $data['recordedAt'],
            'uuid'        => $data['uuid'],
        ]);

        return (bool) $stmt->fetchColumn();
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
                    BIN_TO_UUID(uuid) AS uuid,
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
                'uuid'            => (string) $row['uuid'],
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

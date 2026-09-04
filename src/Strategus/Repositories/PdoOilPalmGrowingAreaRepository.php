<?php

declare(strict_types=1);

namespace App\GrowingAreas\Repositories;

use PDO;

class PdoOilPalmGrowingAreaRepository implements OilPalmGrowingAreaRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findCodeByLocation(float $latitude, float $longitude): ?int
    {
        $pointWkt = sprintf('POINT(%f %f)', $longitude, $latitude);

        $sql = "SELECT growing_area_code 
                FROM oil_palm_growing_areas 
                WHERE ST_Contains(boundary, ST_PointFromText(:point, 4326)) 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['point' => $pointWkt]);

        $code = $stmt->fetchColumn();

        return $code !== false ? (int) $code : null;
    }

    public function findByCode(int $code): array
    {
        $sql = "SELECT 
                    BIN_TO_UUID(uuid) AS uuid,
                    growing_area_code,
                    palm_count,
                    ST_AsText(boundary) AS boundary_wkt
                FROM oil_palm_growing_areas 
                WHERE growing_area_code = :code 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['code' => $code]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }

    public function findByUuid(string $uuid): array
    {
        $sql = "SELECT 
                    BIN_TO_UUID(uuid) AS uuid,
                    growing_area_code,
                    palm_count,
                    ST_AsText(boundary) AS boundary_wkt
                FROM oil_palm_growing_areas 
                WHERE uuid = UUID_TO_BIN(:uuid) 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uuid' => $uuid]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }

    public function getAll(int $limit = 30, int $offset = 0): array
    {
        $sql = "SELECT 
                    BIN_TO_UUID(uuid) AS uuid,
                    growing_area_code,
                    palm_count,
                    ST_AsText(boundary) AS boundary_wkt
                FROM oil_palm_growing_areas 
                ORDER BY growing_area_code ASC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO oil_palm_growing_areas (
                    uuid,
                    growing_area_code,
                    palm_count,
                    boundary
                ) VALUES (
                    UUID_TO_BIN(:uuid),
                    :growing_area_code,
                    :palm_count,
                    ST_GeomFromText(:boundary_wkt, 4326)
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'uuid'              => $data['uuid'],
            'growing_area_code' => (int) $data['growing_area_code'],
            'palm_count'        => (int) $data['palm_count'],
            'boundary_wkt'      => $data['boundary_wkt'],
        ]);

        return (int) $data['growing_area_code'];
    }

    public function update(array $data): bool
    {
        $sql = "UPDATE oil_palm_growing_areas SET
                    palm_count = :palm_count,
                    boundary = ST_GeomFromText(:boundary_wkt, 4326)
                WHERE uuid = UUID_TO_BIN(:uuid)";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'uuid'         => $data['uuid'],
            'palm_count'   => (int) $data['palm_count'],
            'boundary_wkt' => $data['boundary_wkt'],
        ]);
    }

    public function delete(int $code): bool
    {
        $sql = "DELETE FROM oil_palm_growing_areas WHERE growing_area_code = :code";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['code' => $code]);
    }
}

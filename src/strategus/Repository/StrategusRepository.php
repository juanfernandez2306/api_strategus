<?php
namespace App\Strategus\Repository;

use PDO;
use DateTime;
use DateTimeZone;
use Exception;

class StrategusRepository
{
    private PDO $db;
    private array $queries;
    private DateTimeZone $timezone;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->queries = require __DIR__ . '/../Query/StrategusSentences.php';
        // Ajustamos la hora local (Caracas / Bogotá / etc.)
        $this->timezone = new DateTimeZone('America/Caracas'); 
    }

    /**
     * Inserta el monitoreo o actualiza únicamente su fecha de revisión si el UUID existe
     */
    public function upsert(array $item, int $usuarioId): bool
    {
        // 1. Unir y formatear fecha_registro (Day.js YYYY-MM-DD + HH:mm:ss)
        $stringRegistro = $item['fecha_registro'] . ' ' . $item['hora_registro'];
        $dtRegistro = DateTime::createFromFormat('Y-m-d H:i:s', $stringRegistro, $this->timezone);
        $fechaRegistro = $dtRegistro->format('Y-m-d H:i:s');

        // 2. Unir y formatear fecha_revision (Si el operador la trató, de lo contrario NULL)
        $fechaRevision = null;
        if (!empty($item['fecha_revision'])) {
            $horaRev = $item['hora_revision'] ?? '00:00:00';
            $stringRevision = $item['fecha_revision'] . ' ' . $horaRev;
            $dtRevision = DateTime::createFromFormat('Y-m-d H:i:s', $stringRevision, $this->timezone);
            $fechaRevision = $dtRevision->format('Y-m-d H:i:s');
        }

        // 3. Formato espacial WKT POINT(longitud latitud)
        $wktPoint = "POINT(" . $item['longitud'] . " " . $item['latitud'] . ")";

        try {
            $stmt = $this->db->prepare($this->queries['upsertSingle']);
            return $stmt->execute([
                ':uuid'           => $item['uuid'],
                ':usuario_id'     => $usuarioId,
                ':posicion'       => $wktPoint,
                ':fecha_registro' => $fechaRegistro,
                ':galeria'        => (int)$item['galeria'],
                ':precision_gps'  => (float)$item['precision'], 
                ':fecha_revision' => $fechaRevision
            ]);
        } catch (Exception $e) {
            throw new Exception("Error al procesar el monitoreo: " . $e->getMessage());
        }
    }

    /**
     * Obtiene los registros filtrados por rango de fecha, calculando el lote espacial
     * * @param string $fechaInicio Formato 'Y-m-d H:i:s' o 'Y-m-d 00:00:00'
     * @param string $fechaFin Formato 'Y-m-d H:i:s' o 'Y-m-d 23:59:59'
     * @return array
     */
    public function getExportRecords(string $fechaInicio, string $fechaFin): array
    {
        try {
            $stmt = $this->db->prepare($this->queries['getExportData']);
            $stmt->execute([
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin'    => $fechaFin
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al consultar datos filtrados para Excel: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el resumen de rendimiento real del día seleccionado (Marcadas vs Curadas históricas)
     * @param string $fechaInput Formato 'Y-m-d'
     * @return array
     */
    public function getResumenPorLote(string $fechaInput): array
    {
        try {
            $stmt = $this->db->prepare($this->queries['getResumenPorLote']);
            $stmt->execute([
                ':fecha_input_1' => $fechaInput,
                ':fecha_input_2' => $fechaInput,
                ':fecha_input_3' => $fechaInput,
                ':fecha_input_4' => $fechaInput
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al procesar el resumen analítico por lotes: " . $e->getMessage());
        }
    }

    public function getMapMarkers(): array
    {
        try {
            $stmt = $this->db->prepare($this->queries['getMapMarkers']);
            $stmt->execute();
            
            // Forzamos el casteo de tipos para la compatibilidad exacta de TypeScript
            return array_map(function($row) {
                return [
                    'uuid' => $row['uuid'],
                    'lat'  => (float) $row['latitud'],
                    'lng'  => (float) $row['longitud'],
                    'revision_planta' => (bool) $row['revision_planta']
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (Exception $e) {
            throw new Exception("Error al obtener marcadores del mapa (Rango 30 días): " . $e->getMessage());
        }
    }
}
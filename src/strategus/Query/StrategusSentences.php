<?php

namespace App\Strategus\Query;

return [
    "insertBatch" => "
        INSERT INTO monitoreos_strategus (
            uuid, usuario_id, posicion, fecha_registro, 
            galeria, precision_gps, fecha_revision
        ) VALUES (
            :uuid, :usuario_id, ST_PointFromText(:posicion, 4326), 
            :fecha_registro, :galeria, :precision_gps, 
            :fecha_revision
        ) 
        ON DUPLICATE KEY UPDATE 
            fecha_revision = :fecha_revision
    ",
    
    "getExportData" => "
        SELECT 
            ST_Y(m.posicion) AS latitud,
            ST_X(m.posicion) AS longitud,
            COALESCE(l.lote, 'S/I') AS lote,
            m.precision_gps AS `precision`,
            m.fecha_registro,
            m.fecha_revision
        FROM monitoreos_strategus m
        LEFT JOIN lotes l ON ST_Contains(l.geometria, m.posicion)
        WHERE m.fecha_registro BETWEEN :fecha_inicio AND :fecha_fin
        ORDER BY m.fecha_registro DESC
    ",

    "getResumenPorLote" => "
        SELECT 
            COALESCE(l.lote, 'S/I') AS lote,
            SUM(CASE WHEN DATE(m.fecha_registro) = :fecha_input_1 THEN 1 ELSE 0 END) AS palmas_marcadas,
            SUM(CASE WHEN DATE(m.fecha_revision) = :fecha_input_2 THEN 1 ELSE 0 END) AS palmas_revisadas
        FROM monitoreos_strategus m
        LEFT JOIN lotes l ON ST_Contains(l.geometria, m.posicion)
        WHERE DATE(m.fecha_registro) = :fecha_input_3 OR DATE(m.fecha_revision) = :fecha_input_4
        GROUP BY l.id, l.lote
        ORDER BY l.lote ASC
    ",

    "getMapMarkers" => "
        SELECT 
            m.uuid,
            ST_Y(m.posicion) AS latitud,
            ST_X(m.posicion) AS longitud,
            CASE WHEN m.fecha_revision IS NOT NULL THEN 1 ELSE 0 END AS revision_planta
        FROM monitoreos_strategus m
        WHERE m.fecha_registro >= NOW() - INTERVAL 30 DAY
        ORDER BY m.fecha_registro DESC
    "
];
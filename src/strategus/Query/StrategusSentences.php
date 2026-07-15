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
            :fecha_revision_insert
        ) 
        ON DUPLICATE KEY UPDATE 
            fecha_revision = :fecha_revision_update
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
            COALESCE(CONCAT('LOTE 0', l.lote), 'S/I') AS lote,
            -- 1. Suma 1 si la fecha del registro (sin la hora) es hoy
            SUM(CASE WHEN DATE(m.fecha_registro) = CURDATE() THEN 1 ELSE 0 END) AS palmas_marcadas,
            -- 2. Suma 1 si la fecha de la revisión (sin la hora) es hoy
            SUM(CASE WHEN DATE(m.fecha_revision) = CURDATE() THEN 1 ELSE 0 END) AS palmas_revisadas
        FROM monitoreos_strategus m
        LEFT JOIN lotes l ON ST_Contains(l.geometria, m.posicion)
        -- 3. Filtro principal: Que el monitoreo se haya registrado hoy O revisado hoy
        WHERE DATE(m.fecha_registro) = CURDATE() OR DATE(m.fecha_revision) = CURDATE()
        GROUP BY l.id, l.lote
        ORDER BY l.lote ASC;
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
    ",

    "buscarDuplicadoEnRadio" => "
        SELECT uuid 
        FROM monitoreos_strategus 
        WHERE ST_Distance_Sphere(posicion, ST_PointFromText(:posicion_WKT, 4326)) <= 4
          AND ABS(DATEDIFF(fecha_registro, :fecha_referencia)) <= 15
          AND (fecha_registro < :fecha_registro_1 OR (fecha_registro = :fecha_registro_2 AND uuid <> :uuid_actual))
        LIMIT 1
    ",

    "getGraficoSemanal" => "
        SELECT 
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
            -- Subconsulta para unificar eventos de registro y revisión por su respectiva fecha
            SELECT DATE(fecha_registro) AS fecha, 1 AS marcada, 0 AS revisada 
            FROM monitoreos_strategus
            WHERE fecha_registro >= CURDATE() - INTERVAL 6 DAY
            
            UNION ALL
            
            SELECT DATE(fecha_revision) AS fecha, 0 AS marcada, 1 AS revisada 
            FROM monitoreos_strategus 
            WHERE fecha_revision IS NOT NULL 
              AND fecha_revision >= CURDATE() - INTERVAL 6 DAY
        ) m ON d.fecha = m.fecha
        GROUP BY d.fecha
        ORDER BY d.fecha ASC;
    "

];
<?php
namespace App\Usuarios;

return [
    'getAll' => "
        SELECT 
            u.id, 
            u.nombre, 
            u.apellido,
            u.email,
            CASE WHEN u.status = 1 THEN 'activo' ELSE 'inactivo' END AS status,
            r.nombre AS rol 
        FROM usuarios u 
        LEFT JOIN roles r ON u.role_id = r.id 
        ORDER BY u.id DESC
    ",

    "existsEmail" => "
        SELECT id 
        FROM usuarios 
        WHERE email = :email
    ",

    "getAuthData" => "
        SELECT 
        id, 
        nombre, 
        password, 
        status 
        FROM usuarios 
        WHERE email = :email LIMIT 1
    ",

    "create" => "
        INSERT INTO usuarios (
            nombre, apellido, email, password
        ) VALUES (
        :nombre, :apellido, :email, :password)
    ",

    "delete" => "
        DELETE FROM usuarios WHERE id = :id
    ",

    "updateStatus" => "
        UPDATE usuarios 
        SET status = :status 
        WHERE id = :id
    ",

    "storeToken" => "
        INSERT INTO personal_access_tokens (
            usuario_id, nombre, 
            token, expires_at, 
            created_at, updated_at
        ) VALUES (
            :usuario_id, :nombre, 
            :token, :expires_at, 
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )
    ",

    "update" => "
        UPDATE usuarios 
        SET 
            nombre = :nombre, 
            apellido = :apellido,  
            role_id = :role_id,
            status = :status
        WHERE id = :id
    ",

    "createToken" => "
        INSERT INTO personal_access_tokens (usuario_id, 
        nombre, token, last_used_at, created_at, updated_at) 
        VALUES (:usuario_id, :nombre, 
        :token, NULL, CURRENT_TIMESTAMP, 
        CURRENT_TIMESTAMP)
    ",

    "deleteToken" => "
        DELETE FROM personal_access_tokens 
        WHERE token = :token
    ",

    "deleteAllTokens" => "
        DELETE FROM personal_access_tokens 
        WHERE usuario_id = :usuario_id
    ",

    "storeVerificationToken" => "
        INSERT INTO password_resets (email, token, expires_at) 
        VALUES (:email, :token, :expires_at)
    ",

    "verifyEmail" => "
        UPDATE usuarios 
        SET email_verified_at = CURRENT_TIMESTAMP 
        WHERE email = :email
    ",

    "getVerificationToken" => "
        SELECT token, expires_at 
        FROM password_resets 
        WHERE email = :email 
        LIMIT 1
    ",
    
    "deleteVerificationToken" => "
        DELETE FROM password_resets 
        WHERE email = :email
    ",

    "deletePasswordReset" => "
        DELETE FROM password_resets 
        WHERE email = :email
    ",

    "storePasswordReset" => "
        INSERT INTO password_resets (email, token, expires_at) 
        VALUES (:email, :token, :expires_at)
    ",

    "updatePassword" => "
        UPDATE usuarios 
        SET password = :password 
        WHERE email = :email
    ",

    "findByEmail" => "
        SELECT id, nombre, apellido, email, password, role_id, status 
        FROM usuarios 
        WHERE email = :email 
        LIMIT 1
    ",

    "getAccessToken" => "
        SELECT ut.usuario_id, ut.expires_at, 
        u.role_id, u.status, u.email_verified_at
        FROM personal_access_tokens ut
        INNER JOIN usuarios u ON ut.usuario_id = u.id
        WHERE ut.token = :token 
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
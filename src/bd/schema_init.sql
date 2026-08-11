-- -----------------------------------------------------
-- 1. TABLA: roles
-- -----------------------------------------------------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Inserción de Roles Básicos
-- -----------------------------------------------------
INSERT INTO roles (id, nombre, descripcion) VALUES 
(1, 'administrador', 'Acceso total al sistema, gestión de usuarios'),
(2, 'topografía',    'Gestión completa de las capas del mapa y administración de la tabla de negocio.'),
(3, 'operador',      'Personal de campo. Permiso exclusivo para registrar posiciones (POST/PUT) en la tabla de negocio.'),
(4, 'supervisor',    'Control y monitoreo de campo. Permiso para visualizar estadísticas, mapas y exportar datos.'),
(5, 'visitante',     'Acceso de solo lectura externo. Diseñado para clientes o auditores que solo requieren ver estadísticas y descargar reportes.');


-- -----------------------------------------------------
-- 2. TABLA: usuarios
-- -----------------------------------------------------
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED DEFAULT 3,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status BOOLEAN DEFAULT 1,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_roles FOREIGN KEY (role_id) REFERENCES roles(id) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 3. TABLA: personal_access_tokens
-- -----------------------------------------------------
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) 
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 4. TABLA: password_resets
-- -----------------------------------------------------
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (token),
    CONSTRAINT fk_resets_usuario FOREIGN KEY (email) 
        REFERENCES usuarios(email) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 5. TABLA DE NEGOCIO: monitoreos_strategus (Sin dependencia de FK lotes)
-- -----------------------------------------------------
CREATE TABLE monitoreos_strategus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    usuario_id INT UNSIGNED NOT NULL,

    -- Se mantiene la columna para guardar la referencia al ID del lote
    lote_id INT UNSIGNED DEFAULT NULL,
    
    -- Tipo espacial nativo para mapas vectoriales e índices R-Tree
    posicion POINT NOT NULL,
    
    -- Fecha y hora local unificada del registro capturado en campo
    fecha_registro DATETIME NOT NULL, 
    
    -- Variable de daño (túneles en estípite)
    galeria INT NOT NULL,
    precision_gps DECIMAL(5, 2) NOT NULL,
    
    -- Estado de revisión implícito (NULL = Sin revisar, DATETIME = Revisada/Tratada)
    fecha_revision DATETIME NULL DEFAULT NULL, 
    
    -- Registro de inserción en el servidor (Sincronización de IndexedDB)
    sincronizado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_strategus_usuario FOREIGN KEY (usuario_id) 
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices de consulta rápida
CREATE INDEX idx_strategus_lote_id ON monitoreos_strategus(lote_id);
CREATE INDEX idx_strategus_fecha_reg ON monitoreos_strategus(fecha_registro);
CREATE INDEX idx_strategus_fecha_rev ON monitoreos_strategus(fecha_revision);
CREATE SPATIAL INDEX idx_strategus_posicion ON monitoreos_strategus(posicion);


-- -----------------------------------------------------
-- 6. TABLA DE NEGOCIO: rutas_gps (Consolidado Diario por Usuario)
-- -----------------------------------------------------
CREATE TABLE rutas_gps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid_tramo VARCHAR(36) NOT NULL UNIQUE,
    usuario_id INT UNSIGNED NOT NULL,
    
    fecha_jornada DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    
    trayectoria LINESTRING NOT NULL,
    
    sincronizado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_rutas_usuario FOREIGN KEY (usuario_id) 
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE SPATIAL INDEX idx_rutas_trayectoria ON rutas_gps(trayectoria);
CREATE INDEX idx_rutas_usuario_jornada ON rutas_gps(usuario_id, fecha_jornada, hora_inicio);


-- -----------------------------------------------------
-- 7. TABLA DE NEGOCIO: pausas_trayectoria (Eventos Estacionarios / Pausas)
-- -----------------------------------------------------
CREATE TABLE pausas_trayectoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid_pausa VARCHAR(36) NOT NULL UNIQUE,
    usuario_id INT UNSIGNED NOT NULL,
    
    fecha_pausa DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    
    posicion POINT NOT NULL,
    
    sincronizado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_pausas_usuario FOREIGN KEY (usuario_id) 
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE SPATIAL INDEX idx_pausas_posicion ON pausas_trayectoria(posicion);
CREATE INDEX idx_pausas_usuario_fecha ON pausas_trayectoria(usuario_id, fecha_pausa, hora_inicio);


-- -----------------------------------------------------
-- 8. TABLA: api_rate_limits
-- -----------------------------------------------------
CREATE TABLE api_rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier VARCHAR(100) NOT NULL COMMENT 'Token del usuario o IP si es público',
  endpoint VARCHAR(150) NOT NULL COMMENT 'Ej: /api/lotes/posiciones o NULL para limite global',
  rate_key VARCHAR(100) NOT NULL COMMENT 'Hash o string del bloque temporal',
  hits INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Cantidad de peticiones realizadas en la ventana',
  reset_at DATETIME NOT NULL COMMENT 'Fecha/Hora en que vence esta ventana de tiempo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_identifier_endpoint_key (identifier, endpoint, rate_key),
  KEY idx_reset_at (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
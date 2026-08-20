-- =====================================================================
-- MIGRACIÓN: Módulo "Toma Física e Inspección de Activos"
-- Proyecto MIC — no elimina datos existentes, solo amplía el esquema.
-- Aplicar a la base "mic":  mysql -u root mic < migracion_toma_fisica.sql
-- =====================================================================

-- 1) inventario_general: nueva columna "situacion" (NO mezclar con "estado" físico)
ALTER TABLE inventario_general
    ADD COLUMN situacion VARCHAR(30) NOT NULL DEFAULT 'disponible' AFTER estado,
    ADD KEY idx_inv_situacion (situacion),
    ADD KEY idx_inv_id_sede (id_sede),
    ADD KEY idx_inv_codigo (codigo_interno);

-- Los activos con responsable asignado quedan como "asignado"
UPDATE inventario_general SET situacion = 'asignado' WHERE profesor_id IS NOT NULL AND activo = 1;

-- 2) tipo_equipo: relación con categorias (para el botón "+ Tipo")
ALTER TABLE tipo_equipo
    ADD COLUMN categoria_id INT(11) DEFAULT NULL AFTER descripcion,
    ADD KEY idx_tipo_categoria (categoria_id),
    ADD CONSTRAINT tipo_equipo_ibfk_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id);

-- 3) Catálogo de estados físicos (el botón "+ Estado" inserta aquí; solo admin)
CREATE TABLE IF NOT EXISTS estados (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO estados (nombre, descripcion) VALUES
    ('bueno', 'El activo se encuentra en buen estado físico'),
    ('regular', 'El activo presenta desgaste o deterioro leve'),
    ('dañado', 'El activo presenta daños que requieren mantenimiento o evaluación'),
    ('fuera de servicio', 'El activo no puede ser utilizado'),
    ('nuevo', 'El activo es nuevo, sin uso'),
    ('malo', 'El activo presenta daños graves')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 4) Tomas físicas (cabecera)
CREATE TABLE IF NOT EXISTS tomas_fisicas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    sede_id INT(11) NOT NULL,
    ubicacion VARCHAR(200) NOT NULL,
    usuario_id INT(11) NOT NULL,
    fecha_toma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('en_progreso','finalizada','cancelada') NOT NULL DEFAULT 'en_progreso',
    total_esperados INT(11) NOT NULL DEFAULT 0,
    encontrados INT(11) NOT NULL DEFAULT 0,
    no_encontrados INT(11) NOT NULL DEFAULT 0,
    con_novedades INT(11) NOT NULL DEFAULT 0,
    danados INT(11) NOT NULL DEFAULT 0,
    en_mantenimiento INT(11) NOT NULL DEFAULT 0,
    en_reparacion INT(11) NOT NULL DEFAULT 0,
    en_buen_estado INT(11) NOT NULL DEFAULT 0,
    observaciones TEXT DEFAULT NULL,
    finalizada_en DATETIME DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tf_sede (sede_id),
    KEY idx_tf_usuario (usuario_id),
    KEY idx_tf_fecha (fecha_toma),
    CONSTRAINT tomas_fisicas_ibfk_sede FOREIGN KEY (sede_id) REFERENCES sedes (id),
    CONSTRAINT tomas_fisicas_ibfk_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Detalle de la toma física (una fila por activo esperado)
CREATE TABLE IF NOT EXISTS tomas_fisicas_detalle (
    id INT(11) NOT NULL AUTO_INCREMENT,
    toma_fisica_id INT(11) NOT NULL,
    elemento_id INT(11) NOT NULL,
    encontrado TINYINT(1) NOT NULL DEFAULT 0,
    estado_registrado VARCHAR(50) DEFAULT NULL,
    estado_encontrado VARCHAR(50) DEFAULT NULL,
    coincide_codigo TINYINT(1) NOT NULL DEFAULT 0,
    coincide_sede TINYINT(1) NOT NULL DEFAULT 0,
    coincide_ubicacion TINYINT(1) NOT NULL DEFAULT 0,
    coincide_responsable TINYINT(1) NOT NULL DEFAULT 0,
    situacion_despues VARCHAR(30) DEFAULT NULL,
    observacion TEXT DEFAULT NULL,
    verificador_id INT(11) DEFAULT NULL,
    verificada_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toma_elemento (toma_fisica_id, elemento_id),
    KEY idx_tfd_elemento (elemento_id),
    KEY idx_tfd_verificador (verificador_id),
    CONSTRAINT tomas_fisicas_detalle_ibfk_toma FOREIGN KEY (toma_fisica_id) REFERENCES tomas_fisicas (id) ON DELETE CASCADE,
    CONSTRAINT tomas_fisicas_detalle_ibfk_elemento FOREIGN KEY (elemento_id) REFERENCES inventario_general (id),
    CONSTRAINT tomas_fisicas_detalle_ibfk_verificador FOREIGN KEY (verificador_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Evidencias (asociadas a inspección, novedad o baja)
CREATE TABLE IF NOT EXISTS evidencias (
    id INT(11) NOT NULL AUTO_INCREMENT,
    entidad VARCHAR(20) NOT NULL,
    entidad_id INT(11) NOT NULL,
    tipo_evidencia VARCHAR(50) DEFAULT NULL,
    archivo VARCHAR(255) NOT NULL,
    subida_por INT(11) DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ev_entidad (entidad, entidad_id),
    KEY idx_ev_subida_por (subida_por),
    CONSTRAINT evidencias_ibfk_usuario FOREIGN KEY (subida_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Novedades registradas durante la inspección
CREATE TABLE IF NOT EXISTS novedades (
    id INT(11) NOT NULL AUTO_INCREMENT,
    elemento_id INT(11) NOT NULL,
    toma_fisica_id INT(11) DEFAULT NULL,
    tipo VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT(11) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'abierta',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nov_elemento (elemento_id),
    KEY idx_nov_toma (toma_fisica_id),
    KEY idx_nov_usuario (usuario_id),
    KEY idx_nov_fecha (fecha),
    CONSTRAINT novedades_ibfk_elemento FOREIGN KEY (elemento_id) REFERENCES inventario_general (id),
    CONSTRAINT novedades_ibfk_toma FOREIGN KEY (toma_fisica_id) REFERENCES tomas_fisicas (id) ON DELETE SET NULL,
    CONSTRAINT novedades_ibfk_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) Bajas de activos (la baja NO elimina el activo; cambia su situación)
CREATE TABLE IF NOT EXISTS bajas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    elemento_id INT(11) NOT NULL,
    motivo VARCHAR(50) NOT NULL,
    fecha_baja DATE NOT NULL,
    descripcion TEXT DEFAULT NULL,
    documento_baja VARCHAR(255) DEFAULT NULL,
    usuario_solicita INT(11) NOT NULL,
    estado ENUM('solicitada','aprobada','rechazada') NOT NULL DEFAULT 'solicitada',
    aprobado_por INT(11) DEFAULT NULL,
    observacion_aprobacion TEXT DEFAULT NULL,
    fecha_aprobacion DATETIME DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_baja_elemento (elemento_id),
    KEY idx_baja_solicitante (usuario_solicita),
    KEY idx_baja_aprobador (aprobado_por),
    KEY idx_baja_estado (estado),
    CONSTRAINT bajas_ibfk_elemento FOREIGN KEY (elemento_id) REFERENCES inventario_general (id),
    CONSTRAINT bajas_ibfk_solicita FOREIGN KEY (usuario_solicita) REFERENCES usuarios (id),
    CONSTRAINT bajas_ibfk_aprueba FOREIGN KEY (aprobado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) mantenimiento: vínculo opcional con inventario_general (el módulo de
--    toma física registra mantenimientos con elemento_id; el flujo heredado
--    con equipos.id sigue intacto).
ALTER TABLE mantenimiento
    MODIFY id_equipo INT(11) DEFAULT NULL,
    ADD COLUMN elemento_id INT(11) DEFAULT NULL AFTER id_equipo,
    ADD COLUMN resultado VARCHAR(50) DEFAULT NULL AFTER observaciones,
    ADD KEY idx_mto_elemento (elemento_id),
    ADD CONSTRAINT mantenimiento_ibfk_elemento FOREIGN KEY (elemento_id) REFERENCES inventario_general (id);

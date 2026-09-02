-- ============================================================
-- MIGRACIÓN: Ampliación del módulo de Préstamos (v2 - corregida)
-- Permite prestar CUALQUIER elemento del inventario_general
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- 1. Nueva tabla: solicitud_elementos (múltiples ítems por solicitud)
CREATE TABLE IF NOT EXISTS `solicitud_elementos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_solicitud` int(11) NOT NULL,
  `id_elemento` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `tipo_prestamo` enum('individual','cantidad') NOT NULL DEFAULT 'individual',
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_se_solicitud` (`id_solicitud`),
  KEY `idx_se_elemento` (`id_elemento`),
  CONSTRAINT `solicitud_elementos_ibfk_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitud_elementos_ibfk_elemento` FOREIGN KEY (`id_elemento`) REFERENCES `inventario_general` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Nueva tabla: prestamo_elementos (detalle de préstamo por elemento)
CREATE TABLE IF NOT EXISTS `prestamo_elementos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_prestamo` int(11) NOT NULL,
  `id_elemento` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `tipo_prestamo` enum('individual','cantidad') NOT NULL DEFAULT 'individual',
  `codigo_interno` varchar(50) DEFAULT NULL,
  `estado_devolucion` enum('Bueno','Regular','Dañado') DEFAULT NULL,
  `observaciones_devolucion` text DEFAULT NULL,
  `evidencia_foto` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pe_prestamo` (`id_prestamo`),
  KEY `idx_pe_elemento` (`id_elemento`),
  CONSTRAINT `prestamo_elementos_ibfk_prestamo` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prestamo_elementos_ibfk_elemento` FOREIGN KEY (`id_elemento`) REFERENCES `inventario_general` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Nueva tabla: prestamo_recordatorios (evitar notificaciones duplicadas)
CREATE TABLE IF NOT EXISTS `prestamo_recordatorios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_prestamo` int(11) NOT NULL,
  `tipo` enum('3_dias','1_dia','hoy','vencido') NOT NULL,
  `enviado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_envio` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prestamo_tipo` (`id_prestamo`,`tipo`),
  KEY `idx_pr_tipo` (`tipo`),
  CONSTRAINT `prestamo_recordatorios_ibfk_prestamo` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Extender tabla prestamos: nuevos estados y campos de devolución
-- Solo agregar columnas que no existan
ALTER TABLE `prestamos`
MODIFY COLUMN `estado` enum('pendiente','aprobado','activo','devuelto','vencido','rechazado','cancelado','extraviado') DEFAULT 'pendiente',
ADD COLUMN IF NOT EXISTS `id_solicitud_elemento` int(11) DEFAULT NULL AFTER `id_solicitud`,
ADD COLUMN IF NOT EXISTS `hora_devolucion` time DEFAULT NULL AFTER `hora_prestamo`,
ADD COLUMN IF NOT EXISTS `estado_devolucion` enum('Bueno','Regular','Dañado') DEFAULT NULL AFTER `estado`,
ADD COLUMN IF NOT EXISTS `observaciones_devolucion` text DEFAULT NULL AFTER `estado_devolucion`,
ADD COLUMN IF NOT EXISTS `evidencia_foto` varchar(255) DEFAULT NULL AFTER `observaciones_devolucion`;

-- 5. Extender tabla solicitudes: campos adicionales para préstamo general
ALTER TABLE `solicitudes`
ADD COLUMN IF NOT EXISTS `id_sede` int(11) DEFAULT NULL AFTER `id_usuario`,
ADD COLUMN IF NOT EXISTS `hora_devolucion_esperada` time DEFAULT NULL AFTER `fecha_devolucion_esperada`,
ADD COLUMN IF NOT EXISTS `observaciones` text DEFAULT NULL AFTER `fecha_atencion`,
ADD KEY IF NOT EXISTS `idx_solicitudes_sede` (`id_sede`);

-- Agregar FK si no existe
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'mic' AND TABLE_NAME = 'solicitudes' 
    AND CONSTRAINT_NAME = 'solicitudes_ibfk_sede');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE `solicitudes` ADD CONSTRAINT `solicitudes_ibfk_sede` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id`) ON DELETE SET NULL', 
    'SELECT "FK solicitudes_ibfk_sede ya existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Poblar campo disponible_para_prestamo = 1 para elementos existentes (ya hecho)
-- UPDATE `inventario_general` SET `disponible_para_prestamo` = 1 WHERE `activo` = 1;

-- 7. Migrar solicitudes existentes: asignar sede por defecto (Sede Principal = 1)
UPDATE `solicitudes` SET `id_sede` = 1 WHERE `id_sede` IS NULL;

SET FOREIGN_KEY_CHECKS=1;

-- ============================================================
-- FIN DE MIGRACIÓN
-- ============================================================
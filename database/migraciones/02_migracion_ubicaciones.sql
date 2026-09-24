-- Migración para la tabla ubicaciones
CREATE TABLE IF NOT EXISTS `ubicaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sede` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_sede` (`id_sede`),
  CONSTRAINT `ubicaciones_ibfk_1` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modificar tabla equipos para aceptar id_ubicacion
ALTER TABLE `equipos` ADD COLUMN IF NOT EXISTS `id_ubicacion` int(11) DEFAULT NULL AFTER `id_sede`;
ALTER TABLE `equipos` ADD CONSTRAINT `fk_equipos_ubicacion` FOREIGN KEY IF NOT EXISTS (`id_ubicacion`) REFERENCES `ubicaciones`(`id`) ON DELETE SET NULL;

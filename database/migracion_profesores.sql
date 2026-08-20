-- Migración: módulo Responsable (profesores)
-- Ejecutar: mysql -u root mic < migracion_profesores.sql

CREATE TABLE IF NOT EXISTS `profesores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `sede_id` int(11) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sede_id` (`sede_id`),
  CONSTRAINT `profesores_ibfk_1` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `inventario_general`
  ADD COLUMN `profesor_id` int(11) DEFAULT NULL AFTER `id_sede`,
  ADD KEY `idx_profesor` (`profesor_id`),
  ADD CONSTRAINT `inventario_general_ibfk_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`);

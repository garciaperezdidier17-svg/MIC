-- Migración para la tabla instituciones
CREATE TABLE IF NOT EXISTS `instituciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `nombre_corto` varchar(50) DEFAULT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `codigo_dane` varchar(50) DEFAULT NULL,
  `rector` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT 'assets/img/logo.png',
  `color_principal` varchar(7) DEFAULT '#0d6efd',
  `color_secundario` varchar(7) DEFAULT '#6c757d',
  `prefijo_inventario` varchar(10) DEFAULT 'MIC',
  `formato_codigo` varchar(100) DEFAULT '{INSTITUCION}-{SEDE}-{UBICACION}-{CONSECUTIVO}',
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar valores por defecto (opcional, si se quiere correr desde SQL)
INSERT INTO `instituciones` (`nombre`, `nombre_corto`, `prefijo_inventario`) 
SELECT 'Institución Educativa 20 de Julio', '20J', '20J' 
WHERE NOT EXISTS (SELECT id FROM `instituciones`);

-- Agregar la relación de la sede con la institución (opcional si sólo se maneja una)
ALTER TABLE `sedes` ADD COLUMN IF NOT EXISTS `id_institucion` int(11) DEFAULT 1;
-- Se podría agregar un FOREIGN KEY, pero por compatibilidad con DB existente lo dejamos simple primero

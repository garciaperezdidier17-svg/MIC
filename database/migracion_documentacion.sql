-- Migración: Documentación y trazabilidad de activos (origen, documentos, proveedores, actas)
-- Ejecutar: mysql -u root mic < migracion_documentacion.sql

-- 1. Tabla proveedores
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(30) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Columnas de adquisición en inventario_general
ALTER TABLE `inventario_general`
  ADD COLUMN `origen_bien` enum('Compra','Donación','Transferencia','Otro') DEFAULT NULL AFTER `profesor_id`,
  ADD COLUMN `documento_no_disponible` tinyint(1) NOT NULL DEFAULT 0 AFTER `origen_bien`,
  ADD COLUMN `proveedor_id` int(11) DEFAULT NULL AFTER `documento_no_disponible`,
  ADD COLUMN `numero_factura` varchar(100) DEFAULT NULL AFTER `proveedor_id`,
  ADD COLUMN `fecha_compra` date DEFAULT NULL AFTER `numero_factura`,
  ADD COLUMN `valor_compra` decimal(12,2) DEFAULT NULL AFTER `fecha_compra`,
  ADD COLUMN `numero_orden_compra` varchar(100) DEFAULT NULL AFTER `valor_compra`,
  ADD COLUMN `fecha_garantia` date DEFAULT NULL AFTER `numero_orden_compra`,
  ADD COLUMN `donante_nombre` varchar(150) DEFAULT NULL AFTER `fecha_garantia`,
  ADD COLUMN `fecha_donacion` date DEFAULT NULL AFTER `donante_nombre`,
  ADD COLUMN `institucion_origen` varchar(150) DEFAULT NULL AFTER `fecha_donacion`,
  ADD COLUMN `fecha_transferencia` date DEFAULT NULL AFTER `institucion_origen`,
  ADD COLUMN `descripcion_origen` text DEFAULT NULL AFTER `fecha_transferencia`,
  ADD COLUMN `documento_adquisicion` varchar(255) DEFAULT NULL AFTER `descripcion_origen`,
  ADD KEY `idx_proveedor` (`proveedor_id`),
  ADD CONSTRAINT `inventario_general_ibfk_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`);

-- 3. Identificación en profesores (para el acta)
ALTER TABLE `profesores`
  ADD COLUMN `identificacion` varchar(30) DEFAULT NULL AFTER `apellido`;

-- 4. Tabla actas (historial de actas de entrega y responsabilidad)
CREATE TABLE IF NOT EXISTS `actas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `responsable_id` int(11) NOT NULL,
  `sede_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `archivo_pdf` varchar(255) NOT NULL,
  `estado` enum('generada','entregada','devuelta','reasignada') NOT NULL DEFAULT 'generada',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `responsable_id` (`responsable_id`),
  KEY `sede_id` (`sede_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `actas_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `profesores` (`id`),
  CONSTRAINT `actas_ibfk_2` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  CONSTRAINT `actas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla acta_elementos (elementos incluidos en cada acta)
CREATE TABLE IF NOT EXISTS `acta_elementos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acta_id` int(11) NOT NULL,
  `elemento_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acta_elemento` (`acta_id`,`elemento_id`),
  KEY `elemento_id` (`elemento_id`),
  CONSTRAINT `acta_elementos_ibfk_1` FOREIGN KEY (`acta_id`) REFERENCES `actas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acta_elementos_ibfk_2` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Elementos existentes sin documento registrado: marcar como documento no disponible
UPDATE `inventario_general` SET `documento_no_disponible` = 1 WHERE `documento_adquisicion` IS NULL AND `activo` = 1;

-- 7. Proveedores de ejemplo
INSERT INTO `proveedores` (`nombre`, `nit`, `telefono`, `correo`, `direccion`) VALUES
('TecnoCompu SAS', '900123456-7', '3214567890', 'ventas@tecnocompu.com', 'Calle 10 #20-30, Centro'),
('Distribuciones Educativas Ltda', '901234567-8', '3109876543', 'contacto@distribucionedu.com', 'Av 15 #30-40');

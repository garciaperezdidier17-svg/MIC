-- ============================================================
-- Migración: Valorización, trazabilidad y alertas del inventario
-- 1) Tabla general de historial de elementos
-- 2) Clave de configuración: días de alerta de garantía
-- ============================================================

CREATE TABLE IF NOT EXISTS `elemento_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `elemento_id` int(11) NOT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `datos_anterior` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acta_id` int(11) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_historial_elemento` (`elemento_id`),
  KEY `idx_historial_tipo` (`tipo_evento`),
  KEY `idx_historial_fecha` (`fecha`),
  CONSTRAINT `fk_historial_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_historial_acta` FOREIGN KEY (`acta_id`) REFERENCES `actas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion` (`clave`, `valor`, `tipo`, `descripcion`)
VALUES ('dias_alerta_garantia', '30', 'entero', 'Días previos al vencimiento de garantía para generar alerta')
ON DUPLICATE KEY UPDATE `clave` = `clave`;

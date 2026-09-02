-- ============================================================
-- MIGRACIÓN COMPLEMENTARIA: Préstamos generales de inventario
-- Permite préstamos multi-elemento desde inventario_general.
-- Reutiliza prestamos/solicitudes como cabecera y las tablas
-- prestamo_elementos / solicitud_elementos como detalle.
-- Compatible hacia atrás con el flujo de equipos existente.
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- 1. Responsable del préstamo (profesor perteneciente a una sede)
ALTER TABLE `solicitudes`
  ADD COLUMN IF NOT EXISTS `id_profesor` int(11) DEFAULT NULL AFTER `id_estudiante`,
  ADD KEY IF NOT EXISTS `idx_solicitudes_profesor` (`id_profesor`);

-- 0b. Fecha/hora del préstamo solicitado (no es la fecha de la solicitud)
ALTER TABLE `solicitudes`
  ADD COLUMN IF NOT EXISTS `fecha_prestamo` date DEFAULT NULL AFTER `fecha_devolucion_esperada`,
  ADD COLUMN IF NOT EXISTS `hora_prestamo` time DEFAULT NULL AFTER `fecha_prestamo`;

ALTER TABLE `prestamos`
  ADD COLUMN IF NOT EXISTS `id_profesor` int(11) DEFAULT NULL AFTER `id_estudiante`,
  ADD KEY IF NOT EXISTS `idx_prestamos_profesor` (`id_profesor`),
  ADD COLUMN IF NOT EXISTS `id_sede` int(11) DEFAULT NULL AFTER `id_profesor`,
  ADD KEY IF NOT EXISTS `idx_prestamos_sede` (`id_sede`),
  MODIFY COLUMN `id_estudiante` int(11) DEFAULT NULL;

-- 2. Cabecera de préstamo general puede no referenciar un equipo
--    (los elementos viven en prestamo_elementos -> inventario_general)
ALTER TABLE `prestamos`
  MODIFY COLUMN `id_equipo` int(11) DEFAULT NULL;

ALTER TABLE `solicitudes`
  MODIFY COLUMN `id_equipo` int(11) DEFAULT NULL;

-- 3. FKs del responsable (solo si no existen)
SET @fk_sol = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'solicitudes'
    AND CONSTRAINT_NAME = 'solicitudes_ibfk_profesor');
SET @sql_sol = IF(@fk_sol = 0,
    'ALTER TABLE `solicitudes` ADD CONSTRAINT `solicitudes_ibfk_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE s1 FROM @sql_sol;
EXECUTE s1;
DEALLOCATE PREPARE s1;

SET @fk_pre = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'prestamos'
    AND CONSTRAINT_NAME = 'prestamos_ibfk_profesor');
SET @sql_pre = IF(@fk_pre = 0,
    'ALTER TABLE `prestamos` ADD CONSTRAINT `prestamos_ibfk_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE s2 FROM @sql_pre;
EXECUTE s2;
DEALLOCATE PREPARE s2;

SET @fk_sede = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'prestamos'
    AND CONSTRAINT_NAME = 'prestamos_ibfk_sede');
SET @sql_sede = IF(@fk_sede = 0,
    'ALTER TABLE `prestamos` ADD CONSTRAINT `prestamos_ibfk_sede` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE s3 FROM @sql_sede;
EXECUTE s3;
DEALLOCATE PREPARE s3;

-- 4. Índices adicionales para consultas de préstamos/alertas
ALTER TABLE `prestamo_elementos`
  ADD KEY IF NOT EXISTS `idx_pe_elemento` (`id_elemento`),
  ADD KEY IF NOT EXISTS `idx_pe_estado_dev` (`estado_devolucion`);

ALTER TABLE `prestamos`
  ADD KEY IF NOT EXISTS `idx_prestamos_devolucion` (`fecha_devolucion_esperada`),
  ADD KEY IF NOT EXISTS `idx_prestamos_estado` (`estado`);

SET FOREIGN_KEY_CHECKS=1;

-- ============================================================
-- FIN
-- ============================================================
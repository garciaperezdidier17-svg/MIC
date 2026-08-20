-- Migración: Unificar inventarios (Equipos + IG)
ALTER TABLE inventario_general
  ADD COLUMN codigo_interno varchar(50) DEFAULT NULL AFTER id,
  ADD COLUMN marca varchar(50) DEFAULT NULL AFTER tipo,
  ADD COLUMN modelo varchar(50) DEFAULT NULL AFTER marca,
  ADD COLUMN numero_serie varchar(50) DEFAULT NULL AFTER modelo,
  ADD COLUMN procesador varchar(50) DEFAULT NULL AFTER numero_serie,
  ADD COLUMN ram varchar(20) DEFAULT NULL AFTER procesador,
  ADD COLUMN almacenamiento varchar(50) DEFAULT NULL AFTER ram,
  ADD COLUMN accesorios text DEFAULT NULL AFTER almacenamiento,
  ADD COLUMN fecha_ingreso date DEFAULT NULL AFTER accesorios,
  ADD COLUMN observacion text DEFAULT NULL AFTER fecha_ingreso,
  ADD COLUMN id_sede int(11) DEFAULT NULL AFTER observacion,
  DROP COLUMN cantidad;

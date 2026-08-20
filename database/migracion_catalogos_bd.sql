-- ============================================================
-- Migración: catálogos administrables en BD
-- Categorías (categorias), Tipos (tipo_equipo) y Estados (estados)
-- ya existen y están sembrados. Esta migración agrega las columnas
-- necesarias para activar/desactivar y controlar la fecha de
-- actualización. NO elimina ni renombra tablas existentes.
-- ============================================================

ALTER TABLE categorias
    ADD COLUMN activo tinyint(1) NOT NULL DEFAULT 1 AFTER descripcion,
    ADD COLUMN updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER created_at;

ALTER TABLE tipo_equipo
    ADD COLUMN activo tinyint(1) NOT NULL DEFAULT 1 AFTER categoria_id,
    ADD COLUMN updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER created_at;

ALTER TABLE estados
    ADD COLUMN updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER created_at;
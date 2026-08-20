<?php
/**
 * Descarga la plantilla oficial de importación de inventario (.xlsx).
 * Solo administradores. El código interno NO se incluye: lo genera el sistema.
 */
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_importacion.php';

if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

generarPlantillaImportacionDescargar();
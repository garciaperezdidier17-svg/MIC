<?php

/**
 * Bootstrap de pruebas MIC.
 * - Carga vendor/autoload.php (PHPUnit + librerías reales del proyecto).
 * - Prepara la base de datos de pruebas mic_test (NUNCA toca "mic").
 * - Carga helpers reales del proyecto (historial, alertas, actas, inventario).
 * - Define variables globales usadas por los helpers (catálogos).
 * No ejecuta páginas completas ni genera HTML.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Entorno de pruebas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];

define('MIC_TESTING', true);

// Variables de servidor mínimas para helpers que las usan
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
if (!isset($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = '/mic/index.php';
}
if (!isset($_SERVER['HTTPS'])) {
    $_SERVER['HTTPS'] = 'off';
}

require_once __DIR__ . '/../vendor/autoload.php';

// Preparación de la base de datos de pruebas (mic_test)
require_once __DIR__ . '/Support/TestDatabase.php';
TestDatabase::preparar();

// Helpers reales del proyecto (funciones puras, sin ejecución de páginas)
require_once __DIR__ . '/../includes/error_handler.php';
$GLOBALS['logsDir'] = __DIR__ . '/../logs';
require_once __DIR__ . '/../modulo_inventario_general/helpers_historial.php';
require_once __DIR__ . '/../modulo_inventario_general/helpers_actas.php';
require_once __DIR__ . '/../modulo_inventario_general/helpers_inventario.php';
require_once __DIR__ . '/../modulo_toma_fisica/helpers_toma_fisica.php';
require_once __DIR__ . '/../modulo_dashboard/helpers_alertas.php';
require_once __DIR__ . '/../modulo_inventario_general/helpers_importacion.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';
require_once __DIR__ . '/../config/helpers_catalogos.php';

// Réplicas de sesión/CSRF (sin conectar a la BD real)
require_once __DIR__ . '/Support/funciones_sesion_test.php';

// Variables globales requeridas por los helpers reales
$GLOBALS['catalogosUbicaciones'] = require __DIR__ . '/../config/ubicaciones.php';
$GLOBALS['catalogos'] = require __DIR__ . '/../config/catalogos_inventario.php';
$GLOBALS['institucion'] = require __DIR__ . '/../config/institucion.php';

// Clase base para integración
require_once __DIR__ . '/Support/TestCaseBase.php';

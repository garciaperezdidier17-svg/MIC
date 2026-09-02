<?php
/**
 * Cron para procesar alertas automáticas de préstamos.
 * Este script debe ser ejecutado por el Programador de tareas de Windows.
 */

// Si la ruta desde donde se ejecuta el script es diferente, 
// nos aseguramos de estar en la carpeta correcta
chdir(__DIR__);

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';
require_once __DIR__ . '/../modulo_prestamos/helpers_prestamos.php';

// Limitar a CLI para seguridad, aunque por simplicidad en desarrollo si se abre en browser solo advertimos
if (php_sapi_name() !== 'cli') {
    echo "Advertencia: Este script fue diseñado para correr por línea de comandos (Cron/Task Scheduler).<br>";
}

date_default_timezone_set('America/Bogota');

echo "Iniciando procesamiento de alertas de prestamos...\n";

$resultado = procesarAlertasAutomaticasPrestamos($conn);

echo "Proceso finalizado.\n";
echo $resultado['msg'];

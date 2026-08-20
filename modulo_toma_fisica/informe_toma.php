<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
require_once '../vendor/autoload.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

use Mpdf\Mpdf;

$tomaId = (int)($_GET['id'] ?? 0);
if (!$tomaId) { die('Toma física no válida'); }

$toma = obtenerToma($conn, $tomaId);
if (!$toma) { die('La toma física no existe'); }
if ($toma['estado'] !== 'finalizada') {
    die('El informe solo puede generarse para tomas físicas finalizadas');
}

$detalles = obtenerDetallesToma($conn, $tomaId);

$stmt = $conn->prepare(
    "SELECT n.*, ig.codigo_interno, ig.nombre AS elemento_nombre
     FROM novedades n
     LEFT JOIN inventario_general ig ON ig.id = n.elemento_id
     WHERE n.toma_fisica_id = ?
     ORDER BY n.id"
);
$stmt->execute([$tomaId]);
$novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$institucion = require __DIR__ . '/../config/institucion.php';
$logo = obtenerLogo() ? __DIR__ . '/../' . obtenerLogo() : '';

try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_top' => 14,
        'margin_bottom' => 14,
        'margin_left' => 14,
        'margin_right' => 14,
    ]);
    $mpdf->SetTitle('Informe Toma Física #' . $tomaId . ' - MIC');
    $mpdf->WriteHTML(construirInformeTomaHTML($toma, $detalles, $novedades, $institucion, $logo));
    $mpdf->Output('informe_toma_' . $tomaId . '_' . date('Y-m-d') . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
} catch (Throwable $e) {
    logError("Error generando informe de toma física: " . $e->getMessage());
    die('Error al generar el informe PDF. Consulte el log del sistema.');
}

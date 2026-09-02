<?php
require_once '../config/conexion.php';
require_once '../vendor/autoload.php';
if (!estaLogueado() || !esAdmin()) { header('Location: ../modulo_login/index.php'); exit; }

use Mpdf\Mpdf;
require_once __DIR__ . '/helpers_actas.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('ID no válido'); }

$stmt = $conn->prepare("SELECT * FROM inventario_general WHERE id=?");
$stmt->execute([$id]);
$elInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$elInfo || $elInfo['estado'] !== 'Dado de baja') {
    die('El elemento no se encuentra dado de baja.');
}

// Buscar el evento de baja en el historial
$stmtHist = $conn->prepare("SELECT datos_nuevos FROM elemento_historial WHERE elemento_id=? AND tipo_evento='baja' ORDER BY id DESC LIMIT 1");
$stmtHist->execute([$id]);
$historial = $stmtHist->fetchColumn();
$datosBaja = $historial ? json_decode($historial, true) : [];

if (empty($datosBaja)) {
    // Fallback if no history for some reason
    $datosBaja = [
        'motivo' => 'N/A',
        'fecha' => date('Y-m-d'),
        'valor_residual' => null,
        'observaciones' => ''
    ];
}

$institucion = require __DIR__ . '/../config/institucion.php';
$logoPath = function_exists('obtenerLogo') && obtenerLogo() ? __DIR__ . '/../' . obtenerLogo() : '';

$mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 14, 'margin_right' => 14]);
$html = construirActaBajaHTML($institucion, $elInfo, $datosBaja, $logoPath);
$mpdf->WriteHTML($html);
$mpdf->Output('Acta_Baja_' . ($elInfo['codigo_interno'] ?: $elInfo['id']) . '.pdf', \Mpdf\Output\Destination::INLINE);

<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT a.id, a.archivo_pdf, a.estado, a.fecha_generacion, p.nombre AS prof_nombre, p.apellido AS prof_apellido, s.nombre AS sede_nombre FROM actas a LEFT JOIN profesores p ON a.responsable_id=p.id LEFT JOIN sedes s ON a.sede_id=s.id WHERE a.id=?");
$stmt->execute([$id]);
$acta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$acta || !$acta['archivo_pdf']) {
    http_response_code(404);
    die('Acta no encontrada');
}

$ruta = __DIR__ . '/../uploads/' . $acta['archivo_pdf'];
if (!is_file($ruta)) {
    http_response_code(404);
    die('El archivo PDF del acta no existe: ' . htmlspecialchars($acta['archivo_pdf']));
}

$accion = $_GET['accion'] ?? 'ver';
$disposition = ($accion === 'descargar') ? 'attachment' : 'inline';
$nombre = 'acta_' . $acta['id'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;

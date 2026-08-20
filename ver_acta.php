<?php
require_once 'config/conexion.php';
if (!estaLogueado()) { header('Location: modulo_login/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$accion = $_GET['accion'] ?? 'ver';
if (!$id) { die('Acta no válida'); }

$stmt = $conn->prepare("SELECT archivo_pdf FROM actas WHERE id=?");
$stmt->execute([$id]);
$archivo = $stmt->fetchColumn();
if (!$archivo) { die('Acta no encontrada'); }

$path = __DIR__ . '/uploads/' . $archivo;
if (!is_file($path)) { die('El archivo del acta no existe en el servidor'); }

$nombreSalida = 'acta_entrega_' . $id . '.pdf';

if ($accion === 'descargar') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreSalida . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombreSalida . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;

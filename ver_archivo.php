<?php
require_once 'config/conexion.php';
require_once 'config/helpers_auditoria.php';
if (!estaLogueado()) { header('Location: modulo_login/index.php'); exit; }

$ruta = $_GET['ruta'] ?? '';
if ($ruta === '') { die('Archivo no válido'); }

$realBase = realpath(__DIR__ . '/uploads');
$realArchivo = realpath(__DIR__ . '/uploads/' . $ruta);
if ($realArchivo === false || strpos($realArchivo, $realBase) !== 0) {
    die('Archivo no válido');
}
if (!is_file($realArchivo)) { die('El archivo no existe en el servidor'); }

$ext = strtolower(pathinfo($realArchivo, PATHINFO_EXTENSION));
$tipos = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'pdf' => 'application/pdf',
];
if (!isset($tipos[$ext])) { die('Tipo de archivo no permitido'); }

// Auditoría: documentos de adquisición de inventario (sin interrumpir la descarga)
if (strpos($ruta, 'documentos/') === 0) {
    try {
        $stmt = $conn->prepare("SELECT id, nombre, codigo_interno, documento_adquisicion FROM inventario_general WHERE documento_adquisicion=? AND activo=1 LIMIT 1");
        $stmt->execute([$ruta]);
        $elemento = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($elemento) {
            registrarAuditoria(
                $conn, 'descargar_documento', 'inventario', 'elemento', (int)$elemento['id'],
                'Documento de adquisición descargado: ' . ($elemento['codigo_interno'] ?: $elemento['nombre']) . ' (' . basename($ruta) . ')',
                null,
                ['nombre' => $elemento['nombre'], 'codigo' => $elemento['codigo_interno'], 'archivo' => $ruta]
            );
        }
    } catch (Throwable $e) {
        logError('Error auditando descarga de documento: ' . $e->getMessage());
    }
}

$nombreSalida = basename($realArchivo);
header('Content-Type: ' . $tipos[$ext]);
header('Content-Disposition: inline; filename="' . $nombreSalida . '"');
header('Content-Length: ' . filesize($realArchivo));
readfile($realArchivo);
exit;

<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_prestamos.php';

header('Content-Type: application/json; charset=utf-8');

if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$idSede = isset($_GET['id_sede']) ? (int)$_GET['id_sede'] : 0;
if ($idSede <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Sede inválida']);
    exit;
}

$profesores = profesoresDeSede($conn, $idSede);
$salida = [];
foreach ($profesores as $p) {
    $salida[] = [
        'id' => (int)$p['id'],
        'nombre' => $p['nombre'],
        'apellido' => $p['apellido'],
        'nombre_completo' => trim($p['nombre'] . ' ' . ($p['apellido'] ?? '')),
        'correo' => $p['correo'] ?? '',
    ];
}

echo json_encode(['ok' => true, 'profesores' => $salida], JSON_UNESCAPED_UNICODE);
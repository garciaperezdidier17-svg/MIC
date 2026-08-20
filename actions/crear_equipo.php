<?php
require_once '../config/conexion.php';

if (!estaLogueado()) {
    header('Location: ../index.php');
    exit;
}
if (!esAdmin()) {
    header('Location: ../modulo_dashboard/index.php');
    exit;
}

$codigoInterno = trim($_POST['codigo_interno'] ?? '');
$nombreEquipo = trim($_POST['nombre'] ?? '');
$idTipo = !empty($_POST['id_tipo']) ? $_POST['id_tipo'] : null;
$idCategoria = !empty($_POST['id_categoria']) ? $_POST['id_categoria'] : null;
$idSede = !empty($_POST['id_sede']) ? $_POST['id_sede'] : null;
$estadoEquipo = $_POST['estado'] ?? 'disponible';
$stockEquipo = intval($_POST['stock']);
$descripcionEquipo = !empty($_POST['descripcion']) ? trim($_POST['descripcion']) : null;
$vrComercial = !empty($_POST['vr_comercial']) ? floatval($_POST['vr_comercial']) : 0;
$vidaUtil = !empty($_POST['vida_util']) ? intval($_POST['vida_util']) : 0;

if (empty($codigoInterno) || empty($nombreEquipo)) {
    $_SESSION['mensaje'] = 'El código interno y el nombre son obligatorios';
    header('Location: ../modulo_inventario/index.php');
    exit;
}

$stmtVerificar = $conn->prepare("SELECT id FROM equipos WHERE codigo_interno = ?");
$stmtVerificar->execute([$codigoInterno]);
if ($stmtVerificar->fetch()) {
    $_SESSION['mensaje'] = 'Ya existe un equipo con ese código interno';
    header('Location: ../modulo_inventario/index.php');
    exit;
}

$stmtInsertar = $conn->prepare("INSERT INTO equipos (codigo_interno, nombre, id_tipo, id_categoria, id_sede, estado, stock, descripcion_articulo, marca, modelo, numero_serie, procesador, ram, almacenamiento, accesorios, fecha_ingreso, observacion, vr_comercial, vida_util)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmtInsertar->execute([$codigoInterno, $nombreEquipo, $idTipo, $idCategoria, $idSede, $estadoEquipo, $stockEquipo, $descripcionEquipo, $_POST['marca'] ?? '', $_POST['modelo'] ?? '', $_POST['numero_serie'] ?? '', $_POST['procesador'] ?? '', $_POST['ram'] ?? '', $_POST['almacenamiento'] ?? '', $_POST['accesorios'] ?? '', $_POST['fecha_ingreso'] ?? null, $_POST['observacion'] ?? '', $vrComercial, $vidaUtil]);

$_SESSION['mensaje'] = 'Equipo agregado correctamente';
header('Location: ../modulo_inventario/index.php');
exit;

<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_prestamos.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }

$usuarioActual = obtenerUsuarioActual();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_solicitud'])) {
    verificarCSRF();
    $items = [];
    foreach ($_POST['elem_id'] ?? [] as $i => $idEl) {
        $idEl = (int)$idEl;
        if ($idEl <= 0) { continue; }
        $items[] = [
            'elemento_id' => $idEl,
            'tipo_prestamo' => (($_POST['elem_tipo'][$i] ?? 'individual') === 'cantidad') ? 'cantidad' : 'individual',
            'cantidad' => max(1, (int)($_POST['elem_cantidad'][$i] ?? 1)),
        ];
    }
    $resultado = crearSolicitud($conn, [
        'usuario_id' => (int)$usuarioActual['id'],
        'id_sede' => (int)($_POST['id_sede'] ?? 0),
        'id_profesor' => (int)($_POST['id_profesor'] ?? 0),
        'motivo' => trim((string)($_POST['motivo'] ?? '')),
        'fecha_prestamo' => trim((string)($_POST['fecha_prestamo'] ?? '')),
        'hora_prestamo' => trim((string)($_POST['hora_prestamo'] ?? '')),
        'fecha_devolucion_esperada' => trim((string)($_POST['fecha_devolucion_esperada'] ?? '')),
        'hora_devolucion_esperada' => trim((string)($_POST['hora_devolucion_esperada'] ?? '')),
        'observaciones' => trim((string)($_POST['observaciones'] ?? '')),
        'items' => $items,
    ]);
    if ($resultado['ok']) {
        $_SESSION['mensaje'] = 'Solicitud enviada correctamente';
    } else {
        $_SESSION['error'] = $resultado['error'];
    }
    header('Location: solicitudes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aprobar_solicitud'])) {
    verificarCSRF();
    $r = aprobarSolicitud($conn, (int)$usuarioActual['id'], (int)$_POST['id']);
    if ($r['ok']) {
        $_SESSION['mensaje'] = 'Solicitud aprobada. Préstamo #' . $r['prestamo_id'] . ' generado.';
    } else {
        $_SESSION['error'] = $r['error'];
    }
    header('Location: solicitudes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rechazar_solicitud'])) {
    verificarCSRF();
    $r = rechazarSolicitud($conn, (int)$usuarioActual['id'], (int)$_POST['id']);
    if ($r['ok']) {
        $_SESSION['mensaje'] = 'Solicitud rechazada correctamente';
    } else {
        $_SESSION['error'] = $r['error'];
    }
    header('Location: solicitudes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_solicitud'])) {
    verificarCSRF();
    $id = (int)$_POST['id'];
    $conn->prepare("DELETE FROM solicitud_elementos WHERE id_solicitud = ?")->execute([$id]);
    $conn->prepare("DELETE FROM solicitudes WHERE id = ?")->execute([$id]);
    $_SESSION['mensaje'] = 'Solicitud eliminada correctamente';
    header('Location: solicitudes.php');
    exit;
}

$sedes = sedesActivas($conn);
$grupos = gruposElementosPrestables($conn);
$sedePorRepresentante = [];
$repIds = array_map('intval', array_column($grupos, 'representante_id'));
if ($repIds) {
    $stmtRep = $conn->query("SELECT id, id_sede FROM inventario_general WHERE id IN (" . implode(',', $repIds) . ")");
    foreach ($stmtRep->fetchAll(PDO::FETCH_ASSOC) as $filaRep) {
        $sedePorRepresentante[(int)$filaRep['id']] = (int)$filaRep['id_sede'];
    }
}

$esUsuarioNormal = !esAdmin();
$filtroUsuario = $esUsuarioNormal ? "WHERE s.id_usuario = " . (int)$usuarioActual['id'] : '';
$filtroUsuarioCount = $esUsuarioNormal ? "WHERE id_usuario = " . (int)$usuarioActual['id'] : '';

$numeroPagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limitePorPagina = 10;
$desplazamiento = ($numeroPagina - 1) * $limitePorPagina;

$filtroStats = $esUsuarioNormal ? 'WHERE id_usuario = ' . (int)$usuarioActual['id'] : '';
$stmtStats = $conn->query("SELECT estado, COUNT(*) as cnt FROM solicitudes $filtroStats GROUP BY estado");
$conteosPorEstado = ['pendiente' => 0, 'aprobada' => 0, 'rechazada' => 0, 'cancelada' => 0];
while ($filaStats = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
    $conteosPorEstado[$filaStats['estado']] = (int)$filaStats['cnt'];
}
$totalPendientes = $conteosPorEstado['pendiente'];
$totalAprobadas = $conteosPorEstado['aprobada'];
$totalRechazadas = $conteosPorEstado['rechazada'];
$totalSolicitudes = array_sum($conteosPorEstado);
$porcentajePendientes = $totalSolicitudes > 0 ? round($totalPendientes / $totalSolicitudes * 100) : 0;
$porcentajeAprobadas = $totalSolicitudes > 0 ? round($totalAprobadas / $totalSolicitudes * 100) : 0;
$porcentajeRechazadas = $totalSolicitudes > 0 ? round($totalRechazadas / $totalSolicitudes * 100) : 0;

$totalRegistros = $conn->query("SELECT COUNT(*) FROM solicitudes $filtroUsuarioCount")->fetchColumn();
$stmtListar = $conn->prepare("SELECT s.*, pr.nombre as profesor_nombre, pr.apellido as profesor_apellido, sd.nombre as sede_nombre
                              FROM solicitudes s
                              LEFT JOIN profesores pr ON s.id_profesor = pr.id
                              LEFT JOIN sedes sd ON s.id_sede = sd.id
                              $filtroUsuario ORDER BY s.id DESC LIMIT $limitePorPagina OFFSET $desplazamiento");
$stmtListar->execute();
$listaSolicitudes = [];
foreach ($stmtListar->fetchAll(PDO::FETCH_ASSOC) as $filaSolicitud) {
    $stmtElems = $conn->prepare("SELECT se.cantidad, se.tipo_prestamo, ig.nombre FROM solicitud_elementos se JOIN inventario_general ig ON se.id_elemento = ig.id WHERE se.id_solicitud = ? ORDER BY se.id");
    $stmtElems->execute([(int)$filaSolicitud['id']]);
    $partes = [];
    foreach ($stmtElems->fetchAll(PDO::FETCH_ASSOC) as $filaElem) {
        $partes[] = $filaElem['tipo_prestamo'] === 'cantidad' ? ($filaElem['nombre'] . ' x' . $filaElem['cantidad']) : $filaElem['nombre'];
    }
    $filaSolicitud['elementos_txt'] = $partes ? implode(', ', $partes) : '—';
    $filaSolicitud['responsable_txt'] = trim(($filaSolicitud['profesor_nombre'] ?? '') . ' ' . ($filaSolicitud['profesor_apellido'] ?? ''));
    $listaSolicitudes[] = $filaSolicitud;
}
$totalPaginas = max(1, (int)ceil($totalRegistros / $limitePorPagina));

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
$errorSolicitudes = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$esAdmin = esAdmin();
require_once '../views/solicitudes_view.php';
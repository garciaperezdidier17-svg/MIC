<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_prestamos.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }

$usuario = obtenerUsuarioActual();
$esAdmin = esAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_prestamo'])) {
    verificarCSRF();
    if (!$esAdmin) { header('Location: prestamos.php'); exit; }
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
    $r = crearSolicitud($conn, [
        'usuario_id' => (int)$usuario['id'],
        'id_sede' => (int)($_POST['id_sede'] ?? 0),
        'id_profesor' => (int)($_POST['id_profesor'] ?? 0),
        'motivo' => 'Préstamo directo',
        'fecha_prestamo' => trim((string)($_POST['fecha_prestamo'] ?? '')),
        'hora_prestamo' => date('H:i:s'),
        'fecha_devolucion_esperada' => trim((string)($_POST['fecha_devolucion'] ?? '')),
        'items' => $items,
    ]);
    if ($r['ok']) {
        $a = aprobarSolicitud($conn, (int)$usuario['id'], $r['solicitud_id']);
        if ($a['ok']) {
            $_SESSION['mensaje'] = 'Préstamo registrado correctamente (# ' . $a['prestamo_id'] . ')';
        } else {
            rechazarSolicitud($conn, (int)$usuario['id'], $r['solicitud_id']);
            $_SESSION['error'] = 'No se pudo generar el préstamo: ' . $a['error'];
        }
    } else {
        $_SESSION['error'] = $r['error'];
    }
    header('Location: prestamos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['devolver'])) {
    verificarCSRF();
    $idPrestamo = (int)$_POST['id'];
    $evidencias = [];
    foreach (($_FILES['dev_evidencia']['name'] ?? []) as $peId => $nombreArchivo) {
        $peId = (int)$peId;
        if ($peId <= 0) { continue; }
        if (!empty($_FILES['dev_evidencia']['tmp_name'][$peId]) && is_uploaded_file($_FILES['dev_evidencia']['tmp_name'][$peId]) && $_FILES['dev_evidencia']['error'][$peId] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            $ruta = dirname(__DIR__) . '/uploads/devoluciones/';
            if (!is_dir($ruta)) { @mkdir($ruta, 0775, true); }
            $nombre = 'dev_' . $peId . '_' . time() . '.' . ($ext ?: 'jpg');
            if (move_uploaded_file($_FILES['dev_evidencia']['tmp_name'][$peId], $ruta . $nombre)) {
                $evidencias[$peId] = 'uploads/devoluciones/' . $nombre;
            }
        }
    }
    $detalles = [];
    foreach (($_POST['dev_estado'] ?? []) as $peId => $estado) {
        $peId = (int)$peId;
        if ($peId <= 0) { continue; }
        if (!isset($_POST['dev_seleccionar'][$peId])) { continue; }
        $detalles[$peId] = [
            'estado' => in_array($estado, ESTADOS_DEVOLUCION, true) ? $estado : 'Bueno',
            'observaciones' => trim((string)($_POST['dev_obs'][$peId] ?? '')),
            'evidencia' => $evidencias[$peId] ?? null,
        ];
    }
    
    if (empty($detalles)) {
        $_SESSION['error'] = 'Debe seleccionar al menos un elemento para devolver.';
        header('Location: prestamos.php');
        exit;
    }
    
    $r = registrarDevolucion($conn, (int)$usuario['id'], $idPrestamo, $detalles);
    if ($r['ok']) {
        $_SESSION['mensaje'] = 'Devolución registrada correctamente';
    } else {
        $_SESSION['error'] = $r['error'];
    }
    header('Location: prestamos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancelar_prestamo'])) {
    verificarCSRF();
    $r = cancelarPrestamo($conn, (int)$usuario['id'], (int)$_POST['id']);
    if ($r['ok']) {
        $_SESSION['mensaje'] = 'Préstamo cancelado correctamente';
    } else {
        $_SESSION['error'] = $r['error'];
    }
    header('Location: prestamos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_prestamo'])) {
    verificarCSRF();
    if (!$esAdmin) { header('Location: prestamos.php'); exit; }
    $id = (int)$_POST['id'];
    foreach ($conn->query("SELECT id_elemento FROM prestamo_elementos WHERE id_prestamo=$id")->fetchAll(PDO::FETCH_COLUMN) as $elId) {
        $conn->prepare("UPDATE inventario_general SET situacion='disponible' WHERE id=?")->execute([(int)$elId]);
    }
    $conn->prepare("DELETE FROM prestamo_elementos WHERE id_prestamo=?")->execute([$id]);
    $conn->prepare("DELETE FROM prestamo_recordatorios WHERE id_prestamo=?")->execute([$id]);
    $conn->prepare("DELETE FROM prestamos WHERE id=?")->execute([$id]);
    $_SESSION['mensaje'] = 'Préstamo eliminado correctamente';
    header('Location: prestamos.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$porPagina = 10;

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

$estadoSel = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
$sedeSel = isset($_GET['sede']) ? (int)$_GET['sede'] : 0;
$buscar = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if (!$esAdmin) {
    $userId = (int)$usuario['id'];
    $stmtProf = $conn->prepare("SELECT id FROM profesores WHERE correo = ?");
    $stmtProf->execute([$usuario['email'] ?? '']);
    $profIds = array_map('intval', $stmtProf->fetchAll(PDO::FETCH_COLUMN));
    $condiciones = ["p.id_estudiante = $userId"];
    if ($profIds) {
        $condiciones[] = 'p.id_profesor IN (' . implode(',', $profIds) . ')';
    }
    $whereSql = '(' . implode(' OR ', $condiciones) . ')';
    $contar = function ($extra = '') use ($conn, $whereSql) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM prestamos p WHERE $whereSql $extra");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    };
    $stats = [
        'activos' => $contar("AND p.estado IN ('activo', 'parcialmente devuelto')"),
        'proximos' => $contar("AND p.estado IN ('activo', 'parcialmente devuelto') AND p.fecha_devolucion_esperada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)"),
        'vence_hoy' => $contar("AND p.estado IN ('activo', 'parcialmente devuelto') AND p.fecha_devolucion_esperada = CURDATE()"),
        'vencidos' => $contar("AND (p.estado='vencido' OR (p.estado IN ('activo', 'parcialmente devuelto') AND p.fecha_devolucion_esperada < CURDATE()))"),
        'devueltos' => $contar("AND p.estado='devuelto'"),
        'total' => $contar(''),
    ];
    $totalRegistros = $stats['total'];
    $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
    if ($page > $totalPaginas) { $page = $totalPaginas; }
    $offset = ($page - 1) * $porPagina;
    $stmtList = $conn->prepare("SELECT DISTINCT p.*, pr.nombre as profesor_nombre, pr.apellido as profesor_apellido, s.nombre as sede_nombre, u.nombre as estudiante_nombre
                                FROM prestamos p
                                LEFT JOIN profesores pr ON p.id_profesor = pr.id
                                LEFT JOIN sedes s ON p.id_sede = s.id
                                LEFT JOIN usuarios u ON p.id_estudiante = u.id
                                WHERE $whereSql ORDER BY p.fecha_prestamo DESC, p.id DESC LIMIT $porPagina OFFSET $offset");
    $stmtList->execute();
    $prestamos = [];
    foreach ($stmtList->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $fila['elementos'] = elementosDePrestamo($conn, (int)$fila['id']);
        $fila['elementos_txt'] = formatearElementos($fila['elementos']);
        $fila['responsable_txt'] = trim(($fila['profesor_nombre'] ?? '') . ' ' . ($fila['profesor_apellido'] ?? '')) ?: ($fila['estudiante_nombre'] ?? '');
        $prestamos[] = $fila;
    }
} else {
    $filtros = [];
    if ($estadoSel !== '') { $filtros['estado'] = $estadoSel; }
    if ($sedeSel > 0) { $filtros['sede'] = $sedeSel; }
    if ($buscar !== '') { $filtros['buscar'] = $buscar; }
    $res = listarPrestamos($conn, $filtros, $page, $porPagina);
    $prestamos = $res['rows'];
    foreach ($prestamos as &$filaP) {
        $filaP['responsable_txt'] = trim(($filaP['profesor_nombre'] ?? '') . ' ' . ($filaP['profesor_apellido'] ?? '')) ?: ($filaP['estudiante_nombre'] ?? '');
    }
    unset($filaP);
    $totalRegistros = $res['total'];
    $totalPaginas = $res['paginas'];
    $page = $res['page'];
    $stats = statsPrestamos($conn, $sedeSel > 0 ? ['id_sede' => $sedeSel] : []);
}

$activos = $stats['activos'];
$devueltos = $stats['devueltos'];

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
$errorPrestamos = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

require_once '../views/prestamos_view.php';
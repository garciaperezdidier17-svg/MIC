<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }

$usuarioActual = obtenerUsuarioActual();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_solicitud'])) {
    verificarCSRF();
    $fechaDevolucion = !empty($_POST['fecha_devolucion_esperada']) ? $_POST['fecha_devolucion_esperada'] : null;
    $stmtInsertar = $conn->prepare("INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo, fecha_devolucion_esperada, estado)
                                    VALUES (?, ?, CURDATE(), ?, ?, ?, 'pendiente')");
    $stmtInsertar->execute([$usuarioActual['id'], $_POST['id_equipo'], date('H:i:s'), trim($_POST['motivo']), $fechaDevolucion]);
    $_SESSION['mensaje'] = 'Solicitud enviada correctamente';
    header('Location: solicitudes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    verificarCSRF();
    $nuevoEstadoSolicitud = $_POST['estado'];
    if (!in_array($nuevoEstadoSolicitud, ['aprobada', 'rechazada'])) {
        $_SESSION['mensaje'] = 'Estado inválido';
        header('Location: solicitudes.php');
        exit;
    }

    $stmtActualizarEstado = $conn->prepare("UPDATE solicitudes SET estado = ?, fecha_atencion = NOW(), id_atendido = ? WHERE id = ?");
    $stmtActualizarEstado->execute([$nuevoEstadoSolicitud, $usuarioActual['id'], $_POST['id']]);

    if ($nuevoEstadoSolicitud === 'aprobada') {
        $stmtBuscarSolicitud = $conn->prepare("SELECT * FROM solicitudes WHERE id = ?");
        $stmtBuscarSolicitud->execute([$_POST['id']]);
        $solicitudAprobada = $stmtBuscarSolicitud->fetch(PDO::FETCH_ASSOC);

        if ($solicitudAprobada) {
            $idEquipoSolicitado = $solicitudAprobada['id_equipo'];
            $idUsuarioSolicitante = $solicitudAprobada['id_usuario'];
            $fechaDevolucionCalculada = $solicitudAprobada['fecha_devolucion_esperada'] ?: date('Y-m-d', strtotime('+7 days'));

            $stmtCrearPrestamo = $conn->prepare("INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, fecha_prestamo, hora_prestamo, fecha_devolucion_esperada, estado)
                                                  VALUES (?, ?, ?, CURDATE(), ?, ?, 'activo')");
            $stmtCrearPrestamo->execute([$solicitudAprobada['id'], $idEquipoSolicitado, $idUsuarioSolicitante, date('H:i:s'), $fechaDevolucionCalculada]);

            $conn->prepare("UPDATE equipos SET estado = 'prestado' WHERE id = ?")->execute([$idEquipoSolicitado]);
        }
    }

    $_SESSION['mensaje'] = "Solicitud $nuevoEstadoSolicitud correctamente";
    header('Location: solicitudes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_solicitud'])) {
    verificarCSRF();
    $conn->prepare("DELETE FROM solicitudes WHERE id = ?")->execute([$_POST['id']]);
    $_SESSION['mensaje'] = 'Solicitud eliminada correctamente';
    header('Location: solicitudes.php');
    exit;
}

$equiposDisponibles = $conn->query("SELECT id, nombre, stock FROM equipos WHERE estado='disponible' AND stock>0 AND activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$esUsuarioNormal = !esAdmin();
$filtroUsuario = $esUsuarioNormal ? "WHERE s.id_usuario = " . (int)$usuarioActual['id'] : '';
$filtroUsuarioCount = $esUsuarioNormal ? "WHERE id_usuario = " . (int)$usuarioActual['id'] : '';

$numeroPagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limitePorPagina = 10;
$desplazamiento = ($numeroPagina - 1) * $limitePorPagina;

$filtroStats = $esUsuarioNormal ? 'WHERE id_usuario = ' . (int)$usuarioActual['id'] : '';
$stmtStats = $conn->query("SELECT estado, COUNT(*) as cnt FROM solicitudes $filtroStats GROUP BY estado");
$conteosPorEstado = ['pendiente' => 0, 'aprobada' => 0, 'rechazada' => 0, 'entregada' => 0, 'devuelta' => 0, 'cancelada' => 0];
while ($fila = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
    $conteosPorEstado[$fila['estado']] = (int)$fila['cnt'];
}
$totalPendientes = $conteosPorEstado['pendiente'];
$totalAprobadas = $conteosPorEstado['aprobada'];
$totalRechazadas = $conteosPorEstado['rechazada'];
$totalSolicitudes = array_sum($conteosPorEstado);
$porcentajePendientes = $totalSolicitudes > 0 ? round($totalPendientes / $totalSolicitudes * 100) : 0;
$porcentajeAprobadas = $totalSolicitudes > 0 ? round($totalAprobadas / $totalSolicitudes * 100) : 0;
$porcentajeRechazadas = $totalSolicitudes > 0 ? round($totalRechazadas / $totalSolicitudes * 100) : 0;

$totalRegistros = $conn->query("SELECT COUNT(*) FROM solicitudes $filtroUsuarioCount")->fetchColumn();
$stmtListar = $conn->prepare("SELECT s.*, e.nombre as equipo_nombre, u.nombre as usuario_nombre
                              FROM solicitudes s JOIN equipos e ON s.id_equipo=e.id JOIN usuarios u ON s.id_usuario=u.id
                              $filtroUsuario ORDER BY s.creado_en DESC LIMIT ? OFFSET ?");
$stmtListar->bindValue(1, $limitePorPagina, PDO::PARAM_INT);
$stmtListar->bindValue(2, $desplazamiento, PDO::PARAM_INT);
$stmtListar->execute();
$listaSolicitudes = $stmtListar->fetchAll(PDO::FETCH_ASSOC);
$totalPaginas = max(1, ceil($totalRegistros / $limitePorPagina));

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$esAdmin = esAdmin();
require_once '../views/solicitudes_view.php';

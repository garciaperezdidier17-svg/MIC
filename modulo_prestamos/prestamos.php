<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }

$usuario = obtenerUsuarioActual();

// --- NUEVO PRÉSTAMO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_prestamo'])) {
    $conn->beginTransaction();

    $stmt = $conn->prepare("INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado, fecha_atencion)
                            VALUES (?, ?, ?, ?, 'Préstamo directo', 'aprobada', NOW())");
    $stmt->execute([$usuario['id'], $_POST['id_equipo'], $_POST['fecha_prestamo'], date('H:i:s')]);
    $id_solicitud = $conn->lastInsertId();

    $nombreEstudiante = trim($_POST['nombre_estudiante']);
    $stmtEst = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ? AND activo = 1");
    $stmtEst->execute([$nombreEstudiante]);
    $idEstudiante = $stmtEst->fetchColumn();
    if (!$idEstudiante) {
        $_SESSION['mensaje'] = 'Estudiante no encontrado. Verifica el nombre.';
        header('Location: prestamos.php');
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, hora_prestamo, estado)
                            VALUES (?, ?, ?, ?, ?, ?, 'activo')");
    $stmt->execute([$id_solicitud, $_POST['id_equipo'], $idEstudiante, $_POST['fecha_prestamo'], $_POST['fecha_devolucion'], date('H:i:s')]);

    $conn->prepare("UPDATE equipos SET estado = 'prestado' WHERE id = ?")->execute([$_POST['id_equipo']]);
    $conn->commit();

    $_SESSION['mensaje'] = 'Préstamo registrado correctamente';
    header('Location: prestamos.php');
    exit;
}

// --- DEVOLUCIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['devolver'])) {
    $id = (int)$_POST['id'];
    $fecha_hoy = date('Y-m-d');
    $hora = date('H:i:s');

    $stmt = $conn->prepare("SELECT id_equipo FROM prestamos WHERE id = ?");
    $stmt->execute([$id]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($prestamo) {
        $conn->prepare("UPDATE prestamos SET fecha_devolucion_real = ?, hora_devolucion = ?, estado = 'devuelto' WHERE id = ?")->execute([$fecha_hoy, $hora, $id]);
        $conn->prepare("UPDATE equipos SET estado = 'disponible' WHERE id = ?")->execute([$prestamo['id_equipo']]);
        $_SESSION['mensaje'] = 'Devolución registrada correctamente';
    }
    header('Location: prestamos.php');
    exit;
}

// --- ELIMINAR PRÉSTAMO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_prestamo'])) {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("SELECT id_equipo, estado FROM prestamos WHERE id = ?");
    $stmt->execute([$id]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prestamo) {
        if ($prestamo['estado'] == 'activo') {
            $conn->prepare("UPDATE equipos SET estado = 'disponible' WHERE id = ?")->execute([$prestamo['id_equipo']]);
        }
        $conn->prepare("DELETE FROM prestamos WHERE id = ?")->execute([$id]);
        $_SESSION['mensaje'] = 'Préstamo eliminado correctamente';
    }
    header('Location: prestamos.php');
    exit;
}

// --- DATOS PARA LA VISTA ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$porPagina = 10;
$offset = ($page - 1) * $porPagina;

$equipos = $conn->query("SELECT id, nombre, stock FROM equipos WHERE estado='disponible' AND stock>0 AND activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$esUsuario = !esAdmin();
$userId = (int)$usuario['id'];
$wherePrestamos = $esUsuario ? "WHERE p.id_estudiante = $userId" : '';
$filterEstudiante = $esUsuario ? "AND id_estudiante = $userId" : '';

$totalRegistros = $conn->query("SELECT COUNT(*) FROM prestamos WHERE 1=1 $filterEstudiante")->fetchColumn();
$totalPaginas = max(1, ceil($totalRegistros / $porPagina));

$baseSql = "SELECT p.*, e.nombre as equipo_nombre, u.nombre as estudiante_nombre
            FROM prestamos p JOIN equipos e ON p.id_equipo=e.id JOIN usuarios u ON p.id_estudiante=u.id
            $wherePrestamos";

$stmt = $conn->prepare("$baseSql ORDER BY p.fecha_prestamo DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, (int)$porPagina, PDO::PARAM_INT);
$stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activos = $conn->query("SELECT COUNT(*) FROM prestamos WHERE estado='activo' $filterEstudiante")->fetchColumn();
$devueltos = $conn->query("SELECT COUNT(*) FROM prestamos WHERE estado='devuelto' $filterEstudiante")->fetchColumn();

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$esAdmin = esAdmin();

// --- CARGAR VISTA ---
require_once '../views/prestamos_view.php';

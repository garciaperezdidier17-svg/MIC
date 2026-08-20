<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_dashboard/index.php'); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    try {
        $conn->prepare("DELETE FROM equipos WHERE id=?")->execute([$id]);
        $_SESSION['mensaje'] = 'Equipo eliminado correctamente';
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = 'No se puede eliminar: el equipo tiene préstamos, solicitudes u otros registros asociados.';
    }
} else {
    $_SESSION['mensaje'] = 'ID de equipo inválido';
}
header('Location: index.php');
exit;

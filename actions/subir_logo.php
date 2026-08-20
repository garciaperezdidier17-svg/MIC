<?php
require_once __DIR__ . '/../config/conexion.php';
if (!estaLogueado() || !esAdmin()) { header('Location: ../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['logo'])) {
    $archivo = $_FILES['logo'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $permisos = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

    if (!in_array($ext, $permisos)) {
        $_SESSION['mensaje'] = 'Formato no permitido. Usa: JPG, PNG, GIF, SVG o WebP';
    } elseif ($archivo['size'] > 2 * 1024 * 1024) {
        $_SESSION['mensaje'] = 'La imagen no debe superar los 2MB';
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['mensaje'] = 'Error al subir el archivo';
    } else {
        $destino = __DIR__ . '/../uploads/logo.' . $ext;
        if (move_uploaded_file($archivo['tmp_name'], $destino)) {
            foreach (glob(__DIR__ . '/../uploads/logo.*') as $f) {
                if ($f !== $destino) unlink($f);
            }
            $_SESSION['mensaje'] = 'Logo actualizado correctamente';
        } else {
            $_SESSION['mensaje'] = 'Error al guardar el logo';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_logo'])) {
    foreach (glob(__DIR__ . '/../uploads/logo.*') as $f) unlink($f);
    $_SESSION['mensaje'] = 'Logo eliminado. Se usará el icono por defecto.';
}

header('Location: ../modulo_dashboard/index.php');
exit;

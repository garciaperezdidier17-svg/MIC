<?php
require_once __DIR__ . '/config/conexion.php';

// Verificar si ya está configurado
$yaConfigurado = false;
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'instituciones'");
    if ($stmt->rowCount() > 0) {
        $check = $conn->query("SELECT COUNT(*) FROM instituciones");
        if ($check->fetchColumn() > 0) {
            $yaConfigurado = true;
        }
    }
} catch (Exception $e) { }

if ($yaConfigurado) {
    die("El sistema ya ha sido configurado. Por seguridad, el instalador ha sido deshabilitado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar instalación
    $nombre = $_POST['nombre'] ?? '';
    $nit = $_POST['nit'] ?? '';
    $formato = $_POST['formato_codigo'] ?? '{INSTITUCION}-{SEDE}-{UBICACION}-{CONSECUTIVO}';
    $prefijo = $_POST['prefijo'] ?? 'MIC';
    
    // Correr migraciones
    $sql1 = file_get_contents(__DIR__ . '/database/migraciones/01_migracion_institucion.sql');
    $sql2 = file_get_contents(__DIR__ . '/database/migraciones/02_migracion_ubicaciones.sql');
    
    try {
        $conn->exec($sql1);
        $conn->exec($sql2);
        
        $ins = $conn->prepare("INSERT INTO instituciones (nombre, nit, formato_codigo, prefijo_inventario) VALUES (?, ?, ?, ?)");
        $ins->execute([$nombre, $nit, $formato, $prefijo]);
        
        // Redirigir al inicio de sesión
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = "Error al instalar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación Inicial - MIC</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .install-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #0d6efd; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; }
        h2 { text-align: center; margin-top: 0; }
    </style>
</head>
<body>
    <div class="install-card">
        <h2>Configuración Inicial de MIC</h2>
        <p>Bienvenido. Por favor, configure los datos de la institución para comenzar a usar el sistema.</p>
        
        <?php if(isset($error)): ?><p style="color:red;"><?php echo $error; ?></p><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nombre de la Institución *</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="form-group">
                <label>NIT / Identificador</label>
                <input type="text" name="nit" class="form-control">
            </div>
            <div class="form-group">
                <label>Prefijo para Inventario *</label>
                <input type="text" name="prefijo" class="form-control" value="MIC" required>
            </div>
            <div class="form-group">
                <label>Formato Código Automático *</label>
                <input type="text" name="formato_codigo" class="form-control" value="{INSTITUCION}-{SEDE}-{UBICACION}-{CONSECUTIVO}" required>
            </div>
            <button type="submit" class="btn">Finalizar Instalación</button>
        </form>
    </div>
</body>
</html>

<?php
require_once 'config/conexion.php';
require_once 'vendor/autoload.php';

function status($ok, $msg) {
    $icon = $ok ? '&#10003;' : '&#10007;';
    $color = $ok ? '#10b981' : '#ef4444';
    return "<tr><td style=\"padding:8px 12px;border-bottom:1px solid #eee;\"><span style=\"color:$color;font-weight:bold;\">$icon</span></td><td style=\"padding:8px 12px;border-bottom:1px solid #eee;\">$msg</td></tr>";
}

$totalOk = 0;
$totalFail = 0;
$rows = '';
ob_start();

// 1. Connection
try {
    $conn->query("SELECT 1");
    $rows .= status(true, "Conexión a MySQL establecida correctamente");
    $totalOk++;
} catch (Exception $e) {
    $rows .= status(false, "Error de conexión: " . $e->getMessage());
    $totalFail++;
}

// 2. Server info
try {
    $v = $conn->query("SELECT VERSION()")->fetchColumn();
    $h = $conn->query("SELECT @@hostname")->fetchColumn();
    $e = $conn->query("SELECT @@default_storage_engine")->fetchColumn();
    $c = $conn->query("SELECT @@character_set_database")->fetchColumn();
    $rows .= status(true, "Servidor: $v &middot; Host: $h &middot; Motor: $e &middot; Charset: $c");
    $totalOk++;
} catch (Exception $e) {
    $rows .= status(false, "Error leyendo info del servidor");
    $totalFail++;
}

// 3. Database name
try {
    $rows .= status(true, "Base de datos: <strong>$dbname</strong>");
    $totalOk++;
} catch (Exception $e) {
    $rows .= status(false, "Error al obtener nombre de BD");
    $totalFail++;
}

// 4. Tables list
try {
    $tables = $conn->query("SELECT TABLE_NAME, TABLE_ROWS, ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbname' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_map(fn($t) => $t['TABLE_NAME'], $tables);
    $list = '';
    foreach ($tables as $t) {
        $list .= "<li>" . $t['TABLE_NAME'] . " (" . number_format((int)$t['TABLE_ROWS']) . " reg, " . $t['ENGINE'] . ")</li>";
    }
    $rows .= status(true, count($tables) . " tablas encontradas:<ul style=\"margin:4px 0 0 16px;font-size:13px;\">$list</ul>");
    $totalOk++;
} catch (Exception $e) {
    $rows .= status(false, "Error consultando tablas: " . $e->getMessage());
    $totalFail++;
}

// 5. Required tables
$required = ['inventario_general', 'sedes', 'usuarios', 'solicitudes', 'prestamos'];
$missing = [];
foreach ($required as $tbl) {
    try {
        $conn->query("SELECT 1 FROM `$tbl` LIMIT 1");
    } catch (Exception $e) {
        $missing[] = $tbl;
    }
}
if (empty($missing)) {
    $rows .= status(true, "Tablas principales accesibles: " . implode(', ', $required));
    $totalOk++;
} else {
    $rows .= status(false, "Faltan tablas: " . implode(', ', $missing));
    $totalFail++;
}

// 6. conexion.php (config)
try {
    $configOk = defined('BASE_URL');
    $rows .= status($configOk, "Archivo config/conexion.php: " . ($configOk ? "OK (BASE_URL = " . BASE_URL . ")" : "BASE_URL no definida"));
    $totalOk++;
} catch (Exception $e) {
    $rows .= status(false, "Error en config/conexion.php");
    $totalFail++;
}

// 7. Session
$sessionOk = session_status() === PHP_SESSION_ACTIVE;
$rows .= status($sessionOk, "Sesión PHP: " . ($sessionOk ? "Activa" : "Inactiva"));
if ($sessionOk) $totalOk++; else $totalFail++;

// 8. Config files
$configFiles = [
    'config/institucion.php' => 'Institución',
    'config/catalogos_inventario.php' => 'Catálogos',
    'config/ubicaciones.php' => 'Ubicaciones',
];
foreach ($configFiles as $path => $label) {
    try {
        $data = require __DIR__ . '/' . $path;
        $ok = !empty($data);
        $rows .= status($ok, "Config $label: " . ($ok ? "OK" : "Vacío"));
        if ($ok) $totalOk++; else $totalFail++;
    } catch (Exception $e) {
        $rows .= status(false, "Config $label: Error - " . $e->getMessage());
        $totalFail++;
    }
}

// 9. Assets dirs
$dirs = ['assets/qr', 'assets/css', 'uploads'];
foreach ($dirs as $d) {
    $p = __DIR__ . '/' . $d;
    $ok = is_dir($p);
    $rows .= status($ok, "Directorio $d: " . ($ok ? "OK" : "No existe"));
    if ($ok) $totalOk++; else $totalFail++;
}

// 10. QR library
$qrOk = class_exists('Endroid\QrCode\Builder\Builder');
$rows .= status($qrOk, "Librería QR (endroid/qr-code): " . ($qrOk ? "Instalada" : "No encontrada"));
if ($qrOk) $totalOk++; else $totalFail++;

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test BD - MIC</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px; }
        .card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.08); padding:32px; max-width:800px; width:100%; }
        h1 { font-size:1.5rem; font-weight:700; margin-bottom:4px; color:#1e293b; }
        .sub { font-size:0.85rem; color:#94a3b8; margin-bottom:20px; }
        .summary { display:flex; gap:16px; margin-bottom:20px; }
        .summary div { flex:1; padding:14px; border-radius:10px; text-align:center; font-weight:600; }
        .summary .ok { background:#ecfdf5; color:#059669; }
        .summary .fail { background:#fef2f2; color:#dc2626; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th { text-align:left; padding:10px 12px; background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; border-bottom:2px solid #e2e8f0; }
        .footer { margin-top:20px; font-size:0.78rem; color:#94a3b8; text-align:center; }
        .footer a { color:#6366f1; text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <h1><span style="color:#6366f1;">&#9881;</span> Test de Base de Datos</h1>
        <div class="sub">Sistema MIC - Diagnóstico de conexión y estructura</div>

        <div class="summary">
            <div class="ok">&#10003; <?php echo $totalOk; ?> correctos</div>
            <?php if ($totalFail > 0): ?>
            <div class="fail">&#10007; <?php echo $totalFail; ?> errores</div>
            <?php endif; ?>
        </div>

        <table>
            <thead><tr><th style="width:36px;">Estado</th><th>Prueba</th></tr></thead>
            <tbody><?php echo $rows; ?></tbody>
        </table>

        <div class="footer">
            Generado el <?php echo date('d/m/Y H:i:s'); ?> &middot;
            <a href="modulo_dashboard/index.php">Volver al Dashboard</a>
        </div>
    </div>
</body>
</html>

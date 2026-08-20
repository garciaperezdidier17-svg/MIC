<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo 'ID no válido'; exit; }

$stmt = $conn->prepare("SELECT ig.*, s.nombre as sede_nombre, p.nombre as prof_nombre, p.apellido as prof_apellido FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id LEFT JOIN profesores p ON ig.profesor_id=p.id WHERE ig.id=? AND ig.activo=1");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) { echo 'Elemento no encontrado'; exit; }

$institucion = require __DIR__ . '/../config/institucion.php';
$codigo = $item['codigo_interno'] ?? '#' . $item['id'];
$qrPath = $item['qr_path'] ? "../assets/{$item['qr_path']}" : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta - <?php echo htmlspecialchars($codigo); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            display:flex; justify-content:center; align-items:center;
            min-height:100vh; font-family:Arial, sans-serif;
        }
        .etiqueta {
            width:350px; padding:20px; border:2px solid #333;
            border-radius:12px; text-align:center; background:#fff;
        }
        .logo {
            font-size:2.2rem; font-weight:900; color:#1a237e;
            letter-spacing:1px; margin-bottom:4px;
        }
        .institucion {
            font-size:0.75rem; font-weight:600; color:#555;
            text-transform:uppercase; margin-bottom:2px;
        }
        .sistema {
            font-size:0.65rem; color:#888; margin-bottom:12px;
        }
        .codigo {
            font-size:1.1rem; font-weight:700; color:#1a237e;
            letter-spacing:0.5px; margin-bottom:10px;
            padding:6px 10px; background:#f0f0f0;
            border-radius:6px; display:inline-block;
        }
        .qr img {
            width:140px; height:140px; margin-bottom:10px;
        }
        .nombre {
            font-size:0.85rem; font-weight:600; color:#333;
            margin-bottom:4px;
        }
        .ubicacion {
            font-size:0.75rem; color:#666;
        }
        .separator {
            height:1px; background:#ddd; margin:10px 0;
        }
        @media print {
            body { margin:0; padding:0; }
            .etiqueta { border-color:#ccc; box-shadow:none; }
        }
    </style>
</head>
<body>
    <div class="etiqueta">
        <div class="logo">MIC</div>
        <div class="institucion"><?php echo htmlspecialchars($institucion['nombre']); ?></div>
        <div class="sistema">Sistema de Inventario y Control</div>
        <div class="separator"></div>
        <div class="codigo"><?php echo htmlspecialchars($codigo); ?></div>
        <div class="qr">
            <?php if ($qrPath): ?>
            <img src="<?php echo htmlspecialchars($qrPath); ?>" alt="QR">
            <?php else: ?>
            <div style="width:140px;height:140px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;margin:0 auto;border-radius:8px;color:#999;font-size:0.75rem;">QR no disponible</div>
            <?php endif; ?>
        </div>
        <div class="nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
        <div class="ubicacion">
            <?php echo htmlspecialchars($item['ubicacion'] ?? ''); ?>
            <?php if ($item['sede_nombre']): ?> · <?php echo htmlspecialchars($item['sede_nombre']); ?><?php endif; ?>
        </div>
        <?php if ($item['prof_nombre']): ?>
        <div class="ubicacion">Resp: <?php echo htmlspecialchars(trim($item['prof_nombre'] . ' ' . $item['prof_apellido'])); ?></div>
        <?php endif; ?>
    </div>
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>

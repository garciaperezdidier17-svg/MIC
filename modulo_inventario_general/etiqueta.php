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
            background:#f0f0f0;
        }
        .etiqueta {
            width:350px; padding:20px; border:2px solid #333;
            border-radius:12px; text-align:center; background:#fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .logo {
            font-size:1.4rem; font-weight:900; color:#1a237e;
            letter-spacing:1px; margin-bottom:15px;
            text-transform: uppercase;
        }
        .qr img {
            width:160px; height:160px; margin-bottom:10px;
        }
        .codigo {
            font-size:1.2rem; font-weight:700; color:#1a237e;
            letter-spacing:0.5px; margin-bottom:8px;
            padding:6px 10px; background:#f5f5f5;
            border-radius:6px; display:inline-block;
        }
        .nombre {
            font-size:0.9rem; font-weight:600; color:#444;
            margin-bottom:12px;
        }
        .info-container {
            text-align: left;
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .info-row {
            margin-bottom: 5px;
            font-size: 0.9rem;
            color: #333;
        }
        .info-row strong {
            color: #1a237e;
        }
        @media print {
            body { margin:0; padding:0; background: #fff; display:block; }
            .etiqueta { border-color:#000; box-shadow:none; border-width: 1px; width:100%; max-width: 350px; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="etiqueta">
        <div class="logo">MIC - INVENTARIO</div>
        
        <div class="qr">
            <?php if ($qrPath): ?>
            <img src="<?php echo htmlspecialchars($qrPath); ?>" alt="QR">
            <?php else: ?>
            <div style="width:160px;height:160px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;margin:0 auto;border-radius:8px;color:#999;font-size:0.75rem;">QR no disponible</div>
            <?php endif; ?>
        </div>

        <div class="codigo"><?php echo htmlspecialchars($codigo); ?></div>
        
        <?php if (!empty($item['nombre'])): ?>
        <div class="nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
        <?php endif; ?>

        <div class="info-container">
            <?php if (!empty($item['sede_nombre'])): ?>
            <div class="info-row"><strong>Sede:</strong> <?php echo htmlspecialchars($item['sede_nombre']); ?></div>
            <?php endif; ?>
            <?php if (!empty($item['ubicacion'])): ?>
            <div class="info-row"><strong>Ubicación:</strong> <?php echo htmlspecialchars($item['ubicacion']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>

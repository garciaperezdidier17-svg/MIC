<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$tomaId = (int)($_GET['id'] ?? 0);
$toma = $tomaId ? obtenerToma($conn, $tomaId) : null;
if (!$toma) {
    $toma = $conn->query("SELECT * FROM tomas_fisicas ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $tomaId = $toma ? (int)$toma['id'] : 0;
}

$detalles = $tomaId ? obtenerDetallesToma($conn, $tomaId) : [];
$novedades = [];
if ($tomaId) {
    $stmt = $conn->prepare(
        "SELECT n.*, ig.codigo_interno, ig.nombre AS elemento_nombre, u.nombre AS usuario_nombre
         FROM novedades n
         LEFT JOIN inventario_general ig ON ig.id = n.elemento_id
         LEFT JOIN usuarios u ON u.id = n.usuario_id
         WHERE n.toma_fisica_id = ?
         ORDER BY n.id"
    );
    $stmt->execute([$tomaId]);
    $novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Detalle de Toma Física - MIC';
require_once '../includes/head.php';
?>
</head>
<body>
<?php
$paginaActual = '../modulo_toma_fisica/ver_toma.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-clipboard-check"></i> Toma física #<?php echo $tomaId ? (int)$toma['id'] : '—'; ?></h2>
        <p>Resumen de verificación física de activos</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
        <?php if ($tomaId && $toma['estado'] === 'finalizada'): ?>
        <a href="informe_toma.php?id=<?php echo (int)$toma['id']; ?>" class="btn btn-primary btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> Informe PDF</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$toma): ?>
<div class="glass-card" style="padding:40px;text-align:center;">
    <p style="margin:0 0 16px;">No hay tomas físicas registradas.</p>
    <a href="index.php" class="btn btn-primary"><i class="fas fa-plus"></i> Iniciar toma física</a>
</div>
<?php require_once '../includes/footer.php'; exit; ?>
<?php endif; ?>

<?php
$encontrados = 0; $noEncontrados = 0; $pendientes = 0; $danados = 0;
foreach ($detalles as $d) {
    if ((int)$d['encontrado'] === 1) {
        $encontrados++;
        if (in_array($d['estado_encontrado'], ['dañado', 'malo', 'fuera de servicio'], true)) $danados++;
    } elseif ($d['verificada_en'] !== null) {
        $noEncontrados++;
    } else {
        $pendientes++;
    }
}
?>

<div class="glass-card" style="padding:20px 24px;margin-bottom:18px;">
    <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:center;">
        <div>
            <h4 style="margin:0 0 6px;font-weight:700;"><i class="fas fa-clipboard-check" style="color:#3b82f6;"></i> <?php echo htmlspecialchars($toma['sede_nombre'] ?? 'Sede'); ?> — <?php echo htmlspecialchars($toma['ubicacion']); ?></h4>
            <p style="margin:0;font-size:0.85rem;color:var(--gray);">
                Iniciada: <?php echo date('d/m/Y H:i', strtotime($toma['fecha_toma'])); ?>
                <?php if ($toma['finalizada_en']): ?> · Finalizada: <?php echo date('d/m/Y H:i', strtotime($toma['finalizada_en'])); ?><?php endif; ?>
                · Usuario: <?php echo htmlspecialchars($toma['usuario_nombre'] ?? '—'); ?>
            </p>
        </div>
        <span class="badge <?php echo $toma['estado'] === 'finalizada' ? 'badge-success' : ($toma['estado'] === 'en_progreso' ? 'badge-warning' : 'badge-secondary'); ?>" style="font-size:0.8rem;">
            <?php echo $toma['estado'] === 'en_progreso' ? 'En progreso' : ($toma['estado'] === 'finalizada' ? 'Finalizada' : 'Cancelada'); ?>
        </span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
        <span class="badge badge-info">Esperados: <?php echo (int)$toma['total_esperados']; ?></span>
        <span class="badge badge-success">Encontrados: <?php echo $encontrados; ?></span>
        <span class="badge badge-danger">No encontrados: <?php echo $noEncontrados; ?></span>
        <span class="badge badge-warning">Dañados: <?php echo $danados; ?></span>
        <span class="badge badge-secondary">Pendientes: <?php echo $pendientes; ?></span>
    </div>
    <?php if ($toma['observaciones']): ?>
    <p style="margin:14px 0 0;font-size:0.85rem;background:rgba(59,130,246,0.07);padding:10px 14px;border-radius:8px;"><strong>Observaciones:</strong> <?php echo htmlspecialchars($toma['observaciones']); ?></p>
    <?php endif; ?>
</div>

<div class="glass-card" style="padding:18px 22px;margin-bottom:18px;">
    <div class="card-header">
        <h3><i class="fas fa-list-check"></i> Activos verificados (<?php echo count($detalles); ?>)</h3>
    </div>
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Elemento</th>
                    <th>Responsable</th>
                    <th>Estado registrado</th>
                    <th>Estado encontrado</th>
                    <th>Coincidencias</th>
                    <th>Resultado</th>
                    <th>Evidencias</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $i => $d): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($d['codigo_interno'] ?? ('#' . $d['elemento_id'])); ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($d['elemento_nombre']); ?></strong>
                        <?php if ($d['marca']): ?><br><small style="color:var(--gray);"><?php echo htmlspecialchars($d['marca']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(trim(($d['prof_nombre'] ?? '') . ' ' . ($d['prof_apellido'] ?? ''))) ?: '<span class="text-muted">—</span>'; ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($d['estado_registrado'] ?: '—'); ?></span></td>
                    <td>
                        <?php if ($d['estado_encontrado']): ?>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($d['estado_encontrado']); ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($d['verificada_en']): ?>
                            <?php
                            $coincidencias = array_filter([
                                'Código' => (int)$d['coincide_codigo'],
                                'Sede' => (int)$d['coincide_sede'],
                                'Ubicación' => (int)$d['coincide_ubicacion'],
                                'Resp.' => (int)$d['coincide_responsable'],
                            ], function ($v) { return $v === 1; });
                            $fallas = count($coincidencias);
                            $totales = 4;
                            if ($fallas === $totales) {
                                echo '<span class="badge badge-success">Todas coinciden</span>';
                            } elseif ($fallas > 0) {
                                echo '<span class="badge badge-warning">' . $fallas . '/4</span>';
                            } else {
                                echo '<span class="badge badge-danger">Ninguna</span>';
                            }
                            ?>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$d['encontrado'] === 1): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Encontrado</span>
                        <?php elseif ($d['verificada_en'] !== null): ?>
                            <span class="badge badge-danger"><i class="fas fa-times"></i> No encontrado</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Pendiente</span>
                        <?php endif; ?>
                        <?php if ($d['situacion_despues']): ?><br><small style="color:var(--gray);">→ <?php echo htmlspecialchars($d['situacion_despues']); ?></small><?php endif; ?>
                        <?php if ($d['observacion']): ?><br><small style="color:var(--gray);"><?php echo htmlspecialchars($d['observacion']); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php $evs = evidenciasDeEntidad($conn, 'verificacion', (int)$d['id']); ?>
                        <?php if ($evs): ?>
                            <?php foreach ($evs as $ev): ?>
                            <a href="../ver_archivo.php?ruta=<?php echo urlencode($ev['archivo']); ?>" target="_blank" title="<?php echo htmlspecialchars($ev['tipo_evidencia'] ?: 'Evidencia'); ?>"><i class="fas fa-file-image"></i></a>
                            <?php endforeach; ?>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$detalles): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--gray);">Sin detalles</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($novedades): ?>
<div class="glass-card" style="padding:18px 22px;margin-bottom:18px;">
    <div class="card-header">
        <h3><i class="fas fa-sticky-note"></i> Novedades registradas (<?php echo count($novedades); ?>)</h3>
    </div>
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr><th>#</th><th>Activo</th><th>Tipo</th><th>Descripción</th><th>Fecha</th><th>Usuario</th></tr>
            </thead>
            <tbody>
                <?php foreach ($novedades as $n): ?>
                <tr>
                    <td><?php echo (int)$n['id']; ?></td>
                    <td><strong style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($n['codigo_interno'] ?? ('#' . $n['elemento_id'])); ?></strong><br><small><?php echo htmlspecialchars($n['elemento_nombre'] ?? ''); ?></small></td>
                    <td><span class="badge badge-warning"><?php echo htmlspecialchars($n['tipo']); ?></span></td>
                    <td><?php echo htmlspecialchars($n['descripcion']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></td>
                    <td><?php echo htmlspecialchars($n['usuario_nombre'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

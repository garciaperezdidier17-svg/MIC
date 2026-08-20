<?php
/**
 * Detalle de una actividad de auditoría (ACTIVIDAD #ID).
 */
require_once '../config/conexion.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$registro = obtenerAuditoria($conn, $id);
if (!$registro) {
    header('Location: index.php');
    exit;
}

$elemento = null;
if ($registro['entidad'] === 'elemento' && $registro['entidad_id']) {
    $stmt = $conn->prepare("SELECT codigo_interno, nombre FROM inventario_general WHERE id=?");
    $stmt->execute([(int)$registro['entidad_id']]);
    $elemento = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pageTitle = 'Actividad #' . $id . ' - Auditoría MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_auditoria/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-history"></i> Actividad <span style="color:var(--primary);">#<?php echo $id; ?></span></h2>
        <p>Detalle completo del registro de auditoría</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver a Auditoría</a>
    </div>
</div>

<div class="glass-card" style="padding:24px;max-width:860px;">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap;">
        <div class="kpi-icon blue-gradient" style="width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-clipboard-check" style="color:#fff;"></i></div>
        <div>
            <div style="font-size:1.05rem;font-weight:700;"><?php echo htmlspecialchars(etiquetaAccionAuditoria($registro['accion'])); ?></div>
            <div style="font-size:0.82rem;color:var(--gray);">
                <span class="badge <?php echo $registro['modulo'] === 'toma_fisica' ? 'badge-warning' : 'badge-info'; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $registro['modulo']))); ?></span>
                <?php echo date('d/m/Y H:i', strtotime($registro['fecha'])); ?>
            </div>
        </div>
    </div>

    <div class="form-separator" style="margin-bottom:14px;"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-info-circle"></i> Datos de la actividad</h4></div>
    <table class="premium-table" style="margin-bottom:20px;">
        <tbody>
            <tr>
                <td style="width:180px;color:var(--gray);font-weight:600;">Usuario</td>
                <td><i class="fas fa-user" style="color:var(--gray);width:16px;"></i> <?php echo htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema'); ?></td>
            </tr>
            <tr>
                <td style="color:var(--gray);font-weight:600;">Acción</td>
                <td><?php echo htmlspecialchars(etiquetaAccionAuditoria($registro['accion'])); ?></td>
            </tr>
            <tr>
                <td style="color:var(--gray);font-weight:600;">Elemento</td>
                <td>
                    <?php if ($elemento): ?>
                    <a href="../ver_articulo.php?codigo=<?php echo urlencode($elemento['codigo_interno']); ?>" style="color:var(--primary);font-weight:600;">
                        <i class="fas fa-qrcode" style="width:16px;"></i> <?php echo htmlspecialchars($elemento['codigo_interno']); ?>
                    </a>
                    <small style="color:var(--gray);display:block;margin-left:20px;"><?php echo htmlspecialchars($elemento['nombre']); ?></small>
                    <?php elseif ($registro['entidad']): ?>
                    <span><?php echo htmlspecialchars(ucfirst($registro['entidad'])); ?><?php echo $registro['entidad_id'] ? ' #' . (int)$registro['entidad_id'] : ''; ?></span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="color:var(--gray);font-weight:600;">Descripción</td>
                <td><?php echo htmlspecialchars($registro['descripcion'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="color:var(--gray);font-weight:600;">Fecha</td>
                <td><?php echo date('d/m/Y H:i:s', strtotime($registro['fecha'])); ?></td>
            </tr>
            <tr>
                <td style="color:var(--gray);font-weight:600;">IP</td>
                <td><code style="background:rgba(255,255,255,0.06);padding:2px 8px;border-radius:6px;"><?php echo htmlspecialchars($registro['ip'] ?? '—'); ?></code></td>
            </tr>
            <tr>
                <td style="color:var(--gray);font-weight:600;">User Agent</td>
                <td style="font-size:0.78rem;color:var(--gray);word-break:break-all;"><?php echo htmlspecialchars($registro['user_agent'] ?? '—'); ?></td>
            </tr>
        </tbody>
    </table>

    <?php
    $claves = array_unique(array_merge(
        $registro['datos_anteriores'] ? array_keys($registro['datos_anteriores']) : [],
        $registro['datos_nuevos'] ? array_keys($registro['datos_nuevos']) : []
    ));
    ?>
    <?php if ($claves): ?>
    <div class="form-separator" style="margin-bottom:14px;"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-exchange-alt"></i> Valores anteriores y nuevos</h4></div>
    <table class="premium-table" style="margin-bottom:0;">
        <thead>
            <tr>
                <th style="width:160px;">Campo</th>
                <th>Datos anteriores</th>
                <th style="width:34px;"></th>
                <th>Datos nuevos</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($claves as $k): ?>
            <?php
            $a = $registro['datos_anteriores'][$k] ?? null;
            $n = $registro['datos_nuevos'][$k] ?? null;
            if ($a === null && ($n === null || $n === '')) continue;
            $valA = $a !== null ? (is_array($a) ? json_encode($a, JSON_UNESCAPED_UNICODE) : (string)$a) : '<span class="text-muted">—</span>';
            $valN = $n !== null ? (is_array($n) ? json_encode($n, JSON_UNESCAPED_UNICODE) : (string)$n) : '<span class="text-muted">—</span>';
            ?>
            <tr>
                <td style="font-weight:600;font-size:0.82rem;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $k))); ?></td>
                <td style="font-size:0.82rem;"><?php echo $valA === '—' ? '<span class="text-muted">—</span>' : htmlspecialchars($valA); ?></td>
                <td style="color:var(--primary);font-weight:600;"><i class="fas fa-long-arrow-alt-right"></i></td>
                <td style="font-size:0.82rem;color:var(--primary);font-weight:600;"><?php echo $valN === '—' ? '<span class="text-muted">—</span>' : htmlspecialchars($valN); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
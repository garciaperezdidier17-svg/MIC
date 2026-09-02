<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_alertas.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_periodo'])) {
    verificarCSRF();
    $dias = (int)($_POST['dias_alerta_garantia'] ?? 30);
    if ($dias < 1 || $dias > 365) {
        $error = 'El periodo debe estar entre 1 y 365 días';
    } else {
        $conn->prepare("UPDATE configuracion SET valor=? WHERE clave='dias_alerta_garantia'")->execute([$dias]);
        $mensaje = 'Periodo de alerta de garantías actualizado a ' . $dias . ' días';
    }
}

$diasGarantia = diasAlertaGarantia($conn);
$alertas = array_merge(calcularAlertas($conn), calcularAlertasPrestamos($conn));

$porPrioridad = ['critica' => [], 'advertencia' => [], 'informacion' => []];
foreach ($alertas as $a) { $porPrioridad[$a['prioridad']][] = $a; }

function valorColumna($r, $clave) {
    switch ($clave) {
        case 'Proveedor': return $r['proveedor_nombre'] ?: '—';
        case 'Fecha compra': return $r['fecha_compra'] ? date('d/m/Y', strtotime($r['fecha_compra'])) : '—';
        case 'Vencimiento': return $r['fecha_garantia'] ? date('d/m/Y', strtotime($r['fecha_garantia'])) : '—';
        case 'Días restantes': return isset($r['dias_restantes']) ? $r['dias_restantes'] . ' día(s)' : '—';
        case 'Días vencido': return isset($r['dias_restantes']) ? $r['dias_restantes'] . ' día(s)' : '—';
        case 'Vida útil': return !empty($r['vida_util']) ? $r['vida_util'] . ' años' : '—';
        case 'Fecha base': return !empty($r['fecha_base']) ? date('d/m/Y', strtotime($r['fecha_base'])) : '—';
        case 'Fin de vida útil': return !empty($r['fecha_fin_vida']) ? date('d/m/Y', strtotime($r['fecha_fin_vida'])) : '—';
        case 'Fecha último mantenimiento': return isset($r['fecha_ultimo_mantenimiento']) && $r['fecha_ultimo_mantenimiento'] !== '—' ? date('d/m/Y', strtotime($r['fecha_ultimo_mantenimiento'])) : '—';
        case 'Próximo mantenimiento': return $r['proximo_mantenimiento'] ?? '—';
        case 'Devolución esperada': return !empty($r['fecha_devolucion_esperada']) ? date('d/m/Y', strtotime($r['fecha_devolucion_esperada'])) : '—';
        case 'Elementos': return $r['elementos'] ?? '—';
    }
    return '—';
}

$pageTitle = 'Centro de Alertas - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_dashboard/alertas.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.alerts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.alert-card {
    padding: 18px 20px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.alert-card .alert-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.alert-card .alert-body h4 {
    font-size: 0.88rem;
    font-weight: 600;
    margin-bottom: 2px;
}
.alert-card .alert-body p {
    font-size: 0.78rem;
    color: var(--gray);
    margin: 0;
}
.alert-lista {
    display: none;
    margin-top: 14px;
}
.alert-lista.abierto { display: block; }
.grupo-prioridad { margin-bottom: 28px; }
.grupo-prioridad > h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    font-size: 1rem;
}
</style>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-bell"></i> Centro de Alertas</h2>
        <p>Alertas calculadas automáticamente desde la base de datos</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert <?php echo isset($error) ? 'alert-danger' : 'alert-success'; ?>"><i class="fas fa-<?php echo isset($error) ? 'exclamation-circle' : 'check-circle'; ?>"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="glass-card" style="padding:18px 22px;margin-bottom:24px;max-width:520px;">
    <h3 style="font-size:0.95rem;margin-bottom:10px;"><i class="fas fa-cog"></i> Periodo de alerta de garantías</h3>
    <form method="POST" action="" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
            <label style="font-size:0.75rem;">Alertar con cuántos días de anticipación</label>
            <input type="number" class="form-control" name="dias_alerta_garantia" value="<?php echo (int)$diasGarantia; ?>" min="1" max="365" required>
        </div>
        <?php echo campoCSRF(); ?>
        <button type="submit" name="guardar_periodo" value="1" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
    </form>
</div>

<?php
$grupos = [
    'critica' => ['titulo' => 'Críticas', 'icono' => 'fas fa-fire', 'color' => '#ef4444'],
    'advertencia' => ['titulo' => 'Advertencias', 'icono' => 'fas fa-exclamation-triangle', 'color' => '#f59e0b'],
    'informacion' => ['titulo' => 'Información', 'icono' => 'fas fa-info-circle', 'color' => '#3b82f6'],
];
$baseCols = ['Código', 'Elemento', 'Sede', 'Ubicación', 'Responsable', 'Estado'];
$estadosBadge = ['bueno' => 'badge-success', 'nuevo' => 'badge-info', 'regular' => 'badge-warning', 'malo' => 'badge-danger'];
foreach ($grupos as $prio => $g):
    if (!$porPrioridad[$prio]) continue;
?>
<div class="grupo-prioridad">
    <h3 style="color:<?php echo $g['color']; ?>;"><i class="<?php echo $g['icono']; ?>"></i> <?php echo $g['titulo']; ?> (<?php echo count($porPrioridad[$prio]); ?>)</h3>
    <div class="alerts-grid">
        <?php foreach ($porPrioridad[$prio] as $alerta): ?>
        <div class="glass-card" style="padding:18px 20px;border-left:4px solid <?php echo $g['color']; ?>;">
            <div class="alert-card" style="padding:0;align-items:flex-start;">
                <div class="alert-icon" style="background:<?php echo $g['color']; ?>1a;color:<?php echo $g['color']; ?>;"><i class="<?php echo $alerta['icono']; ?>"></i></div>
                <div class="alert-body" style="flex:1;min-width:0;">
                    <h4><span style="font-size:1.15rem;color:<?php echo $g['color']; ?>;"><?php echo $alerta['cantidad']; ?></span> <?php echo htmlspecialchars($alerta['titulo']); ?></h4>
                    <p><?php echo htmlspecialchars($alerta['descripcion']); ?></p>
                    <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px;" onclick="toggleAlerta('lista_<?php echo $alerta['clave']; ?>', this)"><i class="fas fa-list"></i> Ver elementos</button>
                </div>
            </div>
            <div class="alert-lista" id="lista_<?php echo $alerta['clave']; ?>">
                <?php if ($alerta['elementos']): ?>
                <div style="overflow-x:auto;">
                    <table class="premium-table" style="font-size:0.78rem;">
                        <thead>
                            <tr>
                                <?php foreach ($baseCols as $bc): ?><th><?php echo $bc; ?></th><?php endforeach; ?>
                                <?php foreach ($alerta['columnas'] as $col): ?><th><?php echo htmlspecialchars($col); ?></th><?php endforeach; ?>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerta['elementos'] as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['codigo_interno'] ?? '—'); ?></td>
                                <td><strong><?php echo htmlspecialchars($r['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['sede_nombre'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($r['ubicacion'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars(trim($r['responsable'] ?? '')) ?: '—'; ?></td>
                                <td><span class="badge <?php echo $estadosBadge[$r['estado']] ?? 'badge-secondary'; ?>"><?php echo ucfirst($r['estado'] ?? '—'); ?></span></td>
                                <?php foreach ($alerta['columnas'] as $col): ?>
                                <td><?php echo htmlspecialchars((string)valorColumna($r, $col)); ?></td>
                                <?php endforeach; ?>
                                <td><?php if (!empty($r['codigo_interno'])): ?><a href="../ver_articulo.php?codigo=<?php echo urlencode($r['codigo_interno']); ?>" class="btn btn-outline btn-sm" title="Ver ficha"><i class="fas fa-eye"></i></a><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="font-size:0.8rem;color:var(--gray);margin:8px 0 0;">Sin elementos.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (!$alertas): ?>
<div class="glass-card" style="padding:40px;text-align:center;">
    <i class="fas fa-check-circle" style="font-size:3rem;color:var(--green);margin-bottom:12px;display:block;"></i>
    <h3 style="font-weight:600;">Sin alertas</h3>
    <p style="color:var(--gray);font-size:0.85rem;">Actualmente no hay alertas en el inventario.</p>
</div>
<?php endif; ?>

<?php
$extraScripts = '
<script>
function toggleAlerta(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    var abierto = el.classList.toggle("abierto");
    btn.innerHTML = abierto ? "<i class=\"fas fa-chevron-up\"></i> Ocultar elementos" : "<i class=\"fas fa-list\"></i> Ver elementos";
}
</script>';
require_once '../includes/footer.php';
?>

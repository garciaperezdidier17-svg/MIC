<?php
/**
 * Auditoría del Sistema: listado de acciones importantes con filtros.
 * Solo usuarios administradores.
 */
require_once '../config/conexion.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$filtros = [
    'buscar'       => trim($_GET['buscar'] ?? ''),
    'usuario_id'   => (int)($_GET['usuario_id'] ?? 0),
    'accion'       => trim($_GET['accion'] ?? ''),
    'modulo'       => trim($_GET['modulo'] ?? ''),
    'fecha_desde'  => trim($_GET['fecha_desde'] ?? ''),
    'fecha_hasta'  => trim($_GET['fecha_hasta'] ?? ''),
];

$registros = auditoriaListar($conn, $filtros);

// Resuelve el código/nombre de los elementos referenciados en la auditoría
$elementosMap = [];
$elementoIds = array_values(array_unique(array_filter(array_map(function ($r) {
    return ($r['entidad'] === 'elemento' && $r['entidad_id']) ? (int)$r['entidad_id'] : 0;
}, $registros))));
if ($elementoIds) {
    $in = implode(',', array_fill(0, count($elementoIds), '?'));
    $stmt = $conn->prepare("SELECT id, codigo_interno, nombre FROM inventario_general WHERE id IN ($in)");
    $stmt->execute($elementoIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $el) {
        $elementosMap[(int)$el['id']] = $el;
    }
}

$usuariosFiltro = auditoriaUsuariosActivos($conn);
$accionesFiltro = auditoriaAccionesUsadas($conn);
$totalAuditoria = (int)$conn->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();

$hayFiltros = $filtros['buscar'] !== '' || $filtros['usuario_id'] > 0 || $filtros['accion'] !== ''
    || $filtros['modulo'] !== '' || $filtros['fecha_desde'] !== '' || $filtros['fecha_hasta'] !== '';

function badgeModuloAuditoria($modulo) {
    $map = [
        'inventario' => 'badge-info',
        'toma_fisica' => 'badge-warning',
        'actas' => 'badge-danger',
        'proveedores' => 'badge-success',
        'reportes' => 'badge-secondary',
        'sistema' => 'badge-primary',
    ];
    return $map[$modulo] ?? 'badge-secondary';
}

$pageTitle = 'Auditoría del Sistema - MIC';
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
        <h2><i class="fas fa-history"></i> Auditoría del Sistema</h2>
        <p>Registro de las acciones importantes realizadas dentro de MIC</p>
    </div>
    <div class="page-actions">
        <span class="badge badge-info" style="padding:8px 14px;font-size:0.8rem;"><i class="fas fa-clipboard-list"></i> <?php echo $totalAuditoria; ?> <?php echo $totalAuditoria === 1 ? 'actividad registrada' : 'actividades registradas'; ?></span>
        <?php if ($hayFiltros): ?>
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Limpiar filtros</a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card" style="padding:18px 22px;margin-bottom:24px;">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;flex:2;min-width:170px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Buscar</label>
            <input type="text" class="form-control" name="buscar" placeholder="Descripción, elemento o usuario..." value="<?php echo htmlspecialchars($filtros['buscar']); ?>">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Usuario</label>
            <select name="usuario_id" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($usuariosFiltro as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>" <?php echo $filtros['usuario_id'] == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Acción</label>
            <select name="accion" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($accionesFiltro as $a): ?>
                <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $filtros['accion'] === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars(etiquetaAccionAuditoria($a)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:120px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Módulo</label>
            <select name="modulo" class="form-control">
                <option value="">Todos</option>
                <?php foreach (MODULOS_AUDITORIA as $m): ?>
                <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $filtros['modulo'] === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $m))); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Fecha desde</label>
            <input type="date" class="form-control" name="fecha_desde" value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
        </div>
        <div class="form-group" style="margin:0;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Fecha hasta</label>
            <input type="date" class="form-control" name="fecha_hasta" value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="height:40px;"><i class="fas fa-filter"></i> Filtrar</button>
    </form>
</div>

<?php if (count($registros) === 0): ?>
<div class="glass-card" style="padding:60px 20px;text-align:center;">
    <i class="fas fa-history" style="font-size:3rem;color:var(--gray-light);margin-bottom:16px;display:block;"></i>
    <h3 style="font-weight:600;margin-bottom:8px;">No hay actividades registradas</h3>
    <p style="color:var(--gray);font-size:0.88rem;"><?php echo $hayFiltros ? 'No se encontraron registros con los filtros actuales.' : 'Las acciones importantes del sistema aparecerán aquí automáticamente.'; ?></p>
</div>
<?php else: ?>
<div class="glass-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Módulo</th>
                    <th>Elemento</th>
                    <th>Descripción</th>
                    <th style="width:110px;">Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r): ?>
                <tr>
                    <td style="white-space:nowrap;font-size:0.8rem;"><?php echo date('d/m/Y H:i', strtotime($r['fecha'])); ?></td>
                    <td>
                        <i class="fas fa-user" style="color:var(--gray);width:14px;"></i>
                        <?php echo htmlspecialchars($r['usuario_nombre'] ?? 'Sistema'); ?>
                    </td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars(etiquetaAccionAuditoria($r['accion'])); ?></span></td>
                    <td><span class="badge <?php echo badgeModuloAuditoria($r['modulo']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $r['modulo']))); ?></span></td>
                    <td style="font-size:0.8rem;">
                        <?php if ($r['entidad'] === 'elemento' && isset($elementosMap[(int)$r['entidad_id']])): ?>
                        <a href="../ver_articulo.php?codigo=<?php echo urlencode($elementosMap[(int)$r['entidad_id']]['codigo_interno'] ?: ''); ?>" style="color:var(--primary);font-weight:600;">
                            <?php echo htmlspecialchars($elementosMap[(int)$r['entidad_id']]['codigo_interno'] ?? ('#' . $r['entidad_id'])); ?>
                        </a>
                        <small style="color:var(--gray);display:block;"><?php echo htmlspecialchars($elementosMap[(int)$r['entidad_id']]['nombre']); ?></small>
                        <?php elseif ($r['entidad']): ?>
                        <span class="text-muted"><?php echo htmlspecialchars(ucfirst($r['entidad'])); ?><?php echo $r['entidad_id'] ? ' #' . (int)$r['entidad_id'] : ''; ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:var(--gray);max-width:320px;"><?php echo htmlspecialchars(mb_strimwidth($r['descripcion'] ?? '', 0, 90, '…')); ?></td>
                    <td>
                        <a href="detalle.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Ver detalle</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:12px 22px;font-size:0.78rem;color:var(--gray);border-top:1px solid rgba(255,255,255,0.06);">
        Mostrando hasta <?php echo count($registros); ?> registros (ordenados del más reciente al más antiguo).
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
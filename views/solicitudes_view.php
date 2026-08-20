<?php
$pageTitle = 'Solicitudes - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = 'solicitudes.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header animate-fade-up" style="margin-bottom:24px;">
    <div class="page-title">
        <h2><i class="fas fa-clipboard-list"></i> Solicitudes</h2>
        <p>Gestión de solicitudes de equipos</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('nuevaSolicitudModal')">
            <i class="fas fa-plus"></i> Nueva Solicitud
        </button>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success animate-fade-down"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="glass-card kpi-card animate-fade-up" style="margin-bottom:0;">
        <div class="kpi-icon yellow-gradient"><i class="fas fa-clock"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalPendientes; ?></div>
            <div class="kpi-label">Pendientes</div>
        </div>
        <div class="kpi-trend <?php echo $totalPendientes > 0 ? 'negative' : 'positive'; ?>">
            <i class="fas fa-<?php echo $totalPendientes > 0 ? 'exclamation' : 'check'; ?>"></i>
            <?php echo $porcentajePendientes; ?>%
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1" style="margin-bottom:0;">
        <div class="kpi-icon green-gradient"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalAprobadas; ?></div>
            <div class="kpi-label">Aprobadas</div>
        </div>
        <div class="kpi-trend positive">
            <i class="fas fa-check"></i>
            <?php echo $porcentajeAprobadas; ?>%
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2" style="margin-bottom:0;">
        <div class="kpi-icon red-gradient"><i class="fas fa-times-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalRechazadas; ?></div>
            <div class="kpi-label">Rechazadas</div>
        </div>
        <div class="kpi-trend negative">
            <i class="fas fa-times"></i>
            <?php echo $porcentajeRechazadas; ?>%
        </div>
    </div>
</div>

<div class="section-header animate-fade-up delay-2" style="margin-top:28px;">
    <h3><i class="fas fa-list"></i> Listado de Solicitudes</h3>
    <p>Todas las solicitudes registradas en el sistema</p>
</div>

<div class="glass-card animate-fade-up delay-3" style="padding:0;overflow:hidden;">
    <table class="premium-table" style="margin-bottom:0;">
        <thead>
            <tr>
                <th>#</th>
                <th>Equipo</th>
                <th>Fecha</th>
                <th>Motivo</th>
                <th>Devolución</th>
                <th>Estado</th>
                <th>Solicitante</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($listaSolicitudes) == 0): ?>
            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-clipboard-list"></i><h3>No hay solicitudes</h3><p>Aún no hay solicitudes registradas.</p></div></td></tr>
            <?php else: ?>
                <?php foreach($listaSolicitudes as $solicitud): ?>
                <tr class="status-<?php echo $solicitud['estado']; ?>">
                    <td><span class="text-muted" style="font-weight:600;">#<?php echo $solicitud['id']; ?></span></td>
                    <td><strong><?php echo htmlspecialchars($solicitud['equipo_nombre']); ?></strong></td>
                    <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                    <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($solicitud['motivo']); ?>">
                        <?php echo htmlspecialchars($solicitud['motivo']); ?>
                    </td>
                    <td><?php echo $solicitud['fecha_devolucion_esperada'] ? date('d/m/Y', strtotime($solicitud['fecha_devolucion_esperada'])) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <span class="badge <?php echo $solicitud['estado'] == 'pendiente' ? 'badge-warning' : ($solicitud['estado'] == 'aprobada' ? 'badge-success' : 'badge-danger'); ?>">
                            <?php echo ucfirst($solicitud['estado']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($solicitud['usuario_nombre'] ?? '-'); ?></td>
                    <td>
                        <div class="action-buttons">
                            <?php if ($esAdmin): ?>
                            <?php if($solicitud['estado'] == 'pendiente'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Aprobar esta solicitud?')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="cambiar_estado" value="1">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <input type="hidden" name="estado" value="aprobada">
                                <button type="submit" class="btn-icon btn-approve" title="Aprobar"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Rechazar esta solicitud?')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="cambiar_estado" value="1">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <input type="hidden" name="estado" value="rechazada">
                                <button type="submit" class="btn-icon btn-reject" title="Rechazar"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta solicitud?')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="eliminar_solicitud" value="1">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <button type="submit" class="btn-icon btn-delete" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if(isset($totalPaginas) && isset($numeroPagina) && $totalPaginas > 1): ?>
    <div class="pagination" style="margin-top:0;">
        <?php if($numeroPagina > 1): ?>
        <a href="?page=<?php echo $numeroPagina-1; ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for($i = 1; $i <= $totalPaginas; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo (isset($numeroPagina) && $i == $numeroPagina) ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if($numeroPagina < $totalPaginas): ?>
        <a href="?page=<?php echo $numeroPagina+1; ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="nuevaSolicitudModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-list"></i> Nueva Solicitud</h3>
            <button class="modal-close" onclick="closeModal('nuevaSolicitudModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="crear_solicitud" value="1">
                <div class="form-group">
                    <label>Equipo <span class="required">*</span></label>
                    <select class="form-control" name="id_equipo" required>
                        <option value="">Seleccionar equipo</option>
                        <?php foreach($equiposDisponibles as $equipo): ?>
                        <option value="<?php echo $equipo['id']; ?>"><?php echo htmlspecialchars($equipo['nombre']); ?> (Stock: <?php echo $equipo['stock']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Motivo <span class="required">*</span></label>
                    <textarea class="form-control" name="motivo" rows="3" placeholder="¿Para qué necesitas el equipo?" required></textarea>
                </div>
                <div class="form-group">
                    <label>Fecha de Devolución Esperada</label>
                    <input type="date" class="form-control" name="fecha_devolucion_esperada" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Enviar Solicitud
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

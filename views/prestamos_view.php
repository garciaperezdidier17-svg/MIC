<?php
// Variables esperadas: $mensaje, $activos, $devueltos, $totalRegistros,
//                      $prestamos, $page, $totalPaginas, $equipos
?>
<?php
$pageTitle = 'Préstamos - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = 'prestamos.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-handshake"></i> Préstamos</h2>
        <p>Control de préstamos de equipos</p>
    </div>
    <?php if (esAdmin()): ?>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addPrestamoModal')">
            <i class="fas fa-plus"></i> Nuevo Préstamo
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="inventory-stats" style="margin-bottom:25px;">
    <div class="stat-mini-card">
        <span class="stat-mini-label">Préstamos Activos</span>
        <div class="stat-mini-value" style="color:var(--success);"><?php echo $activos; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Devueltos</span>
        <div class="stat-mini-value"><?php echo $devueltos; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Total</span>
        <div class="stat-mini-value"><?php echo $totalRegistros; ?></div>
    </div>
</div>

<div class="table-container">
    <table class="premium-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Equipo</th>
                <th>Estudiante</th>
                <th>Fecha Préstamo</th>
                <th>Devolución Esperada</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($prestamos) == 0): ?>
            <tr>
                <td colspan="7"><div class="empty-state"><i class="fas fa-handshake"></i><h3>No hay préstamos</h3><p>Aún no se han registrado préstamos en el sistema.</p></div></td>
            </tr>
            <?php else: ?>
                <?php foreach($prestamos as $p): ?>
                <tr>
                    <td>#<?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['equipo_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($p['estudiante_nombre']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($p['fecha_prestamo'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])); ?></td>
                    <td>
                        <span class="badge <?php echo $p['estado'] == 'activo' ? 'badge-success' : 'badge-info'; ?>">
                            <?php echo ucfirst($p['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if($p['estado'] == 'activo'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Registrar devolución de este equipo?')">
                                <input type="hidden" name="devolver" value="1">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn-icon" style="color:var(--success);border:none;background:none;cursor:pointer;" title="Devolver">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este préstamo? Esta acción no se puede deshacer.')">
                                <input type="hidden" name="eliminar_prestamo" value="1">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn-icon delete" style="border:none;background:none;cursor:pointer;" title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if($totalPaginas > 1): ?>
    <div class="pagination">
        <?php if($page > 1): ?>
        <a href="?page=<?php echo $page-1; ?>" class="page-btn">
            <i class="fas fa-chevron-left"></i> Anterior
        </a>
        <?php endif; ?>

        <div class="d-flex" style="gap:5px;">
            <?php for($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>

        <?php if($page < $totalPaginas): ?>
        <a href="?page=<?php echo $page+1; ?>" class="page-btn">
            Siguiente <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <div class="pagination-info">
        Mostrando página <?php echo $page; ?> de <?php echo $totalPaginas; ?> (<?php echo $totalRegistros; ?> registros)
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="addPrestamoModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-handshake"></i> Nuevo Préstamo</h3>
            <button class="modal-close" onclick="closeModal('addPrestamoModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="crear_prestamo" value="1">
                <div class="form-group">
                    <label>Equipo <span class="required">*</span></label>
                    <select class="form-control" name="id_equipo" required>
                        <option value="">Seleccionar equipo</option>
                        <?php foreach($equipos as $eq): ?>
                        <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq['nombre']); ?> (Stock: <?php echo $eq['stock']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nombre del estudiante <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nombre_estudiante" placeholder="Nombre del estudiante" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Préstamo</label>
                        <input type="date" class="form-control" name="fecha_prestamo" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Devolución</label>
                        <input type="date" class="form-control" name="fecha_devolucion" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Registrar Préstamo</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

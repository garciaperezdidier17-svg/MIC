<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    verificarCSRF();
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $descripcion = trim($_POST['descripcion']);
    $capacidad = $_POST['capacidad'] !== '' ? (int)$_POST['capacidad'] : null;
    if (!empty($nombre)) {
        $conn->prepare("INSERT INTO sedes (nombre, direccion, descripcion, capacidad) VALUES (?, ?, ?, ?)")->execute([$nombre, $direccion, $descripcion, $capacidad]);
        $_SESSION['mensaje'] = 'Sede agregada correctamente';
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    verificarCSRF();
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $descripcion = trim($_POST['descripcion']);
    $capacidad = $_POST['capacidad'] !== '' ? (int)$_POST['capacidad'] : null;
    $conn->prepare("UPDATE sedes SET nombre=?, direccion=?, descripcion=?, capacidad=? WHERE id=?")->execute([$nombre, $direccion, $descripcion, $capacidad, $id]);
    $_SESSION['mensaje'] = 'Sede actualizada correctamente';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    verificarCSRF();
    $id = (int)$_POST['id'];
    $nuevo = (int)$_POST['nuevo_estado'];
    $conn->prepare("UPDATE sedes SET activo=? WHERE id=?")->execute([$nuevo, $id]);
    $_SESSION['mensaje'] = $nuevo ? 'Sede activada correctamente' : 'Sede desactivada correctamente';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_sede_perm'])) {
    verificarCSRF();
    $id = (int)$_POST['id'];
    try {
        $conn->prepare("DELETE FROM sedes WHERE id = ?")->execute([$id]);
        $_SESSION['mensaje'] = 'Sede eliminada permanentemente';
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = 'No se puede eliminar permanentemente: la sede tiene equipos u otros registros asociados. Use "Desactivar" en su lugar.';
    }
    header('Location: index.php');
    exit;
}

$sedes = $conn->query("SELECT * FROM sedes ORDER BY activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);
$total = count($sedes);
$activas = count(array_filter($sedes, fn($s) => $s['activo']));

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$pageTitle = 'Sedes - MIC';
require_once '../includes/head.php';
?>
<style>
.sede-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 18px;
    margin-top: 24px;
}
.sede-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: var(--radius-xl);
    border: 1px solid rgba(255,255,255,0.3);
    box-shadow: var(--shadow-sm);
    transition: var(--transition-bounce);
    overflow: hidden;
    position: relative;
}
.sede-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}
.sede-card-head {
    padding: 20px 20px 14px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.sede-card-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(59,130,246,0.25);
}
.sede-card-info { flex: 1; min-width: 0; }
.sede-card-info h4 { font-size: 1.05rem; font-weight: 700; margin-bottom: 2px; }
.sede-card-info .sede-status { font-size: 0.7rem; }
.sede-card-body { padding: 0 20px 16px; }
.sede-detail {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.82rem;
    color: var(--gray);
    margin-bottom: 8px;
}
.sede-detail i { width: 16px; color: var(--primary); }
.sede-card-footer {
    padding: 10px 20px;
    border-top: 1px solid var(--gray-light);
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    background: rgba(248,250,252,0.5);
}
@media (max-width: 600px) {
    .sede-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<?php
$paginaActual = '../modulo_sedes/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-school"></i> Sedes</h2>
        <p>Gestión de sedes del colegio</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="fas fa-plus"></i> Agregar Sede
        </button>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="inventory-stats">
    <div class="stat-mini-card">
        <span class="stat-mini-label">Total Sedes</span>
        <div class="stat-mini-value"><?php echo $total; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Activas</span>
        <div class="stat-mini-value" style="color:var(--success);"><?php echo $activas; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Inactivas</span>
        <div class="stat-mini-value" style="color:var(--danger);"><?php echo $total - $activas; ?></div>
    </div>
</div>

<?php if(count($sedes) == 0): ?>
<div class="glass-card" style="padding:40px;text-align:center;">
    <i class="fas fa-school" style="font-size:3rem;color:var(--gray);margin-bottom:16px;display:block;"></i>
    <h3 style="color:var(--gray);font-weight:600;">No hay sedes registradas</h3>
    <p style="font-size:0.85rem;color:var(--gray);margin-top:8px;">Agrega la primera sede con el botón "Agregar Sede"</p>
</div>
<?php else: ?>
<div class="sede-grid">
    <?php foreach ($sedes as $s): ?>
    <div class="sede-card" style="<?php echo $s['activo'] ? '' : 'opacity:0.6;'; ?>">
        <div class="sede-card-head">
            <div class="sede-card-icon"><i class="fas fa-school"></i></div>
            <div class="sede-card-info">
                <h4><?php echo htmlspecialchars($s['nombre']); ?></h4>
                <span class="badge <?php echo $s['activo'] ? 'badge-success' : 'badge-danger'; ?> sede-status">
                    <i class="fas fa-circle" style="font-size:0.4rem;margin-right:4px;"></i>
                    <?php echo $s['activo'] ? 'Activa' : 'Inactiva'; ?>
                </span>
            </div>
        </div>
        <div class="sede-card-body">
            <?php if ($s['direccion']): ?>
            <div class="sede-detail"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($s['direccion']); ?></div>
            <?php endif; ?>
            <?php if ($s['capacidad']): ?>
            <div class="sede-detail"><i class="fas fa-users"></i> Capacidad: <?php echo $s['capacidad']; ?> personas</div>
            <?php endif; ?>
            <?php if ($s['descripcion']): ?>
            <div class="sede-detail"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($s['descripcion']); ?></div>
            <?php endif; ?>
            <div class="sede-detail"><i class="fas fa-calendar-alt"></i> Creada: <?php echo date('d/m/Y', strtotime($s['created_at'])); ?></div>
        </div>
        <div class="sede-card-footer">
            <button class="btn-icon" onclick="editar(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['direccion'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['descripcion'] ?? '', ENT_QUOTES); ?>', <?php echo $s['capacidad'] ?? 'null'; ?>)" title="Editar">
                <i class="fas fa-edit"></i>
            </button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('¿<?php echo $s['activo'] ? 'Desactivar' : 'Activar'; ?> esta sede?')">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="cambiar_estado" value="1">
                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                <input type="hidden" name="nuevo_estado" value="<?php echo $s['activo'] ? 0 : 1; ?>">
                <button type="submit" class="btn-icon" style="border:none;background:none;cursor:pointer;color:<?php echo $s['activo'] ? 'var(--danger)' : 'var(--success)'; ?>;" title="<?php echo $s['activo'] ? 'Desactivar' : 'Activar'; ?>">
                    <i class="fas <?php echo $s['activo'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                </button>
            </form>
            <?php if (!$s['activo']): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('¿ESTÁS SEGURO? Esta acción ELIMINARÁ DEFINITIVAMENTE esta sede. No se puede deshacer.')">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="eliminar_sede_perm" value="1">
                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                <button type="submit" class="btn-icon" style="border:none;background:none;cursor:pointer;color:var(--danger);" title="Eliminar Definitivamente">
                    <i class="fas fa-times-circle"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL AGREGAR -->
<div class="modal" id="addModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Agregar Sede</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="agregar" value="1">
                <div class="form-group">
                    <label>Nombre <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nombre" placeholder="Ej: La Paz" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" class="form-control" name="direccion" placeholder="Ej: Calle 50 #40-20">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Capacidad (personas)</label>
                        <input type="number" class="form-control" name="capacidad" min="0" placeholder="Ej: 500">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="2" placeholder="Información adicional..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Guardar</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal" id="editModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Sede</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="editar" value="1">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nombre <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" class="form-control" name="direccion" id="edit_direccion">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Capacidad (personas)</label>
                        <input type="number" class="form-control" name="capacidad" id="edit_capacidad" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>

<script>
function editar(id, nombre, direccion, descripcion, capacidad) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_direccion').value = direccion;
    document.getElementById('edit_descripcion').value = descripcion;
    document.getElementById('edit_capacidad').value = capacidad;
    openModal('editModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>

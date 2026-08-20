<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    verificarCSRF();
    $nombre = trim($_POST['nombre']);
    $nit = trim($_POST['nit'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    if (!empty($nombre)) {
        $conn->prepare("INSERT INTO proveedores (nombre, nit, telefono, correo, direccion) VALUES (?, ?, ?, ?, ?)")
            ->execute([$nombre, $nit ?: null, $telefono ?: null, $correo ?: null, $direccion ?: null]);
        $provId = $conn->lastInsertId();
        registrarAuditoria($conn, 'crear_proveedor', 'proveedores', 'proveedor', $provId, 'Proveedor creado: ' . $nombre, null, ['nombre' => $nombre, 'nit' => $nit ?: null, 'telefono' => $telefono ?: null, 'correo' => $correo ?: null]);
        $_SESSION['mensaje'] = 'Proveedor agregado correctamente';
    }
    header('Location: proveedores.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    verificarCSRF();
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $nit = trim($_POST['nit'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $oldStmt = $conn->prepare("SELECT nombre, nit, telefono, correo, direccion FROM proveedores WHERE id=?");
    $oldStmt->execute([$id]);
    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $conn->prepare("UPDATE proveedores SET nombre=?, nit=?, telefono=?, correo=?, direccion=? WHERE id=?")
        ->execute([$nombre, $nit ?: null, $telefono ?: null, $correo ?: null, $direccion ?: null, $id]);
    if ($old) {
        registrarAuditoria($conn, 'modificar_proveedor', 'proveedores', 'proveedor', $id, 'Proveedor modificado: ' . $nombre, $old, ['nombre' => $nombre, 'nit' => $nit ?: null, 'telefono' => $telefono ?: null, 'correo' => $correo ?: null, 'direccion' => $direccion ?: null]);
    }
    $_SESSION['mensaje'] = 'Proveedor actualizado correctamente';
    header('Location: proveedores.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    verificarCSRF();
    $id = (int)$_POST['id'];
    $nuevo = $_POST['nuevo_estado'] === 'Activo' ? 'Activo' : 'Inactivo';
    $conn->prepare("UPDATE proveedores SET estado=? WHERE id=?")->execute([$nuevo, $id]);
    $_SESSION['mensaje'] = $nuevo === 'Activo' ? 'Proveedor activado correctamente' : 'Proveedor desactivado correctamente';
    header('Location: proveedores.php');
    exit;
}

$proveedores = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM inventario_general ig WHERE ig.proveedor_id=p.id AND ig.activo=1) as elementos FROM proveedores p ORDER BY p.estado='Activo' DESC, p.nombre")->fetchAll(PDO::FETCH_ASSOC);
$total = count($proveedores);
$activos = count(array_filter($proveedores, fn($p) => $p['estado'] === 'Activo'));

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$pageTitle = 'Proveedores - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_inventario_general/proveedores.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-truck"></i> Proveedores</h2>
        <p>Proveedores para la documentación de adquisición de bienes</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="fas fa-plus"></i> Agregar Proveedor
        </button>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="inventory-stats">
    <div class="stat-mini-card">
        <span class="stat-mini-label">Total Proveedores</span>
        <div class="stat-mini-value"><?php echo $total; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Activos</span>
        <div class="stat-mini-value" style="color:var(--success);"><?php echo $activos; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Inactivos</span>
        <div class="stat-mini-value" style="color:var(--danger);"><?php echo $total - $activos; ?></div>
    </div>
</div>

<?php if(count($proveedores) == 0): ?>
<div class="glass-card" style="padding:40px;text-align:center;">
    <i class="fas fa-truck" style="font-size:3rem;color:var(--gray);margin-bottom:16px;display:block;"></i>
    <h3 style="color:var(--gray);font-weight:600;">No hay proveedores registrados</h3>
    <p style="font-size:0.85rem;color:var(--gray);margin-top:8px;">Agrega el primer proveedor con el botón "Agregar Proveedor"</p>
</div>
<?php else: ?>
<div class="glass-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>NIT</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Dirección</th>
                    <th>Elementos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proveedores as $p): ?>
                <tr style="<?php echo $p['estado'] !== 'Activo' ? 'opacity:0.6;' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                    <td><?php echo $p['nit'] ? htmlspecialchars($p['nit']) : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $p['telefono'] ? htmlspecialchars($p['telefono']) : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $p['correo'] ? htmlspecialchars($p['correo']) : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $p['direccion'] ? htmlspecialchars($p['direccion']) : '<span class="text-muted">—</span>'; ?></td>
                    <td><span class="badge badge-info"><?php echo $p['elementos']; ?></span></td>
                    <td><span class="badge <?php echo $p['estado'] === 'Activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo $p['estado']; ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon" onclick="editar(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['nit'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['telefono'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['correo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['direccion'] ?? '', ENT_QUOTES); ?>')" title="Editar"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿<?php echo $p['estado'] === 'Activo' ? 'Desactivar' : 'Activar'; ?> este proveedor?')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="cambiar_estado" value="1">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $p['estado'] === 'Activo' ? 'Inactivo' : 'Activo'; ?>">
                                <button type="submit" class="btn-icon" style="border:none;background:none;cursor:pointer;color:<?php echo $p['estado'] === 'Activo' ? 'var(--danger)' : 'var(--success)'; ?>;" title="<?php echo $p['estado'] === 'Activo' ? 'Desactivar' : 'Activar'; ?>">
                                    <i class="fas <?php echo $p['estado'] === 'Activo' ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- MODAL AGREGAR -->
<div class="modal" id="addModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Agregar Proveedor</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="agregar" value="1">
                <div class="form-group">
                    <label>Nombre <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nombre" placeholder="Ej: TecnoCompu SAS" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>NIT</label>
                        <input type="text" class="form-control" name="nit" placeholder="Ej: 900123456-7">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" class="form-control" name="telefono" placeholder="Ej: 3214567890">
                    </div>
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" class="form-control" name="correo" placeholder="Ej: ventas@proveedor.com">
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" class="form-control" name="direccion" placeholder="Ej: Calle 10 #20-30">
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
            <h3><i class="fas fa-edit"></i> Editar Proveedor</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="editar" value="1">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nombre <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>NIT</label>
                        <input type="text" class="form-control" name="nit" id="edit_nit">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" class="form-control" name="telefono" id="edit_telefono">
                    </div>
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" class="form-control" name="correo" id="edit_correo">
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" class="form-control" name="direccion" id="edit_direccion">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>

<script>
function editar(id, nombre, nit, telefono, correo, direccion) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_nit').value = nit;
    document.getElementById('edit_telefono').value = telefono;
    document.getElementById('edit_correo').value = correo;
    document.getElementById('edit_direccion').value = direccion;
    openModal('editModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>

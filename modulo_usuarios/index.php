<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_usuario'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['mensaje'] = 'Completa todos los campos';
    } elseif (strlen($password) < 4) {
        $_SESSION['mensaje'] = 'La contraseña debe tener al menos 4 caracteres';
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['mensaje'] = 'El usuario ya existe';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtRol = $conn->query("SELECT id FROM roles WHERE nombre = 'estudiante' LIMIT 1");
            $rolId = $stmtRol->fetchColumn() ?: 4;
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $username . '@mic.local', $password_hash, $rolId])) {
                $_SESSION['mensaje'] = 'Usuario creado correctamente';
            } else {
                $_SESSION['mensaje'] = 'Error al crear usuario';
            }
        }
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_usuario'])) {
    $id = (int)$_POST['id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $_SESSION['mensaje'] = 'El nombre de usuario no puede estar vacío';
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $_SESSION['mensaje'] = 'El nombre de usuario ya está en uso';
        } else {
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $conn->prepare("UPDATE usuarios SET nombre = ?, password_hash = ? WHERE id = ?")->execute([$username, $password_hash, $id]);
            } else {
                $conn->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?")->execute([$username, $id]);
            }
            $_SESSION['mensaje'] = 'Usuario actualizado correctamente';
        }
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_usuario'])) {
    $id = (int)$_POST['id'];
    $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?")->execute([$id]);
    $_SESSION['mensaje'] = 'Usuario desactivado correctamente';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_usuario_perm'])) {
    $id = (int)$_POST['id'];
    try {
        $conn->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        $_SESSION['mensaje'] = 'Usuario eliminado permanentemente';
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = 'No se puede eliminar permanentemente: el usuario tiene registros asociados (solicitudes, préstamos, etc.). Use "Desactivar" en su lugar.';
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activar_usuario'])) {
    $id = (int)$_POST['id'];
    $conn->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?")->execute([$id]);
    $_SESSION['mensaje'] = 'Usuario activado correctamente';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_rol'])) {
    $id = (int)$_POST['id'];
    $nuevo_rol = $_POST['nuevo_rol'] === 'admin' ? 'admin' : 'usuario';
    $rol_nombre_busqueda = $_POST['nuevo_rol'] === 'admin' ? 'admin' : 'estudiante';
    $stmtRol = $conn->prepare("SELECT id FROM roles WHERE nombre = ? LIMIT 1");
    $stmtRol->execute([$rol_nombre_busqueda]);
    $nuevo_rol_id = $stmtRol->fetchColumn() ?: ($_POST['nuevo_rol'] === 'admin' ? 1 : 4);
    $conn->prepare("UPDATE usuarios SET rol = ?, rol_id = ? WHERE id = ?")->execute([$nuevo_rol, $nuevo_rol_id, $id]);
    $_SESSION['mensaje'] = 'Rol actualizado correctamente';
    header('Location: index.php');
    exit;
}

$filtro_inactivos = isset($_GET['inactivos']);
$where_activo = $filtro_inactivos ? '' : ' AND activo=1';
$usuarios = $conn->query("SELECT id, nombre, email, activo, rol, creado_en, ultimo_acceso FROM usuarios WHERE id != 1$where_activo ORDER BY creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($usuarios as &$u) {
    if (is_numeric($u['rol'] ?? '')) {
        $u['rol'] = (int)$u['rol'] === 1 ? 'admin' : 'usuario';
    }
}
unset($u);
$total_incluyendo_inactivos = $conn->query("SELECT COUNT(*) FROM usuarios WHERE id != 1")->fetchColumn();
$total_activos = $conn->query("SELECT COUNT(*) FROM usuarios WHERE id != 1 AND activo=1")->fetchColumn();
$total_inactivos = $total_incluyendo_inactivos - $total_activos;
$total = count($usuarios);
$activos = count(array_filter($usuarios, fn($u) => $u['activo']));

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$pageTitle = 'Usuarios - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_usuarios/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-users"></i> Usuarios</h2>
        <p>Usuarios registrados en el sistema</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addUsuarioModal')">
            <i class="fas fa-plus"></i> Agregar Usuario
        </button>
        <?php if ($filtro_inactivos): ?>
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-users"></i> Solo Activos
        </a>
        <?php else: ?>
        <a href="?inactivos=1" class="btn btn-outline">
            <i class="fas fa-eye"></i> Inactivos (<?php echo $total_inactivos; ?>)
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="inventory-stats">
    <div class="stat-mini-card">
        <span class="stat-mini-label">Total Usuarios</span>
        <div class="stat-mini-value"><?php echo $total_incluyendo_inactivos; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Activos</span>
        <div class="stat-mini-value" style="color:var(--success);"><?php echo $total_activos; ?></div>
    </div>
    <div class="stat-mini-card">
        <span class="stat-mini-label">Inactivos</span>
        <div class="stat-mini-value" style="color:var(--danger);"><?php echo $total_inactivos; ?></div>
    </div>
</div>

<div class="glass-card" style="padding:0;margin-top:25px;">
    <div class="table-container" style="border:none;box-shadow:none;">
        <table class="premium-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Email</th>
                    <th>Registrado</th>
                    <th>Último Acceso</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($usuarios) == 0): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No hay usuarios</h3>
                            <p>Aún no se han registrado usuarios en el sistema.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><span class="badge" style="background:var(--primary-subtle);color:var(--primary);">#<?php echo $u['id']; ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-subtle);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:0.8rem;font-weight:700;">
                                    <?php echo strtoupper(substr($u['nombre'], 0, 1)); ?>
                                </div>
                                <strong><?php echo htmlspecialchars($u['nombre']); ?></strong>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'badge-info' : 'badge-success'; ?>">
                                <i class="fas <?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'fa-crown' : 'fa-user'; ?>" style="font-size:0.65rem;margin-right:4px;"></i>
                                <?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'Admin' : 'Usuario'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($u['creado_en'])); ?></td>
                        <td><?php echo $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '<span style="color:var(--gray);">Nunca</span>'; ?></td>
                        <td>
                            <span class="badge <?php echo $u['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                <i class="fas fa-circle" style="font-size:0.45rem;margin-right:4px;"></i>
                                <?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="editarUsuario(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>')" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿<?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'Quitar permisos de administrador' : 'Hacer administrador'; ?> a este usuario?')">
                                    <input type="hidden" name="cambiar_rol" value="1">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="nuevo_rol" value="<?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'usuario' : 'admin'; ?>">
                                    <button type="submit" class="btn-icon" style="<?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'color:var(--warning);' : 'color:var(--primary);'; ?>" title="<?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'Quitar Admin' : 'Hacer Admin'; ?>">
                                        <i class="fas <?php echo ($u['rol'] ?? 'usuario') === 'admin' ? 'fa-user-minus' : 'fa-user-shield'; ?>"></i>
                                    </button>
                                </form>
                                <?php if ($u['activo']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar este usuario?')">
                                    <input type="hidden" name="eliminar_usuario" value="1">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-icon delete" style="border:none;background:none;cursor:pointer;" title="Desactivar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Activar este usuario?')">
                                    <input type="hidden" name="activar_usuario" value="1">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-icon" style="border:none;background:none;cursor:pointer;color:var(--success);" title="Activar">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿ESTÁS SEGURO? Esta acción ELIMINARÁ DEFINITIVAMENTE este usuario. No se puede deshacer.')">
                                    <input type="hidden" name="eliminar_usuario_perm" value="1">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-icon" style="border:none;background:none;cursor:pointer;color:var(--danger);" title="Eliminar Definitivamente">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="addUsuarioModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Agregar Usuario</h3>
            <button class="modal-close" onclick="closeModal('addUsuarioModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="crear_usuario" value="1">
                <div class="form-group">
                    <label>Usuario <span class="required">*</span></label>
                    <input type="text" class="form-control" name="username" placeholder="Nombre de usuario" required>
                </div>
                <div class="form-group">
                    <label>Contraseña <span class="required">*</span></label>
                    <input type="password" class="form-control" name="password" placeholder="Mínimo 4 caracteres" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Crear Usuario
                </button>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="editUsuarioModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Usuario</h3>
            <button class="modal-close" onclick="closeModal('editUsuarioModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="editar_usuario" value="1">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Usuario <span class="required">*</span></label>
                    <input type="text" class="form-control" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña <span style="color:var(--gray);font-size:0.75rem;">(dejar vacío para mantener)</span></label>
                    <input type="password" class="form-control" name="password" placeholder="Solo si quieres cambiarla">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function editarUsuario(id, nombre) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = nombre;
    openModal('editUsuarioModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>

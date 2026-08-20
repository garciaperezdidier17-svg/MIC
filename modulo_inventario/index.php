<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }

$esAdmin = esAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_todos_equipos'])) {
    if ($esAdmin) {
        try {
            $conn->exec("DELETE FROM equipos");
            $_SESSION['mensaje'] = 'Todos los equipos han sido eliminados permanentemente';
        } catch (PDOException $e) {
            $_SESSION['mensaje'] = 'No se pueden eliminar todos: hay equipos con préstamos activos u otros registros asociados. Elimínalos uno por uno.';
        }
    }
    header('Location: index.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtro_sede = isset($_GET['sede']) ? (int)$_GET['sede'] : 0;
$filtro_tipo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : 0;
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

$where = "WHERE e.activo = 1";
$params = [];
if ($search != '') {
    $where .= " AND (e.codigo_interno LIKE ? OR e.nombre LIKE ? OR e.descripcion_articulo LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filtro_sede > 0) { $where .= " AND e.id_sede = ?"; $params[] = $filtro_sede; }
if ($filtro_tipo > 0) { $where .= " AND e.id_tipo = ?"; $params[] = $filtro_tipo; }
if ($filtro_estado != '') { $where .= " AND e.estado = ?"; $params[] = $filtro_estado; }

$total = $conn->prepare("SELECT COUNT(*) FROM equipos e $where");
$total->execute($params);
$totalEquipos = $total->fetchColumn();

$stats = $conn->prepare("SELECT
    SUM(CASE WHEN e.estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN e.estado = 'mantenimiento' THEN 1 ELSE 0 END) as mantenimiento,
    SUM(CASE WHEN e.stock < e.stock_minimo THEN 1 ELSE 0 END) as stockBajo
    FROM equipos e $where");
$stats->execute($params);
$statsData = $stats->fetch(PDO::FETCH_ASSOC);

$sql = "SELECT e.*, s.nombre as sede_nombre, t.nombre_tipo as tipo_nombre
        FROM equipos e
        LEFT JOIN sedes s ON e.id_sede = s.id
        LEFT JOIN tipo_equipo t ON e.id_tipo = t.id
        $where
        ORDER BY e.nombre ASC";
$stmtEquipos = $conn->prepare($sql);
$stmtEquipos->execute($params);
$equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);

$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$tiposEquipo = $conn->query("SELECT id, nombre_tipo FROM tipo_equipo")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $conn->query("SELECT id, nombre FROM categorias")->fetchAll(PDO::FETCH_ASSOC);

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
?>
<?php
$pageTitle = 'Inventario Equipos - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_inventario/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header animate-fade-up" style="margin-bottom:20px;">
    <div class="page-title">
        <h2><i class="fas fa-boxes"></i> Inventario Equipos</h2>
        <p>Gestión de equipos tecnológicos</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addEquipoModal')">
            <i class="fas fa-plus"></i> Agregar Equipo
        </button>
        <?php if($esAdmin): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar TODOS los equipos? Esta acción no se puede deshacer.')">
            <input type="hidden" name="eliminar_todos_equipos" value="1">
            <button type="submit" class="btn btn-danger btn-sm" style="padding:10px 16px;font-size:0.8rem;"><i class="fas fa-trash-alt"></i> Eliminar Todos</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success animate-fade-down"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon blue-gradient"><i class="fas fa-laptop"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalEquipos; ?></div>
            <div class="kpi-label">Total Equipos</div>
        </div>
    </div>
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon green-gradient"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $statsData['disponibles']; ?></div>
            <div class="kpi-label">Disponibles</div>
        </div>
    </div>
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon yellow-gradient"><i class="fas fa-tools"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $statsData['mantenimiento']; ?></div>
            <div class="kpi-label">Mantenimiento</div>
        </div>
    </div>
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon red-gradient"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $statsData['stockBajo']; ?></div>
            <div class="kpi-label">Stock Bajo</div>
        </div>
    </div>
</div>

<div class="glass-card" style="padding:18px 22px;margin-bottom:24px;">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;flex:2;min-width:180px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Buscar</label>
            <input type="text" class="form-control" name="search" placeholder="Código, nombre o descripción..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Sede</label>
            <select name="sede" class="form-control">
                <option value="">Todas</option>
                <?php foreach($sedes as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $filtro_sede == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos</option>
                <?php foreach($tiposEquipo as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo $filtro_tipo == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nombre_tipo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:120px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Estado</label>
            <select name="estado" class="form-control">
                <option value="">Todos</option>
                <option value="disponible" <?php echo $filtro_estado == 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                <option value="prestado" <?php echo $filtro_estado == 'prestado' ? 'selected' : ''; ?>>Prestado</option>
                <option value="mantenimiento" <?php echo $filtro_estado == 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                <option value="dañado" <?php echo $filtro_estado == 'dañado' ? 'selected' : ''; ?>>Dañado</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:40px;"><i class="fas fa-search"></i></button>
        <?php if($search || $filtro_sede || $filtro_tipo || $filtro_estado): ?>
        <a href="index.php" class="btn btn-outline" style="height:40px;"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<?php if(count($equipos) == 0): ?>
<div class="glass-card" style="padding:60px 20px;text-align:center;">
    <i class="fas fa-boxes" style="font-size:3rem;color:var(--gray-light);margin-bottom:16px;display:block;"></i>
    <h3 style="font-weight:600;margin-bottom:8px;">No hay equipos</h3>
    <p style="color:var(--gray);font-size:0.88rem;">No se encontraron equipos con los filtros actuales.</p>
</div>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="premium-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Sede</th>
                <th>Stock</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($equipos as $eq): ?>
            <tr class="status-<?php echo $eq['estado']; ?>">
                <td><span class="text-muted" style="font-weight:600;font-size:0.8rem;"><?php echo htmlspecialchars($eq['codigo_interno']); ?></span></td>
                <td><strong style="font-size:0.88rem;"><?php echo htmlspecialchars($eq['nombre']); ?></strong></td>
                <td><?php echo $eq['tipo_nombre'] ? htmlspecialchars($eq['tipo_nombre']) : '<span class="text-muted">—</span>'; ?></td>
                <td><?php echo $eq['sede_nombre'] ? htmlspecialchars($eq['sede_nombre']) : '<span class="text-muted">—</span>'; ?></td>
                <td><span class="badge <?php echo $eq['stock'] <= $eq['stock_minimo'] ? 'badge-danger' : 'badge-info'; ?>"><?php echo $eq['stock']; ?></span></td>
                <td>
                    <span class="badge <?php echo $eq['estado'] == 'disponible' ? 'badge-success' : ($eq['estado'] == 'prestado' ? 'badge-warning' : ($eq['estado'] == 'mantenimiento' ? 'badge-info' : 'badge-danger')); ?>">
                        <?php echo ucfirst($eq['estado']); ?>
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="verQR(<?php echo $eq['id']; ?>, '<?php echo htmlspecialchars($eq['codigo_interno']); ?>')" title="Ver QR"><i class="fas fa-qrcode"></i></button>
                        <?php if($esAdmin): ?>
                        <a href="editar.php?id=<?php echo $eq['id']; ?>" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="eliminar.php" style="display:inline;" onsubmit="return confirm('¿Eliminar este equipo?')">
                            <input type="hidden" name="id" value="<?php echo $eq['id']; ?>">
                            <button type="submit" class="btn-icon btn-delete" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- QR MODAL -->
<div class="modal" id="qrModal">
    <div class="modal-content" style="max-width:400px;text-align:center;">
        <div class="modal-header">
            <h3><i class="fas fa-qrcode"></i> Código QR del Equipo</h3>
            <button class="modal-close" onclick="closeModal('qrModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="qrCodeContainer" style="display:flex;justify-content:center;margin-bottom:20px;"></div>
            <p><strong id="qrEquipoNombre"></strong></p>
            <p>Código: <span id="qrEquipoCodigo"></span></p>
            <button class="btn btn-primary" onclick="imprimirQR()"><i class="fas fa-print"></i> Imprimir QR</button>
            <button class="btn btn-outline" onclick="descargarQR()"><i class="fas fa-download"></i> Descargar</button>
        </div>
    </div>
</div>

<!-- ADD EQUIPO MODAL -->
<div class="modal" id="addEquipoModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Agregar Equipo</h3>
            <button class="modal-close" onclick="closeModal('addEquipoModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="../actions/crear_equipo.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>Código Interno <span class="required">*</span></label>
                        <input type="text" class="form-control" name="codigo_interno" required>
                    </div>
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select class="form-control" name="id_tipo">
                            <option value="">Seleccionar</option>
                            <?php foreach($tiposEquipo as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre_tipo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Dependencia</label>
                        <select class="form-control" name="id_categoria">
                            <option value="">Seleccionar</option>
                            <?php foreach($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Sede</label>
                    <select class="form-control" name="id_sede">
                        <option value="">Seleccionar</option>
                        <?php foreach($sedes as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Estado</label>
                        <select class="form-control" name="estado">
                            <option value="disponible">Disponible</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="prestado">Prestado</option>
                            <option value="dañado">Dañado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stock <span class="required">*</span></label>
                        <input type="number" class="form-control" name="stock" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3" placeholder="Características adicionales..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de Ingreso</label>
                        <input type="date" class="form-control" name="fecha_ingreso" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Observación</label>
                        <textarea class="form-control" name="observacion" rows="2" placeholder="Observaciones adicionales"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>VR Comercial</label>
                        <input type="number" class="form-control" name="vr_comercial" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Vida Útil (años)</label>
                        <input type="number" class="form-control" name="vida_util" min="0" placeholder="Años">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Guardar Equipo</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

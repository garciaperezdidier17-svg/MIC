<?php
require_once '../config/conexion.php';

if (!estaLogueado()) {
    header('Location: ../modulo_login/index.php');
    exit;
}
if (!esAdmin()) {
    header('Location: ../modulo_dashboard/index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.*, s.nombre as sede_nombre, t.nombre_tipo as tipo_nombre
                        FROM equipos e
                        LEFT JOIN sedes s ON e.id_sede = s.id
                        LEFT JOIN tipo_equipo t ON e.id_tipo = t.id
                        WHERE e.id = ? AND e.activo = 1");
$stmt->execute([$id]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    header('Location: ../modulo_inventario/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo = trim($_POST['codigo_interno']);
    $nombre = trim($_POST['nombre']);
    $id_tipo = !empty($_POST['id_tipo']) ? $_POST['id_tipo'] : null;
    $id_categoria = !empty($_POST['id_categoria']) ? $_POST['id_categoria'] : null;
    $id_sede = !empty($_POST['id_sede']) ? $_POST['id_sede'] : null;
    $estado = $_POST['estado'];
    $stock = intval($_POST['stock']);
    $descripcion = !empty($_POST['descripcion']) ? trim($_POST['descripcion']) : null;
    $vr_comercial = !empty($_POST['vr_comercial']) ? floatval($_POST['vr_comercial']) : 0;
    $vida_util = !empty($_POST['vida_util']) ? intval($_POST['vida_util']) : 0;

    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
    $observacion = trim($_POST['observacion'] ?? '');

    $stmt = $conn->prepare("UPDATE equipos SET codigo_interno=?, nombre=?, id_tipo=?, id_categoria=?, id_sede=?, estado=?, stock=?, descripcion_articulo=?, fecha_ingreso=?, observacion=?, vr_comercial=?, vida_util=? WHERE id=?");
    $stmt->execute([$codigo, $nombre, $id_tipo, $id_categoria, $id_sede, $estado, $stock, $descripcion, $fecha_ingreso, $observacion, $vr_comercial, $vida_util, $id]);

    $_SESSION['mensaje'] = 'Equipo actualizado correctamente';
    header('Location: index.php');
    exit;
}

$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$tiposEquipo = $conn->query("SELECT id, nombre_tipo FROM tipo_equipo ORDER BY nombre_tipo")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Editar Equipo - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_inventario/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-edit"></i> Editar Equipo</h2>
        <p><?php echo htmlspecialchars($equipo['nombre']); ?></p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="glass-card" style="max-width:900px;margin:0 auto;padding:32px;">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Código Interno <span class="required">*</span></label>
                <input type="text" class="form-control" name="codigo_interno" value="<?php echo htmlspecialchars($equipo['codigo_interno']); ?>" required>
            </div>
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($equipo['nombre']); ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Tipo</label>
                <select class="form-control" name="id_tipo">
                    <option value="">Seleccionar</option>
                    <?php foreach($tiposEquipo as $tipo): ?>
                    <option value="<?php echo $tipo['id']; ?>" <?php echo $equipo['id_tipo'] == $tipo['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo['nombre_tipo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Categoría</label>
                <select class="form-control" name="id_categoria">
                    <option value="">Seleccionar</option>
                    <?php foreach($categorias as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $equipo['id_categoria'] == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Sede</label>
                <select class="form-control" name="id_sede">
                    <option value="">Seleccionar</option>
                    <?php foreach($sedes as $sede): ?>
                    <option value="<?php echo $sede['id']; ?>" <?php echo $equipo['id_sede'] == $sede['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sede['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select class="form-control" name="estado">
                    <option value="disponible" <?php echo $equipo['estado'] == 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="mantenimiento" <?php echo $equipo['estado'] == 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                    <option value="prestado" <?php echo $equipo['estado'] == 'prestado' ? 'selected' : ''; ?>>Prestado</option>
                    <option value="dañado" <?php echo $equipo['estado'] == 'dañado' ? 'selected' : ''; ?>>Dañado</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Stock <span class="required">*</span></label>
            <input type="number" class="form-control" name="stock" min="0" value="<?php echo $equipo['stock']; ?>" required>
        </div>

        <div class="form-group">
            <label>Descripción</label>
            <textarea class="form-control" name="descripcion" rows="3" placeholder="Características adicionales..."><?php echo htmlspecialchars($equipo['descripcion_articulo'] ?? ''); ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Fecha de Ingreso</label>
                <input type="date" class="form-control" name="fecha_ingreso" value="<?php echo $equipo['fecha_ingreso'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Observación</label>
                <textarea class="form-control" name="observacion" rows="2" placeholder="Observaciones adicionales"><?php echo htmlspecialchars($equipo['observacion'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-separator"><h4 style="color:var(--primary);font-size:14px;font-weight:600;margin:0;"><i class="fas fa-dollar-sign"></i> Información Comercial</h4></div>
        <div class="form-row">
            <div class="form-group">
                <label>VR Comercial (Valor de Reposición)</label>
                <input type="number" class="form-control" name="vr_comercial" min="0" step="0.01" value="<?php echo $equipo['vr_comercial'] ?? '0'; ?>" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Vida Útil (años)</label>
                <input type="number" class="form-control" name="vida_util" min="0" value="<?php echo $equipo['vida_util'] ?? '0'; ?>" placeholder="Años de vida útil">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Guardar Cambios</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>

<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$filtroTipo = trim((string)($_GET['tipo'] ?? ''));
$filtroEstado = trim((string)($_GET['estado'] ?? ''));

$sql = "SELECT n.*, ig.codigo_interno, ig.nombre AS elemento_nombre, ig.situacion,
               u.nombre AS usuario_nombre, t.id AS toma_id
        FROM novedades n
        LEFT JOIN inventario_general ig ON ig.id = n.elemento_id
        LEFT JOIN usuarios u ON u.id = n.usuario_id
        LEFT JOIN tomas_fisicas t ON t.id = n.toma_fisica_id
        WHERE 1=1";
$params = [];
if ($filtroTipo !== '') { $sql .= " AND n.tipo = ?"; $params[] = $filtroTipo; }
if ($filtroEstado !== '') { $sql .= " AND n.estado = ?"; $params[] = $filtroEstado; }
$sql .= " ORDER BY n.id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tiposNovedad = $conn->query("SELECT DISTINCT tipo FROM novedades ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
$token = generarTokenCSRF();

$pageTitle = 'Novedades - MIC';
require_once '../includes/head.php';
?>
</head>
<body>
<?php
$paginaActual = '../modulo_toma_fisica/novedades.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-sticky-note"></i> Novedades</h2>
        <p>Novedades registradas durante las tomas físicas e inspecciones</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('nov_nueva_modal').style.display='flex'"><i class="fas fa-plus"></i> Registrar novedad</button>
    </div>
</div>

<div class="glass-card" style="padding:16px 20px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:0;">
        <div class="form-group" style="margin:0;">
            <label>Tipo de novedad</label>
            <select class="form-control" name="tipo">
                <option value="">Todos los tipos</option>
                <?php foreach (array_merge(TIPOS_NOVEDAD, $tiposNovedad) as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filtroTipo === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Estado</label>
            <select class="form-control" name="estado">
                <option value="">Todos</option>
                <option value="abierta" <?php echo $filtroEstado === 'abierta' ? 'selected' : ''; ?>>Abierta</option>
                <option value="cerrada" <?php echo $filtroEstado === 'cerrada' ? 'selected' : ''; ?>>Cerrada</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrar</button>
    </form>
</div>

<div class="glass-card" style="padding:18px 22px;">
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Activo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Toma física</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th>Evidencias</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($novedades as $n): ?>
                <tr>
                    <td><strong><?php echo (int)$n['id']; ?></strong></td>
                    <td>
                        <strong style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($n['codigo_interno'] ?? ('#' . $n['elemento_id'])); ?></strong>
                        <br><small><?php echo htmlspecialchars($n['elemento_nombre'] ?? '—'); ?></small>
                    </td>
                    <td><span class="badge badge-warning"><?php echo htmlspecialchars($n['tipo']); ?></span></td>
                    <td style="max-width:320px;"><?php echo htmlspecialchars($n['descripcion']); ?></td>
                    <td>
                        <?php if ($n['toma_id']): ?>
                        <a href="ver_toma.php?id=<?php echo (int)$n['toma_id']; ?>" class="btn-icon" title="Ver toma física"><i class="fas fa-eye"></i> #<?php echo (int)$n['toma_id']; ?></a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></td>
                    <td><?php echo htmlspecialchars($n['usuario_nombre'] ?? '—'); ?></td>
                    <td><span class="badge <?php echo $n['estado'] === 'abierta' ? 'badge-warning' : 'badge-success'; ?>"><?php echo ucfirst($n['estado']); ?></span></td>
                    <td>
                        <?php $evs = evidenciasDeEntidad($conn, 'novedad', (int)$n['id']); ?>
                        <?php if ($evs): ?>
                            <?php foreach ($evs as $ev): ?>
                            <a href="../ver_archivo.php?ruta=<?php echo urlencode($ev['archivo']); ?>" target="_blank" title="<?php echo htmlspecialchars($ev['tipo_evidencia'] ?: 'Evidencia'); ?>"><i class="fas fa-file-image"></i></a>
                            <?php endforeach; ?>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$novedades): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--gray);">No hay novedades registradas</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ MODAL NUEVA NOVEDAD ============ -->
<div class="modal" id="nov_nueva_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:540px;">
        <div class="modal-header">
            <h3><i class="fas fa-sticky-note"></i> Registrar novedad</h3>
            <button class="modal-close" onclick="document.getElementById('nov_nueva_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Código del activo <span class="required">*</span></label>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-control" id="nov_buscar_codigo" placeholder="Escanee o digite el código (ej: 20J-02-S001-001)" style="font-family:monospace;">
                    <button type="button" class="btn btn-outline" onclick="buscarActivoNovedad()"><i class="fas fa-search"></i></button>
                </div>
                <input type="hidden" id="nov_elemento_id">
                <div id="nov_elemento_info" style="font-size:0.85rem;margin-top:6px;"></div>
            </div>
            <form id="formNovedadNueva">
                <div class="form-group">
                    <label>Tipo de novedad <span class="required">*</span></label>
                    <select class="form-control" name="tipo" required>
                        <?php foreach (TIPOS_NOVEDAD as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descripción <span class="required">*</span></label>
                    <textarea class="form-control" name="descripcion" rows="3" required placeholder="Describa la novedad..."></textarea>
                </div>
                <div class="form-group">
                    <label>Evidencias (JPG, PNG, WEBP, PDF — máx 8 MB c/u)</label>
                    <input type="file" class="form-control" name="evidencias[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-primary btn-block" onclick="guardarNovedadNueva()"><i class="fas fa-save"></i> Guardar novedad</button>
            </form>
        </div>
    </div>
</div>

<script>
var CSRF = <?php echo json_encode($token); ?>;
function buscarActivoNovedad() {
    var codigo = document.getElementById('nov_buscar_codigo').value.trim();
    if (!codigo) { alert('Digite un código'); return; }
    var fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fetch('acciones.php?accion=buscar_codigo&codigo=' + encodeURIComponent(codigo), { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('nov_elemento_id').value = res.elemento.id;
            document.getElementById('nov_elemento_info').innerHTML =
                '<strong>' + res.elemento.codigo_interno + '</strong> — ' + res.elemento.nombre +
                (res.elemento.sede_nombre ? ' (' + res.elemento.sede_nombre + ')' : '');
        })
        .catch(function() { alert('Error de conexión'); });
}
function guardarNovedadNueva() {
    var elementoId = document.getElementById('nov_elemento_id').value;
    if (!elementoId) { alert('Primero busque el activo por su código'); return; }
    var form = document.getElementById('formNovedadNueva');
    var fd = new FormData(form);
    fd.set('accion', 'registrar_novedad');
    fd.set('_csrf_token', CSRF);
    fd.set('elemento_id', elementoId);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('nov_nueva_modal').style.display = 'none';
            location.reload();
        })
        .catch(function() { alert('Error de conexión'); });
}
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$filtroEstado = trim((string)($_GET['estado'] ?? ''));

$sql = "SELECT m.*, ig.codigo_interno, ig.nombre AS elemento_nombre, ig.estado AS elem_estado,
               ig.situacion AS elem_situacion, u.nombre AS usuario_nombre
        FROM mantenimiento m
        LEFT JOIN inventario_general ig ON ig.id = m.elemento_id
        LEFT JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.elemento_id IS NOT NULL";
$params = [];
if ($filtroEstado !== '') { $sql .= " AND m.estado = ?"; $params[] = $filtroEstado; }
$sql .= " ORDER BY m.id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$mantenimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$token = generarTokenCSRF();

$pageTitle = 'Mantenimientos - MIC';
require_once '../includes/head.php';
?>
</head>
<body>
<?php
$paginaActual = '../modulo_toma_fisica/mantenimientos.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-tools"></i> Mantenimientos</h2>
        <p>Activos enviados a mantenimiento desde la inspección y su resultado</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('mto_nuevo_modal').style.display='flex'"><i class="fas fa-plus"></i> Enviar a mantenimiento</button>
    </div>
</div>

<div class="glass-card" style="padding:16px 20px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:0;">
        <div class="form-group" style="margin:0;">
            <label>Estado</label>
            <select class="form-control" name="estado">
                <option value="">Todos</option>
                <option value="programado" <?php echo $filtroEstado === 'programado' ? 'selected' : ''; ?>>Programado</option>
                <option value="en_proceso" <?php echo $filtroEstado === 'en_proceso' ? 'selected' : ''; ?>>En proceso</option>
                <option value="completado" <?php echo $filtroEstado === 'completado' ? 'selected' : ''; ?>>Completado</option>
                <option value="cancelado" <?php echo $filtroEstado === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
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
                    <th>Descripción</th>
                    <th>Proveedor / técnico</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Costo</th>
                    <th>Resultado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mantenimientos as $m): ?>
                <tr>
                    <td><strong><?php echo (int)$m['id']; ?></strong></td>
                    <td>
                        <strong style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($m['codigo_interno'] ?? ('#' . $m['elemento_id'])); ?></strong>
                        <br><small><?php echo htmlspecialchars($m['elemento_nombre'] ?? '—'); ?></small>
                    </td>
                    <td style="max-width:260px;"><?php echo htmlspecialchars($m['descripcion_trabajo']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($m['proveedor'] ?: '—'); ?>
                        <?php if ($m['tecnico']): ?><br><small><?php echo htmlspecialchars($m['tecnico']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($m['fecha_inicio'])); ?></td>
                    <td><?php echo $m['fecha_fin'] ? date('d/m/Y', strtotime($m['fecha_fin'])) : '—'; ?></td>
                    <td><?php echo $m['costo'] ? '$' . number_format((float)$m['costo'], 0) : '—'; ?></td>
                    <td>
                        <?php if ($m['resultado']): ?>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($m['resultado']); ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $m['estado'] === 'completado' ? 'badge-success' : ($m['estado'] === 'cancelado' ? 'badge-secondary' : ($m['estado'] === 'en_proceso' ? 'badge-info' : 'badge-warning')); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $m['estado'])); ?>
                        </span>
                        <br><small style="color:var(--gray);">Situación: <?php echo htmlspecialchars($m['elem_situacion'] ?? '—'); ?></small>
                    </td>
                    <td>
                        <?php $evs = evidenciasDeEntidad($conn, 'inspeccion', (int)$m['id']); ?>
                        <?php if ($evs): ?>
                            <?php foreach ($evs as $ev): ?>
                            <a href="../ver_archivo.php?ruta=<?php echo urlencode($ev['archivo']); ?>" target="_blank" title="Evidencia"><i class="fas fa-file-image"></i></a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (in_array($m['estado'], ['programado', 'en_proceso'], true)): ?>
                        <button class="btn-icon" onclick="abrirFinalizarMto(<?php echo (int)$m['id']; ?>)" title="Finalizar mantenimiento"><i class="fas fa-check-circle"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$mantenimientos): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--gray);">No hay mantenimientos registrados</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ MODAL NUEVO MANTENIMIENTO ============ -->
<div class="modal" id="mto_nuevo_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:540px;">
        <div class="modal-header">
            <h3><i class="fas fa-tools"></i> Enviar a mantenimiento</h3>
            <button class="modal-close" onclick="document.getElementById('mto_nuevo_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Código del activo <span class="required">*</span></label>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-control" id="mto_buscar_codigo" placeholder="Escanee o digite el código (ej: 20J-02-S001-001)" style="font-family:monospace;">
                    <button type="button" class="btn btn-outline" onclick="buscarActivoMto()"><i class="fas fa-search"></i></button>
                </div>
                <input type="hidden" id="mto_elemento_id">
                <div id="mto_elemento_info" style="font-size:0.85rem;margin-top:6px;"></div>
            </div>
            <form id="formMtoNuevo">
                <div class="form-group">
                    <label>Motivo / descripción del trabajo <span class="required">*</span></label>
                    <textarea class="form-control" name="descripcion" rows="3" required placeholder="Ej: Reemplazo de pantalla..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Proveedor</label>
                        <input type="text" class="form-control" name="proveedor" placeholder="Empresa que realiza el mantenimiento">
                    </div>
                    <div class="form-group">
                        <label>Técnico</label>
                        <input type="text" class="form-control" name="tecnico" placeholder="Nombre del técnico">
                    </div>
                </div>
                <div class="form-group">
                    <label>Costo estimado</label>
                    <input type="number" class="form-control" name="costo" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Evidencias</label>
                    <input type="file" class="form-control" name="evidencias[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-primary btn-block" onclick="guardarMtoNuevo()"><i class="fas fa-wrench"></i> Enviar a mantenimiento</button>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODAL FINALIZAR MANTENIMIENTO ============ -->
<div class="modal" id="mto_finalizar_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:540px;">
        <div class="modal-header">
            <h3><i class="fas fa-flag-checkered"></i> Finalizar mantenimiento</h3>
            <button class="modal-close" onclick="document.getElementById('mto_finalizar_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="mto_fin_id">
            <form id="formMtoFinalizar">
                <div class="form-group">
                    <label>Resultado del mantenimiento <span class="required">*</span></label>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <?php foreach (RESULTADOS_MANTENIMIENTO as $r): ?>
                        <label style="display:flex;align-items:center;gap:8px;font-size:0.88rem;cursor:pointer;">
                            <input type="radio" name="resultado" value="<?php echo htmlspecialchars($r); ?>"> <?php echo htmlspecialchars($r); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color:var(--gray);">Si el activo fue reparado, su estado pasará a "bueno" y quedará disponible.</small>
                </div>
                <div class="form-group">
                    <label>Costo final</label>
                    <input type="number" class="form-control" name="costo" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="2" placeholder="Observaciones del mantenimiento..."></textarea>
                </div>
                <div class="form-group">
                    <label>Evidencias del resultado</label>
                    <input type="file" class="form-control" name="evidencias[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-success btn-block" onclick="guardarMtoFinalizar()"><i class="fas fa-check-double"></i> Finalizar mantenimiento</button>
            </form>
        </div>
    </div>
</div>

<script>
var CSRF = <?php echo json_encode($token); ?>;
function buscarActivoMto() {
    var codigo = document.getElementById('mto_buscar_codigo').value.trim();
    if (!codigo) { alert('Digite un código'); return; }
    var fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fetch('acciones.php?accion=buscar_codigo&codigo=' + encodeURIComponent(codigo), { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('mto_elemento_id').value = res.elemento.id;
            document.getElementById('mto_elemento_info').innerHTML =
                '<strong>' + res.elemento.codigo_interno + '</strong> — ' + res.elemento.nombre +
                (res.elemento.sede_nombre ? ' (' + res.elemento.sede_nombre + ')' : '');
        })
        .catch(function() { alert('Error de conexión'); });
}
function guardarMtoNuevo() {
    var elementoId = document.getElementById('mto_elemento_id').value;
    if (!elementoId) { alert('Primero busque el activo por su código'); return; }
    var form = document.getElementById('formMtoNuevo');
    var fd = new FormData(form);
    fd.set('accion', 'enviar_mantenimiento');
    fd.set('_csrf_token', CSRF);
    fd.set('elemento_id', elementoId);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('mto_nuevo_modal').style.display = 'none';
            location.reload();
        })
        .catch(function() { alert('Error de conexión'); });
}
function abrirFinalizarMto(id) {
    document.getElementById('mto_fin_id').value = id;
    document.getElementById('mto_finalizar_modal').style.display = 'flex';
}
function guardarMtoFinalizar() {
    var id = document.getElementById('mto_fin_id').value;
    var form = document.getElementById('formMtoFinalizar');
    var fd = new FormData(form);
    fd.set('accion', 'finalizar_mantenimiento');
    fd.set('_csrf_token', CSRF);
    fd.set('mantenimiento_id', id);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('mto_finalizar_modal').style.display = 'none';
            location.reload();
        })
        .catch(function() { alert('Error de conexión'); });
}
</script>

<?php require_once '../includes/footer.php'; ?>

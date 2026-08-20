<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$filtroEstado = trim((string)($_GET['estado'] ?? ''));

$sql = "SELECT b.*, ig.codigo_interno, ig.nombre AS elemento_nombre, ig.situacion,
               u.nombre AS solicitante_nombre, a.nombre AS aprobador_nombre
        FROM bajas b
        LEFT JOIN inventario_general ig ON ig.id = b.elemento_id
        LEFT JOIN usuarios u ON u.id = b.usuario_solicita
        LEFT JOIN usuarios a ON a.id = b.aprobado_por
        WHERE 1=1";
$params = [];
if ($filtroEstado !== '') { $sql .= " AND b.estado = ?"; $params[] = $filtroEstado; }
$sql .= " ORDER BY b.id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$token = generarTokenCSRF();

$pageTitle = 'Bajas de Activos - MIC';
require_once '../includes/head.php';
?>
</head>
<body>
<?php
$paginaActual = '../modulo_toma_fisica/bajas.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-ban"></i> Bajas de activos</h2>
        <p>Solicitudes de baja. El activo nunca se elimina: conserva código, QR e historial.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('baja_nueva_modal').style.display='flex'"><i class="fas fa-plus"></i> Solicitar baja</button>
    </div>
</div>

<div class="glass-card" style="padding:16px 20px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:0;">
        <div class="form-group" style="margin:0;">
            <label>Estado</label>
            <select class="form-control" name="estado">
                <option value="">Todos</option>
                <option value="solicitada" <?php echo $filtroEstado === 'solicitada' ? 'selected' : ''; ?>>Solicitada</option>
                <option value="aprobada" <?php echo $filtroEstado === 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                <option value="rechazada" <?php echo $filtroEstado === 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
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
                    <th>Motivo</th>
                    <th>Descripción</th>
                    <th>Fecha</th>
                    <th>Solicitante</th>
                    <th>Estado</th>
                    <th>Documento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bajas as $b): ?>
                <tr>
                    <td><strong><?php echo (int)$b['id']; ?></strong></td>
                    <td>
                        <strong style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($b['codigo_interno'] ?? ('#' . $b['elemento_id'])); ?></strong>
                        <br><small><?php echo htmlspecialchars($b['elemento_nombre'] ?? '—'); ?></small>
                        <br><small style="color:var(--gray);">Situación: <?php echo htmlspecialchars($b['situacion'] ?? '—'); ?></small>
                    </td>
                    <td><span class="badge badge-danger"><?php echo htmlspecialchars($b['motivo']); ?></span></td>
                    <td style="max-width:240px;"><?php echo htmlspecialchars($b['descripcion'] ?: '—'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($b['fecha_baja'])); ?></td>
                    <td><?php echo htmlspecialchars($b['solicitante_nombre'] ?? '—'); ?></td>
                    <td>
                        <span class="badge <?php echo $b['estado'] === 'aprobada' ? 'badge-success' : ($b['estado'] === 'rechazada' ? 'badge-secondary' : 'badge-warning'); ?>">
                            <?php echo ucfirst($b['estado']); ?>
                        </span>
                        <?php if ($b['estado'] !== 'solicitada' && $b['aprobador_nombre']): ?>
                        <br><small style="color:var(--gray);">Por: <?php echo htmlspecialchars($b['aprobador_nombre']); ?></small>
                        <?php endif; ?>
                        <?php if ($b['observacion_aprobacion']): ?>
                        <br><small style="color:var(--gray);"><?php echo htmlspecialchars($b['observacion_aprobacion']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($b['documento_baja']): ?>
                        <a href="../ver_archivo.php?ruta=<?php echo urlencode($b['documento_baja']); ?>" target="_blank" class="btn-icon" title="Ver documento de baja"><i class="fas fa-file-pdf"></i></a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        <?php $evs = evidenciasDeEntidad($conn, 'baja', (int)$b['id']); ?>
                        <?php foreach ($evs as $ev): ?>
                        <a href="../ver_archivo.php?ruta=<?php echo urlencode($ev['archivo']); ?>" target="_blank" class="btn-icon" title="Evidencia"><i class="fas fa-file-image"></i></a>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($b['estado'] === 'solicitada'): ?>
                        <button class="btn-icon" style="color:#16a34a;" onclick="resolverBaja(<?php echo (int)$b['id']; ?>, 'aprobar')" title="Aprobar baja"><i class="fas fa-check"></i></button>
                        <button class="btn-icon" style="color:#dc2626;" onclick="resolverBaja(<?php echo (int)$b['id']; ?>, 'rechazar')" title="Rechazar baja"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$bajas): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--gray);">No hay solicitudes de baja</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ MODAL NUEVA BAJA ============ -->
<div class="modal" id="baja_nueva_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:540px;">
        <div class="modal-header">
            <h3><i class="fas fa-ban"></i> Solicitar baja de activo</h3>
            <button class="modal-close" onclick="document.getElementById('baja_nueva_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Código del activo <span class="required">*</span></label>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-control" id="baja_buscar_codigo" placeholder="Escanee o digite el código (ej: 20J-02-S001-001)" style="font-family:monospace;">
                    <button type="button" class="btn btn-outline" onclick="buscarActivoBaja()"><i class="fas fa-search"></i></button>
                </div>
                <input type="hidden" id="baja_elemento_id">
                <div id="baja_elemento_info" style="font-size:0.85rem;margin-top:6px;"></div>
            </div>
            <form id="formBajaNueva">
                <div class="form-group">
                    <label>Motivo de la baja <span class="required">*</span></label>
                    <select class="form-control" name="motivo" required>
                        <?php foreach (MOTIVOS_BAJA as $m): ?>
                        <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de la baja <span class="required">*</span></label>
                    <input type="date" class="form-control" name="fecha_baja" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="2" placeholder="Detalle de la situación del activo..."></textarea>
                </div>
                <div class="form-group">
                    <label>Documento de baja (PDF u otros, máx 8 MB)</label>
                    <input type="file" class="form-control" name="documento_baja" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <div class="form-group">
                    <label>Evidencias</label>
                    <input type="file" class="form-control" name="evidencias[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-primary btn-block" onclick="guardarBajaNueva()"><i class="fas fa-save"></i> Solicitar baja</button>
            </form>
        </div>
    </div>
</div>

<script>
var CSRF = <?php echo json_encode($token); ?>;
function buscarActivoBaja() {
    var codigo = document.getElementById('baja_buscar_codigo').value.trim();
    if (!codigo) { alert('Digite un código'); return; }
    var fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fetch('acciones.php?accion=buscar_codigo&codigo=' + encodeURIComponent(codigo), { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('baja_elemento_id').value = res.elemento.id;
            document.getElementById('baja_elemento_info').innerHTML =
                '<strong>' + res.elemento.codigo_interno + '</strong> — ' + res.elemento.nombre +
                (res.elemento.sede_nombre ? ' (' + res.elemento.sede_nombre + ')' : '') +
                ' · Situación: ' + res.elemento.situacion;
        })
        .catch(function() { alert('Error de conexión'); });
}
function guardarBajaNueva() {
    var elementoId = document.getElementById('baja_elemento_id').value;
    if (!elementoId) { alert('Primero busque el activo por su código'); return; }
    var form = document.getElementById('formBajaNueva');
    var fd = new FormData(form);
    fd.set('accion', 'solicitar_baja');
    fd.set('_csrf_token', CSRF);
    fd.set('elemento_id', elementoId);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('baja_nueva_modal').style.display = 'none';
            location.reload();
        })
        .catch(function() { alert('Error de conexión'); });
}
function resolverBaja(id, accion) {
    var observacion = prompt(accion === 'aprobar' ? 'Observación (opcional):' : 'Motivo del rechazo (opcional):');
    if (observacion === null) return;
    var fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fd.append('accion', accion === 'aprobar' ? 'aprobar_baja' : 'rechazar_baja');
    fd.append('baja_id', id);
    if (observacion !== '') fd.append('observacion', observacion);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            location.reload();
        })
        .catch(function() { alert('Error de conexión'); });
}
</script>

<?php require_once '../includes/footer.php'; ?>

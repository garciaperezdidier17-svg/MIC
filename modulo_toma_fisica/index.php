<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$catalogosUbicaciones = require __DIR__ . '/../config/ubicaciones.php';
$usuarioId = (int)$_SESSION['user_id'];
$token = generarTokenCSRF();

$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$esperadosPorSede = [];
foreach ($sedes as $s) {
    $stmt = $conn->prepare("SELECT ubicacion, COUNT(*) AS total FROM inventario_general WHERE activo=1 AND situacion <> 'dado_de_baja' AND id_sede=? AND ubicacion IS NOT NULL AND ubicacion <> '' GROUP BY ubicacion ORDER BY ubicacion");
    $stmt->execute([(int)$s['id']]);
    $esperadosPorSede[(int)$s['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$tomaActiva = obtenerTomaActiva($conn, $usuarioId);
$detalles = [];
if ($tomaActiva) {
    $detalles = obtenerDetallesToma($conn, (int)$tomaActiva['id']);
}
$tomaId = $tomaActiva ? (int)$tomaActiva['id'] : 0;

$estados = estadosDisponibles($conn);
$estadosNombres = array_column($estados, 'nombre');

$tomasRecientes = $conn->query(
    "SELECT t.*, s.nombre AS sede_nombre, u.nombre AS usuario_nombre
     FROM tomas_fisicas t
     LEFT JOIN sedes s ON t.sede_id=s.id
     LEFT JOIN usuarios u ON t.usuario_id=u.id
     ORDER BY t.id DESC LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$pageTitle = 'Toma Física - MIC';
require_once '../includes/head.php';
?>
</head>
<body>
<?php
$paginaActual = '../modulo_toma_fisica/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-clipboard-check"></i> Toma Física e Inspección de Activos</h2>
        <p>Comprueba físicamente que los activos existen y coinciden con la información registrada</p>
    </div>
    <div class="page-actions">
        <a href="ver_toma.php" class="btn btn-outline btn-sm"><i class="fas fa-history"></i> Historial de tomas</a>
        <a href="novedades.php" class="btn btn-outline btn-sm"><i class="fas fa-sticky-note"></i> Novedades</a>
        <a href="mantenimientos.php" class="btn btn-outline btn-sm"><i class="fas fa-tools"></i> Mantenimientos</a>
        <a href="bajas.php" class="btn btn-outline btn-sm"><i class="fas fa-ban"></i> Bajas</a>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if (!$tomaActiva): ?>

<!-- ============ INICIO DE TOMA FÍSICA ============ -->
<div class="glass-card" style="max-width:640px;margin:0 auto;padding:24px 28px;">
    <h4 style="margin:0 0 4px;font-weight:600;"><i class="fas fa-map-marked-alt"></i> Iniciar nueva toma física</h4>
    <p style="font-size:0.82rem;color:var(--gray);margin:0 0 18px;">Selecciona la sede y la ubicación. El sistema listará los activos que deberían encontrarse allí.</p>
    <form method="POST" action="acciones.php?accion=iniciar_toma" id="formIniciarToma" enctype="multipart/form-data">
        <?php echo campoCSRF(); ?>
        <div class="form-group">
            <label>Sede <span class="required">*</span></label>
            <select class="form-control" name="sede_id" id="tf_sede_id" onchange="cargarUbicacionesToma()">
                <option value="">Seleccione la sede</option>
                <?php foreach ($sedes as $s): ?>
                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Ubicación <span class="required">*</span></label>
            <select class="form-control" name="ubicacion" id="tf_ubicacion" disabled>
                <option value="">Primero seleccione la sede</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-play"></i> Iniciar toma física</button>
    </form>
</div>

<script>
var esperadosPorSede = <?php echo json_encode($esperadosPorSede, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
function cargarUbicacionesToma() {
    var sedeId = document.getElementById('tf_sede_id').value;
    var sel = document.getElementById('tf_ubicacion');
    sel.innerHTML = '';
    if (!sedeId) { sel.disabled = true; sel.innerHTML = '<option value="">Primero seleccione la sede</option>'; return; }
    var items = esperadosPorSede[sedeId] || [];
    if (!items.length) {
        sel.disabled = true;
        sel.innerHTML = '<option value="">No hay activos en esta sede</option>';
        return;
    }
    items.forEach(function(u) {
        var opt = document.createElement('option');
        opt.value = u.ubicacion;
        opt.textContent = u.ubicacion + ' (' + u.total + ' activos)';
        sel.appendChild(opt);
    });
    sel.disabled = false;
}
</script>

<?php else: ?>

<!-- ============ TOMA FÍSICA EN PROGRESO ============ -->
<?php
$encontrados = 0; $noEncontrados = 0; $pendientes = 0;
foreach ($detalles as $d) {
    if ((int)$d['encontrado'] === 1) $encontrados++;
    elseif ($d['verificada_en'] !== null) $noEncontrados++;
    else $pendientes++;
}
?>
<div class="glass-card" style="padding:20px 24px;margin-bottom:18px;">
    <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:center;">
        <div>
            <h4 style="margin:0 0 4px;font-weight:700;"><i class="fas fa-clipboard-check" style="color:#3b82f6;"></i> Toma física #<?php echo $tomaId; ?> — <?php echo htmlspecialchars($tomaActiva['sede_nombre'] ?? ''); ?></h4>
            <p style="margin:0;font-size:0.85rem;color:var(--gray);">Ubicación: <strong><?php echo htmlspecialchars($tomaActiva['ubicacion']); ?></strong> · Iniciada: <?php echo date('d/m/Y H:i', strtotime($tomaActiva['fecha_toma'])); ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <span class="badge badge-info">Esperados: <?php echo (int)$tomaActiva['total_esperados']; ?></span>
            <span class="badge badge-success">Encontrados: <?php echo $encontrados; ?></span>
            <span class="badge badge-danger">No encontrados: <?php echo $noEncontrados; ?></span>
            <span class="badge badge-secondary">Pendientes: <?php echo $pendientes; ?></span>
        </div>
    </div>
</div>

<div class="glass-card" style="padding:20px 24px;margin-bottom:18px;">
    <h4 style="margin:0 0 10px;font-weight:600;"><i class="fas fa-qrcode"></i> Escaneo QR</h4>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="text" id="tf_scan_input" class="form-control" style="flex:1;min-width:260px;font-family:monospace;" placeholder="Escanee o digite el código del activo (ej: 20J-02-S001-001)">
        <button type="button" class="btn btn-primary" onclick="escanearCodigo()"><i class="fas fa-search"></i> Buscar</button>
        <button type="button" class="btn btn-outline" id="btn_camara" onclick="iniciarCamara()" style="display:none;"><i class="fas fa-camera"></i> Cámara</button>
        <button type="button" class="btn btn-outline" id="btn_parar_camara" onclick="pararCamara()" style="display:none;"><i class="fas fa-stop"></i> Detener cámara</button>
    </div>
    <div id="tf_camara_container" style="display:none;margin-top:12px;max-width:420px;"></div>
    <div id="tf_scan_result" style="margin-top:12px;"></div>
</div>

<div class="glass-card" style="padding:18px 22px;">
    <div class="card-header" style="margin-bottom:10px;">
        <h3><i class="fas fa-list-check"></i> Activos esperados en <?php echo htmlspecialchars($tomaActiva['ubicacion']); ?></h3>
    </div>
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Elemento</th>
                    <th>Responsable</th>
                    <th>Estado registrado</th>
                    <th>Verificación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $i => $d): ?>
                <tr id="fila_detalle_<?php echo (int)$d['id']; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td><strong style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($d['codigo_interno'] ?? ('#' . $d['elemento_id'])); ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($d['elemento_nombre']); ?></strong>
                        <?php if ($d['marca']): ?><br><small style="color:var(--gray);"><?php echo htmlspecialchars($d['marca']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(trim(($d['prof_nombre'] ?? '') . ' ' . ($d['prof_apellido'] ?? ''))) ?: '<span class="text-muted">—</span>'; ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($d['estado_registrado'] ?: '—'); ?></span></td>
                    <td>
                        <?php if ((int)$d['encontrado'] === 1): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Encontrado</span>
                            <?php if ($d['estado_encontrado']): ?><br><small style="color:var(--gray);">Estado: <?php echo htmlspecialchars($d['estado_encontrado']); ?></small><?php endif; ?>
                        <?php elseif ($d['verificada_en'] !== null): ?>
                            <span class="badge badge-danger"><i class="fas fa-times"></i> No encontrado</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons" style="gap:2px;">
                            <button class="btn-icon" onclick="abrirVerificacion(<?php echo (int)$d['id']; ?>)" title="Verificar"><i class="fas fa-clipboard-check"></i></button>
                            <button class="btn-icon" onclick="abrirNoEncontrado(<?php echo (int)$d['id']; ?>)" title="Marcar no encontrado"><i class="fas fa-eye-slash"></i></button>
                            <button class="btn-icon" onclick="abrirNovedad(<?php echo (int)$d['elemento_id']; ?>)" title="Registrar novedad"><i class="fas fa-sticky-note"></i></button>
                            <button class="btn-icon" onclick="abrirMantenimiento(<?php echo (int)$d['elemento_id']; ?>)" title="Enviar a mantenimiento"><i class="fas fa-tools"></i></button>
                            <button class="btn-icon" onclick="abrirSituacion(<?php echo (int)$d['elemento_id']; ?>)" title="Cambiar situación"><i class="fas fa-arrows-alt-h"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
        <button class="btn btn-success" onclick="abrirFinalizar()"><i class="fas fa-flag-checkered"></i> Finalizar toma física</button>
        <button class="btn btn-outline" onclick="cancelarToma()"><i class="fas fa-times"></i> Cancelar toma</button>
    </div>
</div>

<script>
var CSRF = <?php echo json_encode($token); ?>;
var tomaId = <?php echo $tomaId; ?>;
var estadoRegistradoPorDetalle = <?php echo json_encode(array_column($detalles, 'estado_registrado', 'id'), JSON_UNESCAPED_UNICODE); ?>;
var codigoPorDetalle = <?php echo json_encode(array_column($detalles, 'codigo_interno', 'id'), JSON_UNESCAPED_UNICODE); ?>;
var elementoPorDetalle = <?php echo json_encode(array_column($detalles, 'elemento_id', 'id')); ?>;
var detallePorCodigo = {};
<?php foreach ($detalles as $d): ?>
detallePorCodigo[<?php echo json_encode($d['codigo_interno'] ?: ('#' . $d['elemento_id'])); ?>] = <?php echo (int)$d['id']; ?>;
<?php endforeach; ?>
var estadosDisponibles = <?php echo json_encode($estadosNombres, JSON_UNESCAPED_UNICODE); ?>;

function postForm(url, form) {
    return fetch(url, { method: 'POST', body: new FormData(form) })
        .then(function(r) { return r.json(); });
}

function mostrarError(msg) {
    var el = document.getElementById('tf_scan_result');
    el.innerHTML = '<div class="alert alert-danger" style="margin:0;"><i class="fas fa-exclamation-circle"></i> ' + msg + '</div>';
}

/* ---------- Escaneo QR ---------- */
function escanearCodigo() {
    var codigo = document.getElementById('tf_scan_input').value.trim();
    if (!codigo) { mostrarError('Ingrese o escanee un código'); return; }
    var fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fetch('acciones.php?accion=buscar_codigo&codigo=' + encodeURIComponent(codigo), { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { mostrarError(res.error); return; }
            var el = res.elemento;
            var detalleId = detallePorCodigo[el.codigo_interno] || null;
            if (!detalleId) {
                mostrarError('El activo <strong>' + el.codigo_interno + '</strong> no está en la lista esperada de esta ubicación.');
                return;
            }
            document.getElementById('tf_scan_input').value = '';
            abrirVerificacion(detalleId, el);
        })
        .catch(function() { mostrarError('Error de conexión al verificar el código'); });
}

/* ---------- Cámara (html5-qrcode por CDN, con respaldo manual) ---------- */
var camaraActiva = null;
function iniciarCamara() {
    if (!window.Html5Qrcode) {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
        s.onload = function() { arrancarCamara(); };
        s.onerror = function() { alert('No se pudo cargar el lector de cámara. Use el campo de código manual.'); };
        document.head.appendChild(s);
        return;
    }
    arrancarCamara();
}
function arrancarCamara() {
    var cont = document.getElementById('tf_camara_container');
    cont.style.display = 'block';
    document.getElementById('btn_parar_camara').style.display = 'inline-flex';
    document.getElementById('btn_camara').style.display = 'none';
    camaraActiva = new Html5Qrcode('tf_camara_container');
    camaraActiva.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 220, height: 220 } },
        function(texto) {
            document.getElementById('tf_scan_input').value = texto;
            pararCamara();
            escanearCodigo();
        },
        function() {}
    ).catch(function(e) {
        alert('No se pudo acceder a la cámara: ' + e);
        document.getElementById('btn_parar_camara').style.display = 'none';
        document.getElementById('btn_camara').style.display = 'inline-flex';
    });
}
function pararCamara() {
    if (camaraActiva) {
        camaraActiva.stop().then(function() { camaraActiva.clear(); }).catch(function() {});
        camaraActiva = null;
    }
    document.getElementById('tf_camara_container').style.display = 'none';
    document.getElementById('btn_parar_camara').style.display = 'none';
    document.getElementById('btn_camara').style.display = 'inline-flex';
}
window.addEventListener('load', function() {
    if (window.Html5Qrcode) document.getElementById('btn_camara').style.display = 'inline-flex';
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
    s.onload = function() { document.getElementById('btn_camara').style.display = 'inline-flex'; };
    document.head.appendChild(s);
});

/* ---------- Verificación ---------- */
function abrirVerificacion(detalleId, elemento) {
    document.getElementById('verificar_detalle_id').value = detalleId;
    var codigo = codigoPorDetalle[detalleId] || '';
    document.getElementById('verificar_codigo').textContent = codigo;
    var reg = estadoRegistradoPorDetalle[detalleId] || '—';
    document.getElementById('verificar_estado_registrado').textContent = reg;

    var coincideCodigo = true;
    var coincideSede = true;
    var coincideUbicacion = true;
    var coincideResponsable = true;
    if (elemento) {
        coincideCodigo = true;
        document.getElementById('verificar_nombre').textContent = (elemento.nombre || '') + (elemento.marca ? ' (' + elemento.marca + ')' : '');
    } else {
        document.getElementById('verificar_nombre').textContent = codigo;
    }
    document.getElementById('v_coincide_codigo').checked = coincideCodigo;
    document.getElementById('v_coincide_sede').checked = coincideSede;
    document.getElementById('v_coincide_ubicacion').checked = coincideUbicacion;
    document.getElementById('v_coincide_responsable').checked = coincideResponsable;

    var aviso = document.getElementById('verificar_aviso_estado');
    aviso.style.display = 'none';
    document.getElementById('v_cambiar_estado').checked = false;

    var radios = document.querySelectorAll('input[name="verificar_estado"]');
    var opciones = ['bueno','regular','dañado','fuera de servicio'];
    radios.forEach(function(r) {
        var en = opciones.indexOf(r.value) >= 0 ? r.value : r.value;
        r.checked = (en === reg);
        r.disabled = false;
    });

    document.getElementById('verificar_observacion').value = '';
    document.getElementById('verificar_evidencias').value = '';
    document.getElementById('verificar_situacion').value = '';
    document.getElementById('verificar_modal').style.display = 'flex';
}

function estadoSeleccionado() {
    var radios = document.querySelectorAll('input[name="verificar_estado"]');
    for (var i = 0; i < radios.length; i++) { if (radios[i].checked) return radios[i].value; }
    return '';
}
document.addEventListener('change', function(e) {
    if (e.target && e.target.name === 'verificar_estado') {
        var detalleId = document.getElementById('verificar_detalle_id').value;
        var reg = estadoRegistradoPorDetalle[detalleId] || '';
        var nuevo = estadoSeleccionado();
        var aviso = document.getElementById('verificar_aviso_estado');
        if (nuevo && nuevo !== reg) {
            aviso.style.display = 'block';
            document.getElementById('verificar_aviso_estado').textContent =
                'El estado físico encontrado (' + nuevo + ') es diferente al estado registrado (' + reg + '). Confirme el cambio para actualizar el activo.';
            document.getElementById('v_cambiar_estado').checked = true;
        } else {
            aviso.style.display = 'none';
            document.getElementById('v_cambiar_estado').checked = false;
        }
    }
});

function enviarVerificacion(encontrado) {
    var detalleId = document.getElementById('verificar_detalle_id').value;
    if (!detalleId) return;
    var form = document.getElementById('formVerificar');
    var fd = new FormData(form);
    fd.set('accion', 'verificar');
    fd.set('encontrado', encontrado ? '1' : '0');
    fd.set('_csrf_token', CSRF);
    if (!encontrado) {
        fd.delete('estado_encontrado');
        fd.set('estado_encontrado', '');
    }
    var btn = document.getElementById('btn_enviar_verificacion');
    btn.disabled = true;
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); btn.disabled = false; return; }
            document.getElementById('verificar_modal').style.display = 'none';
            location.reload();
        })
        .catch(function() { alert('Error de conexión'); btn.disabled = false; });
}

function abrirNoEncontrado(detalleId) {
    document.getElementById('verificar_detalle_id').value = detalleId;
    document.getElementById('verificar_codigo').textContent = codigoPorDetalle[detalleId] || '';
    document.getElementById('verificar_nombre').textContent = codigoPorDetalle[detalleId] || '';
    document.getElementById('verificar_estado_registrado').textContent = estadoRegistradoPorDetalle[detalleId] || '—';
    document.getElementById('verificar_observacion').value = '';
    document.getElementById('verificar_evidencias').value = '';
    document.getElementById('verificar_situacion').value = '';
    document.getElementById('verificar_aviso_estado').style.display = 'none';
    document.getElementById('verificar_modal').style.display = 'flex';
    document.querySelectorAll('input[name="verificar_estado"]').forEach(function(r) { r.checked = false; });
}

/* ---------- Finalizar / cancelar ---------- */
function abrirFinalizar() {
    document.getElementById('finalizar_modal').style.display = 'flex';
}
function enviarFinalizar() {
    var fd = new FormData();
    fd.append('accion', 'finalizar_toma');
    fd.append('_csrf_token', CSRF);
    fd.append('toma_id', tomaId);
    fd.append('observaciones', document.getElementById('finalizar_observaciones').value);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            window.location.href = 'ver_toma.php?id=' + res.toma_id;
        })
        .catch(function() { alert('Error de conexión'); });
}
function cancelarToma() {
    if (!confirm('¿Cancelar esta toma física? Los cambios de verificación ya registrados se conservarán.')) return;
    var fd = new FormData();
    fd.append('accion', 'cancelar_toma');
    fd.append('_csrf_token', CSRF);
    fd.append('toma_id', tomaId);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) { if (res.ok) location.reload(); else alert(res.error); });
}

/* ---------- Novedad / mantenimiento / situación ---------- */
function abrirNovedad(elementoId) {
    document.getElementById('nov_elemento_id').value = elementoId;
    document.getElementById('nov_modal').style.display = 'flex';
}
function enviarNovedad() {
    var form = document.getElementById('formNovedad');
    var fd = new FormData(form);
    fd.set('accion', 'registrar_novedad');
    fd.set('_csrf_token', CSRF);
    fd.set('toma_fisica_id', tomaId);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('nov_modal').style.display = 'none';
            location.reload();
        });
}
function abrirMantenimiento(elementoId) {
    document.getElementById('mto_elemento_id').value = elementoId;
    document.getElementById('mto_modal').style.display = 'flex';
}
function enviarMantenimiento() {
    var form = document.getElementById('formMantenimiento');
    var fd = new FormData(form);
    fd.set('accion', 'enviar_mantenimiento');
    fd.set('_csrf_token', CSRF);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('mto_modal').style.display = 'none';
            location.reload();
        });
}
function abrirSituacion(elementoId) {
    document.getElementById('sit_elemento_id').value = elementoId;
    document.getElementById('sit_modal').style.display = 'flex';
}
function enviarSituacion() {
    var form = document.getElementById('formSituacion');
    var fd = new FormData(form);
    fd.set('accion', 'cambiar_situacion');
    fd.set('_csrf_token', CSRF);
    fetch('acciones.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.error); return; }
            document.getElementById('sit_modal').style.display = 'none';
            location.reload();
        });
}
</script>

<!-- ============ MODAL VERIFICACIÓN ============ -->
<div class="modal" id="verificar_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:620px;">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> Verificación física</h3>
            <button class="modal-close" onclick="document.getElementById('verificar_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formVerificar">
                <input type="hidden" name="detalle_id" id="verificar_detalle_id">
                <input type="hidden" name="accion" value="verificar">
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                    <div><strong style="font-family:monospace;" id="verificar_codigo"></strong><br><span id="verificar_nombre" style="font-size:0.85rem;"></span></div>
                    <div style="text-align:right;"><small style="color:var(--gray);">Estado registrado:</small><br><strong id="verificar_estado_registrado"></strong></div>
                </div>
                <div style="background:rgba(34,197,94,0.08);border-radius:10px;padding:12px 14px;margin-bottom:12px;">
                    <label style="display:block;margin-bottom:6px;font-weight:600;"><i class="fas fa-list-check"></i> Verificación</label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.88rem;margin-bottom:4px;cursor:pointer;"><input type="checkbox" id="v_coincide_codigo" name="coincide_codigo" value="1" checked> Código coincide</label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.88rem;margin-bottom:4px;cursor:pointer;"><input type="checkbox" id="v_coincide_sede" name="coincide_sede" value="1" checked> Sede coincide</label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.88rem;margin-bottom:4px;cursor:pointer;"><input type="checkbox" id="v_coincide_ubicacion" name="coincide_ubicacion" value="1" checked> Ubicación coincide</label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.88rem;cursor:pointer;"><input type="checkbox" id="v_coincide_responsable" name="coincide_responsable" value="1" checked> Responsable coincide</label>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;display:block;margin-bottom:6px;">Estado encontrado</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach (['bueno', 'regular', 'dañado', 'fuera de servicio'] as $est): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:0.88rem;cursor:pointer;"><input type="radio" name="verificar_estado" value="<?php echo $est; ?>"> <?php echo ucfirst($est); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="verificar_aviso_estado" class="alert alert-warning" style="display:none;margin-bottom:12px;"></div>
                <input type="hidden" name="cambiar_estado" id="v_cambiar_estado" value="">
                <div class="form-group">
                    <label>Situación después de la inspección</label>
                    <select class="form-control" name="situacion_despues" id="verificar_situacion">
                        <option value="">Mantener la actual</option>
                        <option value="disponible">Disponible</option>
                        <option value="asignado">Asignado</option>
                        <option value="en_mantenimiento">En mantenimiento</option>
                        <option value="en_reparacion">En reparación</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Observación</label>
                    <textarea class="form-control" name="observacion" id="verificar_observacion" rows="2" placeholder="Ej: El computador presenta una grieta en la pantalla."></textarea>
                </div>
                <div class="form-group">
                    <label>Evidencias fotográficas (JPG, PNG, WEBP, PDF — máx 8 MB c/u)</label>
                    <input type="file" class="form-control" name="evidencias[]" id="verificar_evidencias" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-success btn-block" id="btn_enviar_verificacion" onclick="enviarVerificacion(true)"><i class="fas fa-check"></i> Confirmar verificación</button>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODAL FINALIZAR ============ -->
<div class="modal" id="finalizar_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:520px;">
        <div class="modal-header">
            <h3><i class="fas fa-flag-checkered"></i> Finalizar toma física</h3>
            <button class="modal-close" onclick="document.getElementById('finalizar_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.85rem;color:var(--gray);margin-top:0;">Los activos pendientes se contarán como no encontrados. Se generará el resumen final.</p>
            <div class="form-group">
                <label>Observaciones generales</label>
                <textarea class="form-control" id="finalizar_observaciones" rows="3" placeholder="Observaciones de la toma física..."></textarea>
            </div>
            <button type="button" class="btn btn-success btn-block" onclick="enviarFinalizar()"><i class="fas fa-check-double"></i> Finalizar y ver resumen</button>
        </div>
    </div>
</div>

<!-- ============ MODAL NOVEDAD ============ -->
<div class="modal" id="nov_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:520px;">
        <div class="modal-header">
            <h3><i class="fas fa-sticky-note"></i> Registrar novedad</h3>
            <button class="modal-close" onclick="document.getElementById('nov_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formNovedad">
                <input type="hidden" name="elemento_id" id="nov_elemento_id">
                <div class="form-group">
                    <label>Tipo de novedad <span class="required">*</span></label>
                    <select class="form-control" name="tipo" required>
                        <?php foreach (TIPOS_NOVEDAD as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descripción <span class="required">*</span></label>
                    <textarea class="form-control" name="descripcion" rows="3" required placeholder="Describa la novedad..."></textarea>
                </div>
                <div class="form-group">
                    <label>Evidencias</label>
                    <input type="file" class="form-control" name="evidencias[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-primary btn-block" onclick="enviarNovedad()"><i class="fas fa-save"></i> Guardar novedad</button>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODAL MANTENIMIENTO ============ -->
<div class="modal" id="mto_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:520px;">
        <div class="modal-header">
            <h3><i class="fas fa-tools"></i> Enviar a mantenimiento</h3>
            <button class="modal-close" onclick="document.getElementById('mto_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formMantenimiento">
                <input type="hidden" name="elemento_id" id="mto_elemento_id">
                <div class="form-group">
                    <label>Motivo / descripción del trabajo <span class="required">*</span></label>
                    <textarea class="form-control" name="descripcion" rows="3" required placeholder="Ej: Reemplazo de pantalla..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Proveedor / técnico</label>
                        <input type="text" class="form-control" name="proveedor" placeholder="Empresa que realiza el mantenimiento">
                    </div>
                    <div class="form-group">
                        <label>Técnico</label>
                        <input type="text" class="form-control" name="tecnico" placeholder="Nombre del técnico">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Costo estimado</label>
                        <input type="number" class="form-control" name="costo" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Evidencias</label>
                    <input type="file" class="form-control" name="evidencias[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <button type="button" class="btn btn-primary btn-block" onclick="enviarMantenimiento()"><i class="fas fa-wrench"></i> Enviar a mantenimiento</button>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODAL SITUACIÓN ============ -->
<div class="modal" id="sit_modal" style="display:none;">
    <div class="modal-content glass-card" style="max-width:520px;">
        <div class="modal-header">
            <h3><i class="fas fa-arrows-alt-h"></i> Cambiar situación del activo</h3>
            <button class="modal-close" onclick="document.getElementById('sit_modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formSituacion">
                <input type="hidden" name="elemento_id" id="sit_elemento_id">
                <div class="form-group">
                    <label>Situación <span class="required">*</span></label>
                    <select class="form-control" name="situacion" required>
                        <option value="no_encontrado">No encontrado</option>
                        <option value="en_investigacion">En investigación</option>
                        <option value="disponible">Disponible</option>
                        <option value="asignado">Asignado</option>
                        <option value="en_mantenimiento">En mantenimiento</option>
                        <option value="en_reparacion">En reparación</option>
                        <option value="dado_de_baja">Dado de baja</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Observación</label>
                    <textarea class="form-control" name="observacion" rows="2" placeholder="Motivo del cambio..."></textarea>
                </div>
                <button type="button" class="btn btn-primary btn-block" onclick="enviarSituacion()"><i class="fas fa-save"></i> Guardar</button>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ============ HISTORIAL DE TOMAS FÍSICAS ============ -->
<div class="glass-card" style="margin-top:24px;padding:18px 22px;">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Historial de tomas físicas</h3>
        <a href="ver_toma.php" class="btn btn-outline btn-sm">Ver todas</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Sede</th>
                    <th>Ubicación</th>
                    <th>Usuario</th>
                    <th>Esperados</th>
                    <th>Encontrados</th>
                    <th>Novedades</th>
                    <th>No encontrados</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tomasRecientes as $t): ?>
                <tr>
                    <td><strong><?php echo (int)$t['id']; ?></strong></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($t['fecha_toma'])); ?></td>
                    <td><?php echo htmlspecialchars($t['sede_nombre'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($t['ubicacion']); ?></td>
                    <td><?php echo htmlspecialchars($t['usuario_nombre'] ?? '—'); ?></td>
                    <td><?php echo (int)$t['total_esperados']; ?></td>
                    <td><?php echo (int)$t['encontrados']; ?></td>
                    <td><?php echo (int)$t['con_novedades']; ?></td>
                    <td><?php echo (int)$t['no_encontrados']; ?></td>
                    <td>
                        <span class="badge <?php echo $t['estado'] === 'finalizada' ? 'badge-success' : ($t['estado'] === 'en_progreso' ? 'badge-warning' : 'badge-secondary'); ?>">
                            <?php echo $t['estado'] === 'en_progreso' ? 'En progreso' : ($t['estado'] === 'finalizada' ? 'Finalizada' : 'Cancelada'); ?>
                        </span>
                    </td>
                    <td><a href="ver_toma.php?id=<?php echo (int)$t['id']; ?>" class="btn-icon" title="Ver detalle"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tomasRecientes): ?>
                <tr><td colspan="11" style="text-align:center;color:var(--gray);">No hay tomas físicas registradas</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

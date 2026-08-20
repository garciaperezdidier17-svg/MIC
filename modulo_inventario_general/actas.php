<?php
require_once '../config/conexion.php';
require_once '../vendor/autoload.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

use Mpdf\Mpdf;

require_once __DIR__ . '/helpers_actas.php';
require_once __DIR__ . '/helpers_historial.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

$catalogosUbicaciones = require __DIR__ . '/../config/ubicaciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar_acta'])) {
    verificarCSRF();
    $responsable_id = (int)($_POST['responsable_id'] ?? 0);
    $sede_id = (int)($_POST['sede_id'] ?? 0);
    $elementos_ids = array_values(array_filter(array_map('intval', $_POST['elementos'] ?? [])));

    if (!$responsable_id || !$sede_id || count($elementos_ids) === 0) {
        $_SESSION['mensaje'] = 'Debe seleccionar sede, responsable y al menos un elemento';
        header('Location: actas.php');
        exit;
    }

    $profStmt = $conn->prepare("SELECT * FROM profesores WHERE id=? AND estado='Activo'");
    $profStmt->execute([$responsable_id]);
    $profesor = $profStmt->fetch(PDO::FETCH_ASSOC);
    if (!$profesor || (int)$profesor['sede_id'] !== $sede_id) {
        $_SESSION['mensaje'] = 'El responsable no pertenece a la sede seleccionada';
        header('Location: actas.php');
        exit;
    }

    $sedeNombre = $conn->query("SELECT nombre FROM sedes WHERE id=$sede_id")->fetchColumn();
    if (!$sedeNombre) {
        $_SESSION['mensaje'] = 'La sede no existe';
        header('Location: actas.php');
        exit;
    }

    $in = implode(',', array_fill(0, count($elementos_ids), '?'));
    $elStmt = $conn->prepare("SELECT ig.*, s.nombre as sede_nombre, prov.nombre as proveedor_nombre, prov.nit as proveedor_nit FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id LEFT JOIN proveedores prov ON ig.proveedor_id=prov.id WHERE ig.activo=1 AND ig.profesor_id=? AND ig.id IN ($in)");
    $elStmt->execute(array_merge([$responsable_id], $elementos_ids));
    $elementos = $elStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($elementos) !== count(array_unique($elementos_ids))) {
        $_SESSION['mensaje'] = 'Algunos elementos no pertenecen al responsable o sede seleccionada';
        header('Location: actas.php');
        exit;
    }

    $ubicaciones = [];
    foreach ($elementos as $el) {
        if ((int)$el['id_sede'] !== $sede_id) {
            $_SESSION['mensaje'] = 'El elemento ' . ($el['codigo_interno'] ?? $el['id']) . ' pertenece a otra sede';
            header('Location: actas.php');
            exit;
        }
        if (!ubicacionPerteneceSedeActa($catalogosUbicaciones, $sedeNombre, $el['ubicacion'])) {
            $_SESSION['mensaje'] = 'La ubicación del elemento ' . ($el['codigo_interno'] ?? $el['id']) . ' no pertenece a la sede';
            header('Location: actas.php');
            exit;
        }
        if ($el['ubicacion']) $ubicaciones[] = $el['ubicacion'];
    }

    $institucion = require __DIR__ . '/../config/institucion.php';
    $logo = obtenerLogo() ? __DIR__ . '/../' . obtenerLogo() : '';

    try {
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 14, 'margin_right' => 14]);
        $mpdf->WriteHTML(construirActaHTML($institucion, $profesor, $sedeNombre, $elementos, $ubicaciones, $logo));
        $dir = __DIR__ . '/../uploads/actas';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $nombrePdf = 'acta_' . $responsable_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $mpdf->Output("$dir/$nombrePdf", \Mpdf\Output\Destination::FILE);

        $conn->prepare("INSERT INTO actas (responsable_id, sede_id, usuario_id, fecha_generacion, archivo_pdf) VALUES (?, ?, ?, NOW(), ?)")
            ->execute([$responsable_id, $sede_id, (int)$_SESSION['user_id'], "actas/$nombrePdf"]);
        $actaId = $conn->lastInsertId();
        $insDet = $conn->prepare("INSERT INTO acta_elementos (acta_id, elemento_id) VALUES (?, ?)");
        foreach ($elementos as $el) {
            $insDet->execute([$actaId, $el['id']]);
            registrarEventoHistorial(
                $conn, $el['id'], 'generacion_acta',
                'Acta de entrega y responsabilidad generada',
                null,
                ['acta_id' => $actaId, 'responsable' => trim($profesor['nombre'] . ' ' . $profesor['apellido'])],
                (int)$_SESSION['user_id'],
                null,
                $actaId
            );
        }
        $_SESSION['mensaje'] = 'Acta generada correctamente (' . count($elementos) . ' elementos)';
        registrarAuditoria(
            $conn, 'generar_acta', 'actas', 'acta', $actaId,
            'Acta de entrega y responsabilidad generada (' . count($elementos) . ' elementos) para ' . trim($profesor['nombre'] . ' ' . $profesor['apellido']),
            null,
            ['acta_id' => $actaId, 'responsable_id' => $responsable_id, 'sede_id' => $sede_id, 'elementos' => count($elementos), 'archivo_pdf' => "actas/$nombrePdf"]
        );
        header('Location: actas.php?id=' . $actaId);
        exit;
    } catch (Throwable $e) {
        logError("Error generando acta: " . $e->getMessage());
        $_SESSION['mensaje'] = 'No se pudo generar el acta: ' . $e->getMessage();
        header('Location: actas.php');
        exit;
    }
}

$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$profesores = $conn->query("SELECT id, nombre, apellido, sede_id FROM profesores WHERE estado='Activo' ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC);
$elementosPorProfesor = [];
$todosElementos = $conn->query("SELECT id, codigo_interno, nombre, marca, estado, ubicacion, profesor_id FROM inventario_general WHERE activo=1 AND profesor_id IS NOT NULL ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
foreach ($todosElementos as $el) {
    $elementosPorProfesor[$el['profesor_id']][] = $el;
}

$actas = $conn->query("SELECT a.*, p.nombre as prof_nombre, p.apellido as prof_apellido, s.nombre as sede_nombre, u.nombre as usuario_nombre, (SELECT COUNT(*) FROM acta_elementos ae WHERE ae.acta_id=a.id) as total_elementos FROM actas a LEFT JOIN profesores p ON a.responsable_id=p.id LEFT JOIN sedes s ON a.sede_id=s.id LEFT JOIN usuarios u ON a.usuario_id=u.id ORDER BY a.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$pageTitle = 'Actas de Entrega - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_inventario_general/actas.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-file-signature"></i> Actas de Entrega y Responsabilidad</h2>
        <p>Generación de actas para elementos asignados a un responsable</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('generarModal')">
            <i class="fas fa-file-pdf"></i> Generar Acta
        </button>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="glass-card" style="padding:18px 22px;margin-bottom:24px;">
    <h4 style="margin:0 0 4px;font-weight:600;"><i class="fas fa-history"></i> Historial de Actas</h4>
    <p style="font-size:0.82rem;color:var(--gray);margin:0 0 14px;">El historial se conserva aunque un elemento cambie de responsable. La estructura permite registrar posteriormente entrega, devolución, reasignación o cambio de responsable.</p>
    <?php if(count($actas) == 0): ?>
    <div style="padding:30px;text-align:center;color:var(--gray);">
        <i class="fas fa-file-alt" style="font-size:2.5rem;display:block;margin-bottom:10px;color:var(--gray-light);"></i>
        No se han generado actas todavía
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Responsable</th>
                    <th>Sede</th>
                    <th>Elementos</th>
                    <th>Generada</th>
                    <th>Generada por</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actas as $a): ?>
                <tr>
                    <td><strong><?php echo $a['id']; ?></strong></td>
                    <td><?php echo htmlspecialchars(trim($a['prof_nombre'] . ' ' . $a['prof_apellido'])); ?></td>
                    <td><?php echo htmlspecialchars($a['sede_nombre'] ?? '—'); ?></td>
                    <td><span class="badge badge-info"><?php echo $a['total_elementos']; ?></span></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($a['fecha_generacion'])); ?></td>
                    <td><?php echo htmlspecialchars($a['usuario_nombre'] ?? '—'); ?></td>
                    <td><span class="badge <?php echo $a['estado'] === 'generada' ? 'badge-warning' : 'badge-success'; ?>"><?php echo ucfirst($a['estado']); ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <a href="ver_acta.php?id=<?php echo $a['id']; ?>" target="_blank" class="btn-icon" title="Ver acta"><i class="fas fa-eye"></i></a>
                            <a href="ver_acta.php?id=<?php echo $a['id']; ?>&accion=descargar" class="btn-icon" title="Descargar acta"><i class="fas fa-download"></i></a>
                            <a href="ver_acta.php?id=<?php echo $a['id']; ?>&accion=imprimir" target="_blank" class="btn-icon" title="Imprimir acta"><i class="fas fa-print"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL GENERAR ACTA -->
<div class="modal" id="generarModal">
    <div class="modal-content glass-card" style="max-width:720px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-pdf"></i> Generar Acta de Entrega</h3>
            <button class="modal-close" onclick="closeModal('generarModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="generarForm">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="generar_acta" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Sede <span class="required">*</span></label>
                        <select class="form-control" name="sede_id" id="acta_sede_id" onchange="filtrarResponsables()">
                            <option value="">Seleccione la sede</option>
                            <?php foreach ($sedes as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsable <span class="required">*</span></label>
                        <select class="form-control" name="responsable_id" id="acta_responsable_id" disabled onchange="cargarElementosResponsable()">
                            <option value="">Primero seleccione la sede</option>
                            <?php foreach ($profesores as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-sede="<?php echo $p['sede_id']; ?>"><?php echo htmlspecialchars(trim($p['nombre'] . ' ' . $p['apellido'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="acta_elementos_group" style="display:none;">
                    <label>Elementos del responsable <span class="required">*</span></label>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="seleccionarTodos(true)"><i class="fas fa-check-double"></i> Seleccionar todos</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="seleccionarTodos(false)"><i class="fas fa-times"></i> Quitar todos</button>
                        <span id="acta_seleccion_count" class="badge badge-info" style="font-size:0.8rem;">0 seleccionados</span>
                    </div>
                    <div id="acta_elementos_list" style="max-height:300px;overflow-y:auto;border:1px solid var(--gray-light);border-radius:8px;padding:10px;">
                        <p style="color:var(--gray);font-size:0.85rem;margin:0;">Seleccione primero la sede y el responsable.</p>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block" onclick="return validarGenerarActa()"><i class="fas fa-file-pdf"></i> Generar Acta (PDF)</button>
            </form>
        </div>
    </div>
</div>

<script>
var elementosPorProfesor = <?php
    echo json_encode($elementosPorProfesor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

var preseleccion = {
    sede: <?php echo (int)($_GET['sede'] ?? 0); ?>,
    responsable: <?php echo (int)($_GET['responsable'] ?? 0); ?>,
    elemento: <?php echo (int)($_GET['elemento'] ?? 0); ?>
};

function filtrarResponsables() {
    var sede = document.getElementById('acta_sede_id').value;
    var respSelect = document.getElementById('acta_responsable_id');
    respSelect.innerHTML = '';
    if (!sede) {
        respSelect.innerHTML = '<option value="">Primero seleccione la sede</option>';
        respSelect.disabled = true;
        document.getElementById('acta_elementos_group').style.display = 'none';
        return;
    }
    var options = document.querySelectorAll('#acta_responsable_id option');
    document.querySelectorAll('#acta_responsable_id option').forEach(function(o) { o.remove(); });
    <?php foreach ($profesores as $p): ?>
    if (<?php echo $p['sede_id']; ?> == sede) {
        var opt = document.createElement('option');
        opt.value = <?php echo $p['id']; ?>;
        opt.textContent = '<?php echo htmlspecialchars(trim($p['nombre'] . ' ' . $p['apellido']), ENT_QUOTES); ?>';
        respSelect.appendChild(opt);
    }
    <?php endforeach; ?>
    if (respSelect.options.length === 0) {
        respSelect.innerHTML = '<option value="">No hay profesores activos en esta sede</option>';
        respSelect.disabled = true;
    } else {
        respSelect.disabled = false;
    }
    document.getElementById('acta_elementos_group').style.display = 'none';
}

function cargarElementosResponsable() {
    var respId = document.getElementById('acta_responsable_id').value;
    var group = document.getElementById('acta_elementos_group');
    var list = document.getElementById('acta_elementos_list');
    var count = document.getElementById('acta_seleccion_count');
    if (!respId) { group.style.display = 'none'; return; }
    var elementos = elementosPorProfesor[respId] || [];
    group.style.display = 'block';
    if (elementos.length === 0) {
        list.innerHTML = '<p style="color:var(--gray);font-size:0.85rem;margin:0;">Este responsable no tiene elementos asignados.</p>';
        count.textContent = '0 seleccionados';
        return;
    }
    var html = '';
    elementos.forEach(function(el) {
        html += '<label style="display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:0.88rem;">';
        html += '<input type="checkbox" name="elementos[]" value="' + el.id + '" onchange="actualizarContador()">';
        html += '<span style="font-weight:600;font-family:monospace;font-size:0.8rem;">' + el.codigo_interno + '</span>';
        html += ' — ' + el.nombre + (el.marca ? ' (' + el.marca + ')' : '') + (el.estado ? ' <span class="badge badge-info" style="font-size:0.7rem;">' + el.estado + '</span>' : '');
        html += '</label>';
    });
    list.innerHTML = html;
    actualizarContador();
}

function actualizarContador() {
    var checks = document.querySelectorAll('#acta_elementos_list input[name="elementos[]"]:checked');
    document.getElementById('acta_seleccion_count').textContent = checks.length + ' seleccionados';
}

function seleccionarTodos(estado) {
    document.querySelectorAll('#acta_elementos_list input[name="elementos[]"]').forEach(function(c) { c.checked = estado; });
    actualizarContador();
}

function validarGenerarActa() {
    var sede = document.getElementById('acta_sede_id').value;
    var resp = document.getElementById('acta_responsable_id').value;
    var checks = document.querySelectorAll('#acta_elementos_list input[name="elementos[]"]:checked');
    if (!sede || !resp) { alert('Debe seleccionar sede y responsable'); return false; }
    if (checks.length === 0) { alert('Debe seleccionar al menos un elemento'); return false; }
    return true;
}

window.addEventListener('load', function() {
    if (preseleccion.sede && preseleccion.responsable && preseleccion.elemento) {
        document.getElementById('acta_sede_id').value = preseleccion.sede;
        filtrarResponsables();
        document.getElementById('acta_responsable_id').value = preseleccion.responsable;
        cargarElementosResponsable();
        var check = document.querySelector('#acta_elementos_list input[name="elementos[]"][value="' + preseleccion.elemento + '"]');
        if (check) check.checked = true;
        actualizarContador();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

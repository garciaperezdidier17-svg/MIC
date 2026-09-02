<?php
$pageTitle = 'Solicitudes - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = 'solicitudes.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header animate-fade-up" style="margin-bottom:24px;">
    <div class="page-title">
        <h2><i class="fas fa-clipboard-list"></i> Solicitudes</h2>
        <p>Gestión de solicitudes de préstamo de elementos</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('nuevaSolicitudModal')">
            <i class="fas fa-plus"></i> Nueva Solicitud
        </button>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success animate-fade-down"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if(isset($errorSolicitudes) && $errorSolicitudes): ?>
<div class="alert alert-danger animate-fade-down"><?php echo htmlspecialchars($errorSolicitudes); ?></div>
<?php endif; ?>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="glass-card kpi-card animate-fade-up" style="margin-bottom:0;">
        <div class="kpi-icon yellow-gradient"><i class="fas fa-clock"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalPendientes; ?></div>
            <div class="kpi-label">Pendientes</div>
        </div>
        <div class="kpi-trend <?php echo $totalPendientes > 0 ? 'negative' : 'positive'; ?>">
            <i class="fas fa-<?php echo $totalPendientes > 0 ? 'exclamation' : 'check'; ?>"></i>
            <?php echo $porcentajePendientes; ?>%
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1" style="margin-bottom:0;">
        <div class="kpi-icon green-gradient"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalAprobadas; ?></div>
            <div class="kpi-label">Aprobadas</div>
        </div>
        <div class="kpi-trend positive">
            <i class="fas fa-check"></i>
            <?php echo $porcentajeAprobadas; ?>%
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2" style="margin-bottom:0;">
        <div class="kpi-icon red-gradient"><i class="fas fa-times-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $totalRechazadas; ?></div>
            <div class="kpi-label">Rechazadas</div>
        </div>
        <div class="kpi-trend negative">
            <i class="fas fa-times"></i>
            <?php echo $porcentajeRechazadas; ?>%
        </div>
    </div>
</div>

<div class="section-header animate-fade-up delay-2" style="margin-top:28px;">
    <h3><i class="fas fa-list"></i> Listado de Solicitudes</h3>
    <p>Las solicitudes registradas en el sistema</p>
</div>

<div class="glass-card animate-fade-up delay-3" style="padding:0;overflow:hidden;">
    <table class="premium-table" style="margin-bottom:0;">
        <thead>
            <tr>
                <th>#</th>
                <th>Elementos</th>
                <th>Responsable</th>
                <th>Sede</th>
                <th>Préstamo</th>
                <th>Devolución</th>
                <th>Estado</th>
                <th>Solicitante</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($listaSolicitudes) == 0): ?>
            <tr>
                <td colspan="9"><div class="empty-state"><i class="fas fa-clipboard-list"></i><h3>No hay solicitudes</h3><p>Aún no hay solicitudes registradas.</p></div></td>
            </tr>
            <?php else: ?>
                <?php foreach($listaSolicitudes as $solicitud): ?>
                <tr>
                    <td><span class="text-muted" style="font-weight:600;">#<?php echo $solicitud['id']; ?></span></td>
                    <td style="max-width:220px;"><strong><?php echo htmlspecialchars($solicitud['elementos_txt']); ?></strong></td>
                    <td><?php echo htmlspecialchars($solicitud['responsable_txt'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['sede_nombre'] ?? '—'); ?></td>
                    <td><?php echo $solicitud['fecha_prestamo'] ? date('d/m/Y', strtotime($solicitud['fecha_prestamo'])) : date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                    <td><?php echo $solicitud['fecha_devolucion_esperada'] ? date('d/m/Y', strtotime($solicitud['fecha_devolucion_esperada'])) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <span class="badge <?php echo $solicitud['estado'] == 'pendiente' ? 'badge-warning' : ($solicitud['estado'] == 'aprobada' ? 'badge-success' : 'badge-danger'); ?>">
                            <?php echo ucfirst($solicitud['estado']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($solicitud['usuario_nombre'] ?? '-'); ?></td>
                    <td>
                        <div class="action-buttons">
                            <?php if ($esAdmin): ?>
                            <?php if($solicitud['estado'] == 'pendiente'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Aprobar esta solicitud? Verificará la disponibilidad de los elementos.')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="aprobar_solicitud" value="1">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <button type="submit" class="btn-icon btn-approve" title="Aprobar"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Rechazar esta solicitud?')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="rechazar_solicitud" value="1">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <button type="submit" class="btn-icon btn-reject" title="Rechazar"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta solicitud?')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="eliminar_solicitud" value="1">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <button type="submit" class="btn-icon btn-delete" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if($totalPaginas > 1): ?>
    <div class="pagination" style="margin-top:0;">
        <?php if($numeroPagina > 1): ?>
        <a href="?page=<?php echo $numeroPagina-1; ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for($i = 1; $i <= $totalPaginas; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $numeroPagina ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if($numeroPagina < $totalPaginas): ?>
        <a href="?page=<?php echo $numeroPagina+1; ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="nuevaSolicitudModal">
    <div class="modal-content modal-content-lg">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-list"></i> Nueva Solicitud de Préstamo</h3>
            <button class="modal-close" onclick="closeModal('nuevaSolicitudModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="crear_solicitud" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Sede <span class="required">*</span></label>
                        <select class="form-control" name="id_sede" id="sol_id_sede" onchange="solCargarProfesores()" required>
                            <option value="">Seleccionar sede</option>
                            <?php foreach($sedes as $sede): ?>
                            <option value="<?php echo $sede['id']; ?>"><?php echo htmlspecialchars($sede['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsable <span class="required">*</span></label>
                        <select class="form-control" name="id_profesor" id="sol_id_profesor" required>
                            <option value="">Seleccionar sede primero</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de Préstamo</label>
                        <input type="date" class="form-control" name="fecha_prestamo" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha de Devolución Esperada</label>
                        <input type="date" class="form-control" name="fecha_devolucion_esperada" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Motivo <span class="required">*</span></label>
                    <textarea class="form-control" name="motivo" rows="2" placeholder="¿Para qué se solicita? Ej: clase de informática" required></textarea>
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="2" placeholder="Observaciones adicionales (opcional)"></textarea>
                </div>
                <div class="section-header" style="margin-top:16px;">
                    <h3 style="font-size:15px;"><i class="fas fa-boxes"></i> Elementos solicitados</h3>
                    <p>Agregue uno o más elementos. Si elige "Por cantidad" indique cuántas unidades necesita.</p>
                </div>
                <table class="premium-table" style="margin-bottom:10px;">
                    <thead>
                        <tr>
                            <th style="min-width:240px;">Elemento</th>
                            <th>Tipo</th>
                            <th style="width:100px;">Cantidad</th>
                            <th style="width:45px;"></th>
                        </tr>
                    </thead>
                    <tbody id="sol_items"></tbody>
                </table>
                <button type="button" class="btn btn-secondary" onclick="solAddFila()" style="margin-bottom:16px;">
                    <i class="fas fa-plus"></i> Agregar elemento
                </button>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Enviar Solicitud
                </button>
            </form>
        </div>
    </div>
</div>

<script>
var solGrupos = <?php echo json_encode($grupos); ?>;
var solSedeRep = <?php echo json_encode($sedePorRepresentante); ?>;
var solFilaActual = 0;

function solOpcionesElemento() {
    var sel = document.createElement('select');
    sel.className = 'form-control';
    sel.name = 'elem_id[]';
    sel.required = true;
    var vacio = document.createElement('option');
    vacio.value = '';
    vacio.textContent = 'Seleccionar elemento';
    sel.appendChild(vacio);
    solGrupos.forEach(function(g) {
        var opt = document.createElement('option');
        opt.value = g.representante_id;
        opt.textContent = g.nombre + ' (' + g.disponibles + ' disponibles)';
        opt.dataset.sede = solSedeRep[g.representante_id] || '';
        sel.appendChild(opt);
    });
    return sel;
}

function solAplicarSedeFiltro() {
    var sede = document.getElementById('sol_id_sede').value;
    var selects = document.querySelectorAll('#sol_items select[name="elem_id[]"]');
    selects.forEach(function(sel) {
        Array.prototype.forEach.call(sel.options, function(opt) {
            if (opt.value === '') { return; }
            opt.hidden = sede !== '' && opt.dataset.sede !== sede;
        });
    });
}

function solToggleTipo(tr) {
    var tipo = tr.querySelector('select[name="elem_tipo[]"]').value;
    var cant = tr.querySelector('input[name="elem_cantidad[]"]');
    if (tipo === 'cantidad') {
        cant.disabled = false;
        cant.value = (parseInt(cant.value, 10) || 1) < 2 ? 2 : cant.value;
    } else {
        cant.disabled = true;
        cant.value = '1';
    }
}

function solAddFila() {
    var tbody = document.getElementById('sol_items');
    var tr = document.createElement('tr');
    solFilaActual++;

    var tdEl = document.createElement('td');
    var sel = solOpcionesElemento();
    tdEl.appendChild(sel);

    var tdTipo = document.createElement('td');
    var selTipo = document.createElement('select');
    selTipo.className = 'form-control';
    selTipo.name = 'elem_tipo[]';
    ['individual', 'cantidad'].forEach(function(t) {
        var o = document.createElement('option');
        o.value = t;
        o.textContent = t === 'individual' ? 'Individual' : 'Por cantidad';
        selTipo.appendChild(o);
    });
    selTipo.onchange = function() { solToggleTipo(tr); };
    tdTipo.appendChild(selTipo);

    var tdCant = document.createElement('td');
    var cant = document.createElement('input');
    cant.type = 'number';
    cant.className = 'form-control';
    cant.name = 'elem_cantidad[]';
    cant.min = '1';
    cant.value = '1';
    cant.disabled = true;
    tdCant.appendChild(cant);

    var tdBtn = document.createElement('td');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-icon btn-delete';
    btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
    btn.onclick = function() { tbody.removeChild(tr); };
    tdBtn.appendChild(btn);

    tr.appendChild(tdEl);
    tr.appendChild(tdTipo);
    tr.appendChild(tdCant);
    tr.appendChild(tdBtn);
    tbody.appendChild(tr);
    solAplicarSedeFiltro();
}

function solCargarProfesores() {
    var sede = document.getElementById('sol_id_sede').value;
    var pro = document.getElementById('sol_id_profesor');
    pro.innerHTML = '<option value="">' + (sede === '' ? 'Seleccionar sede primero' : 'Cargando...') + '</option>';
    solAplicarSedeFiltro();
    if (sede === '') { return; }
    fetch('api_profesores.php?id_sede=' + encodeURIComponent(sede))
        .then(function(resp) { return resp.json(); })
        .then(function(datos) {
            pro.innerHTML = '<option value="">Seleccionar responsable</option>';
            if (datos.ok && datos.profesores) {
                datos.profesores.forEach(function(p) {
                    var o = document.createElement('option');
                    o.value = p.id;
                    o.textContent = p.nombre_completo;
                    pro.appendChild(o);
                });
            }
            pro.required = true;
        })
        .catch(function() {
            pro.innerHTML = '<option value="">Error al cargar responsables</option>';
        });
}

solAddFila();
</script>

<?php require_once '../includes/footer.php'; ?>
<?php
$pageTitle = 'Préstamos - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = 'prestamos.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-handshake"></i> Préstamos</h2>
        <p>Control de préstamos de elementos institucionales</p>
    </div>
    <?php if ($esAdmin): ?>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addPrestamoModal')">
            <i class="fas fa-plus"></i> Nuevo Préstamo
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if(isset($errorPrestamos) && $errorPrestamos): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($errorPrestamos); ?></div>
<?php endif; ?>

<div class="kpi-grid" style="grid-template-columns:repeat(6,1fr);">
    <div class="glass-card kpi-card animate-fade-up" style="margin-bottom:0;">
        <div class="kpi-icon blue-gradient"><i class="fas fa-handshake"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['activos']; ?></div>
            <div class="kpi-label">Activos</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1" style="margin-bottom:0;">
        <div class="kpi-icon orange-gradient"><i class="fas fa-hourglass-half"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['vencidos']; ?></div>
            <div class="kpi-label">Vencidos</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1" style="margin-bottom:0;">
        <div class="kpi-icon yellow-gradient"><i class="fas fa-clock"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['vence_hoy']; ?></div>
            <div class="kpi-label">Vencen hoy</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2" style="margin-bottom:0;">
        <div class="kpi-icon purple-gradient"><i class="fas fa-hourglass-start"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['proximos']; ?></div>
            <div class="kpi-label">Próximos (3 días)</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2" style="margin-bottom:0;">
        <div class="kpi-icon green-gradient"><i class="fas fa-undo"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['devueltos']; ?></div>
            <div class="kpi-label">Devueltos</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-3" style="margin-bottom:0;">
        <div class="kpi-icon gray-gradient"><i class="fas fa-database"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['total']; ?></div>
            <div class="kpi-label">Total</div>
        </div>
    </div>
</div>

<?php if ($esAdmin): ?>
<div class="glass-card animate-fade-up delay-2" style="padding:16px;margin-top:24px;">
    <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>Buscar</label>
            <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Responsable, elemento, ID" style="min-width:220px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Estado</label>
            <select class="form-control" name="estado">
                <option value="">Todos</option>
                <?php foreach(['activo','vencido','devuelto','cancelado','pendiente','aprobado'] as $est): ?>
                <option value="<?php echo $est; ?>" <?php echo $estadoSel === $est ? 'selected' : ''; ?>><?php echo ucfirst($est); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Sede</label>
            <select class="form-control" name="sede">
                <option value="">Todas</option>
                <?php foreach($sedes as $sede): ?>
                <option value="<?php echo $sede['id']; ?>" <?php echo $sedeSel === (int)$sede['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sede['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
        <a href="prestamos.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
    </form>
</div>
<?php endif; ?>

<div class="glass-card animate-fade-up" style="padding:0;overflow:hidden;margin-top:24px;">
    <table class="premium-table" style="margin-bottom:0;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Responsable</th>
                <th>Sede</th>
                <th>Elementos</th>
                <th>Fecha Préstamo</th>
                <th>Devolución Esperada</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($prestamos) == 0): ?>
            <tr>
                <td colspan="8"><div class="empty-state"><i class="fas fa-handshake"></i><h3>No hay préstamos</h3><p>No se encontraron préstamos con los criterios actuales.</p></div></td>
            </tr>
            <?php else: ?>
                <?php foreach($prestamos as $p): ?>
                <?php
                $esVencido = in_array($p['estado'], ['vencido']);
                $esVenceHoy = $p['estado'] == 'activo' && ($p['fecha_devolucion_esperada'] ?? '') == date('Y-m-d');
                ?>
                <tr>
                    <td>#<?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['responsable_txt'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['sede_nombre'] ?? '—'); ?></td>
                    <td style="max-width:240px;" title="<?php echo htmlspecialchars($p['elementos_txt']); ?>"><?php echo htmlspecialchars($p['elementos_txt']); ?></td>
                    <td><?php echo !empty($p['fecha_prestamo']) ? date('d/m/Y', strtotime($p['fecha_prestamo'])) : '—'; ?></td>
                    <td>
                        <?php if (!empty($p['fecha_devolucion_esperada'])): ?>
                        <span style="<?php echo $esVencido ? 'color:var(--danger);font-weight:700;' : ($esVenceHoy ? 'color:var(--warning);font-weight:700;' : ''); ?>">
                            <?php echo date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])); ?>
                        </span>
                        <?php echo $esVencido ? ' <i class="fas fa-exclamation-circle" title="Vencido"></i>' : ''; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $p['estado'] == 'activo' ? 'badge-success' : ($p['estado'] == 'vencido' ? 'badge-danger' : ($p['estado'] == 'devuelto' ? 'badge-info' : 'badge-warning')); ?>">
                            <?php echo ucfirst($p['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if(in_array($p['estado'], ['activo', 'vencido', 'parcialmente devuelto'])): ?>
                            <button type="button" class="btn-icon" style="color:var(--success);border:none;background:none;cursor:pointer;" title="Devolver"
                                    onclick='abrirDevolucion(<?php echo $p['id']; ?> , <?php echo htmlspecialchars(json_encode($p['elementos']), ENT_QUOTES, 'UTF-8'); ?>)'>
                                <i class="fas fa-undo"></i>
                            </button>
                            <?php endif; ?>
                            <?php if(in_array($p['estado'], ['activo', 'vencido', 'parcialmente devuelto', 'devuelto'])): ?>
                            <a href="generar_acta.php?id=<?php echo $p['id']; ?>&tipo=entrega" target="_blank" class="btn-icon" style="color:var(--primary);border:none;background:none;cursor:pointer;" title="Acta de Entrega">
                                <i class="fas fa-file-export"></i>
                            </a>
                            <?php endif; ?>
                            <?php if(in_array($p['estado'], ['parcialmente devuelto', 'devuelto'])): ?>
                            <a href="generar_acta.php?id=<?php echo $p['id']; ?>&tipo=devolucion" target="_blank" class="btn-icon" style="color:var(--info);border:none;background:none;cursor:pointer;" title="Acta de Devolución">
                                <i class="fas fa-file-import"></i>
                            </a>
                            <?php endif; ?>
                            <?php if(in_array($p['estado'], ['pendiente', 'aprobado', 'activo', 'vencido', 'parcialmente devuelto'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar este préstamo? Se liberarán los elementos.')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="cancelar_prestamo" value="1">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn-icon" style="color:var(--warning);border:none;background:none;cursor:pointer;" title="Cancelar préstamo">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($esAdmin): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este préstamo? Esta acción no se puede deshacer.')">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="eliminar_prestamo" value="1">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
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
        <?php
        $qs = function($extraPage) use ($estadoSel, $sedeSel, $buscar) {
            $parametros = [];
            if ($estadoSel !== '') { $parametros['estado'] = $estadoSel; }
            if ($sedeSel > 0) { $parametros['sede'] = $sedeSel; }
            if ($buscar !== '') { $parametros['q'] = $buscar; }
            $parametros['page'] = $extraPage;
            return '?' . http_build_query($parametros);
        };
        ?>
        <?php if($page > 1): ?>
        <a href="<?php echo $qs($page-1); ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for($i = 1; $i <= $totalPaginas; $i++): ?>
        <a href="<?php echo $qs($i); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if($page < $totalPaginas): ?>
        <a href="<?php echo $qs($page+1); ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <div class="pagination-info" style="padding:0 16px 12px;">
        Mostrando página <?php echo $page; ?> de <?php echo $totalPaginas; ?> (<?php echo $totalRegistros; ?> registros)
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="devolucionModal">
    <div class="modal-content modal-content-lg">
        <div class="modal-header">
            <h3><i class="fas fa-undo"></i> Registrar Devolución</h3>
            <button class="modal-close" onclick="closeModal('devolucionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" enctype="multipart/form-data" id="devolucionForm">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="devolver" value="1">
                <input type="hidden" name="id" id="dev_id" value="">
                <p class="text-muted" style="margin-bottom:12px;">Indique el estado de cada elemento devuelto. Los elementos marcados como <strong>Dañado</strong> generarán una alerta para administración.</p>
                <table class="premium-table" style="margin-bottom:14px;">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="dev_check_all" checked onchange="toggleDevAll()"></th>
                            <th>Elemento</th>
                            <th style="width:130px;">Estado de Devolución</th>
                            <th>Observaciones</th>
                            <th style="width:150px;">Evidencia (foto)</th>
                        </tr>
                    </thead>
                    <tbody id="dev_items"></tbody>
                </table>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check-circle"></i> Confirmar Devolución
                </button>
            </form>
        </div>
    </div>
</div>

<?php if ($esAdmin): ?>
<div class="modal" id="addPrestamoModal">
    <div class="modal-content modal-content-lg">
        <div class="modal-header">
            <h3><i class="fas fa-handshake"></i> Nuevo Préstamo Directo</h3>
            <button class="modal-close" onclick="closeModal('addPrestamoModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="crear_prestamo" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Sede <span class="required">*</span></label>
                        <select class="form-control" name="id_sede" id="pr_id_sede" onchange="prCargarProfesores()" required>
                            <option value="">Seleccionar sede</option>
                            <?php foreach($sedes as $sede): ?>
                            <option value="<?php echo $sede['id']; ?>"><?php echo htmlspecialchars($sede['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsable <span class="required">*</span></label>
                        <select class="form-control" name="id_profesor" id="pr_id_profesor" required>
                            <option value="">Seleccionar sede primero</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Préstamo</label>
                        <input type="date" class="form-control" name="fecha_prestamo" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Devolución</label>
                        <input type="date" class="form-control" name="fecha_devolucion" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                </div>
                <div class="section-header" style="margin-top:16px;">
                    <h3 style="font-size:15px;"><i class="fas fa-boxes"></i> Elementos</h3>
                    <p>Seleccione los elementos que se entregarán.</p>
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
                    <tbody id="pr_items"></tbody>
                </table>
                <button type="button" class="btn btn-secondary" onclick="prAddFila()" style="margin-bottom:16px;">
                    <i class="fas fa-plus"></i> Agregar elemento
                </button>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-handshake"></i> Registrar Préstamo
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
var prGrupos = <?php echo json_encode($grupos); ?>;
var prSedeRep = <?php echo json_encode($sedePorRepresentante); ?>;
var prFilaActual = 0;

function prOpcionesElemento() {
    var sel = document.createElement('select');
    sel.className = 'form-control';
    sel.name = 'elem_id[]';
    sel.required = true;
    var vacio = document.createElement('option');
    vacio.value = '';
    vacio.textContent = 'Seleccionar elemento';
    sel.appendChild(vacio);
    prGrupos.forEach(function(g) {
        var opt = document.createElement('option');
        opt.value = g.representante_id;
        opt.textContent = g.nombre + ' (' + g.disponibles + ' disponibles)';
        opt.dataset.sede = prSedeRep[g.representante_id] || '';
        sel.appendChild(opt);
    });
    return sel;
}

function prAplicarSedeFiltro() {
    var sede = document.getElementById('pr_id_sede').value;
    var selects = document.querySelectorAll('#pr_items select[name="elem_id[]"]');
    selects.forEach(function(sel) {
        Array.prototype.forEach.call(sel.options, function(opt) {
            if (opt.value === '') { return; }
            opt.hidden = sede !== '' && opt.dataset.sede !== sede;
        });
    });
}

function prToggleTipo(tr) {
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

function prAddFila() {
    var tbody = document.getElementById('pr_items');
    var tr = document.createElement('tr');
    prFilaActual++;

    var tdEl = document.createElement('td');
    tdEl.appendChild(prOpcionesElemento());

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
    selTipo.onchange = function() { prToggleTipo(tr); };
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
    prAplicarSedeFiltro();
}

function prCargarProfesores() {
    var sede = document.getElementById('pr_id_sede').value;
    var pro = document.getElementById('pr_id_profesor');
    pro.innerHTML = '<option value="">' + (sede === '' ? 'Seleccionar sede primero' : 'Cargando...') + '</option>';
    prAplicarSedeFiltro();
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

function abrirDevolucion(idPrestamo, elementos) {
    document.getElementById('dev_id').value = idPrestamo;
    var tbody = document.getElementById('dev_items');
    tbody.innerHTML = '';
    
    // Solo mostrar los que no estén devueltos
    elementos = elementos.filter(function(el) { return el.estado_devolucion === null || el.estado_devolucion === ''; });
    
    if (!elementos || !elementos.length) {
        var filaVacia = document.createElement('tr');
        filaVacia.innerHTML = '<td colspan="5" class="text-muted">Todos los elementos ya han sido devueltos.</td>';
        tbody.appendChild(filaVacia);
        document.getElementById('dev_check_all').disabled = true;
    } else {
        document.getElementById('dev_check_all').disabled = false;
        document.getElementById('dev_check_all').checked = true;
        elementos.forEach(function(el) {
            var tr = document.createElement('tr');
            
            var tdChk = document.createElement('td');
            var chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.name = 'dev_seleccionar[' + el.id + ']';
            chk.value = '1';
            chk.className = 'dev-item-chk';
            chk.checked = true;
            tdChk.appendChild(chk);
            tr.appendChild(tdChk);
            
            var nombre = el.nombre + (el.codigo_interno ? ' (' + el.codigo_interno + ')' : '');
            var tdNombre = document.createElement('td');
            var strong = document.createElement('strong');
            strong.textContent = nombre;
            tdNombre.appendChild(strong);
            tr.appendChild(tdNombre);

            var tdEstado = document.createElement('td');
            var selEst = document.createElement('select');
            selEst.name = 'dev_estado[' + el.id + ']';
            selEst.className = 'form-control';
            ['Bueno', 'Regular', 'Dañado', 'Perdido'].forEach(function(e) {
                var o = document.createElement('option');
                o.value = e;
                o.textContent = e;
                selEst.appendChild(o);
            });
            tdEstado.appendChild(selEst);
            tr.appendChild(tdEstado);

            var tdObs = document.createElement('td');
            var inObs = document.createElement('input');
            inObs.type = 'text';
            inObs.className = 'form-control';
            inObs.name = 'dev_obs[' + el.id + ']';
            inObs.placeholder = 'Observaciones';
            tdObs.appendChild(inObs);
            tr.appendChild(tdObs);

            var tdEv = document.createElement('td');
            var inEv = document.createElement('input');
            inEv.type = 'file';
            inEv.className = 'form-control';
            inEv.name = 'dev_evidencia[' + el.id + ']';
            inEv.accept = 'image/*';
            tdEv.appendChild(inEv);
            tr.appendChild(tdEv);

            // Deshabilitar campos si se desmarca
            chk.onchange = function() {
                selEst.disabled = !this.checked;
                inObs.disabled = !this.checked;
                inEv.disabled = !this.checked;
                // Si alguno se desmarca, quitar el check all
                if (!this.checked) document.getElementById('dev_check_all').checked = false;
            };

            tbody.appendChild(tr);
        });
    }
    openModal('devolucionModal');
}

function toggleDevAll() {
    var checkAll = document.getElementById('dev_check_all').checked;
    var checks = document.querySelectorAll('.dev-item-chk');
    checks.forEach(function(chk) {
        chk.checked = checkAll;
        chk.dispatchEvent(new Event('change'));
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
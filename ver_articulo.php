<?php
require_once 'config/conexion.php';
require_once 'modulo_inventario_general/helpers_historial.php';
require_once 'modulo_dashboard/helpers_alertas.php';

$codigo = $_GET['codigo'] ?? '';
if (!$codigo) { header('Location: modulo_inventario_general/index.php'); exit; }

$stmt = $conn->prepare("SELECT ig.*, s.nombre as sede_nombre, p.nombre as prof_nombre, p.apellido as prof_apellido, prov.nombre as proveedor_nombre, prov.nit as proveedor_nit FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id LEFT JOIN profesores p ON ig.profesor_id=p.id LEFT JOIN proveedores prov ON ig.proveedor_id=prov.id WHERE ig.codigo_interno=? AND ig.activo=1");
$stmt->execute([$codigo]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

$actasDelElemento = [];
$mantenimientos = [];
$historial = [];
$alertasElemento = [];
$esAdmin = estaLogueado() && esAdmin();
$sedes = [];
$profesores = [];
$catalogosUbicaciones = [];
$puedeRegistrarMto = false;

if ($item) {
    $actasDelElemento = $conn->prepare("SELECT a.* FROM actas a JOIN acta_elementos ae ON ae.acta_id=a.id WHERE ae.elemento_id=? ORDER BY a.id DESC");
    $actasDelElemento->execute([$item['id']]);
    $actasDelElemento = $actasDelElemento->fetchAll(PDO::FETCH_ASSOC);

    $historial = historialDeElemento($conn, $item['id']);
    $alertasElemento = alertasDeElemento($conn, $item);

    if (!empty($item['numero_serie'])) {
        $mantenimientos = $conn->prepare("SELECT m.*, e.nombre as equipo_nombre, e.codigo_interno as equipo_codigo FROM mantenimiento m JOIN equipos e ON e.id=m.id_equipo WHERE e.numero_serie=? ORDER BY m.fecha_inicio DESC");
        $mantenimientos->execute([$item['numero_serie']]);
        $mantenimientos = $mantenimientos->fetchAll(PDO::FETCH_ASSOC);
        if ($esAdmin) {
            $eqCheck = $conn->prepare("SELECT id FROM equipos WHERE numero_serie=? AND activo=1 LIMIT 1");
            $eqCheck->execute([$item['numero_serie']]);
            $puedeRegistrarMto = (bool)$eqCheck->fetchColumn();
        }
    }

    if ($esAdmin) {
        $sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        $profesores = $conn->query("SELECT id, nombre, apellido, sede_id FROM profesores WHERE estado='Activo' ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC);
        $catalogosUbicaciones = require __DIR__ . '/config/ubicaciones.php';
    }
}

function docUrlVer($path) {
    return $path ? 'uploads/' . $path : '';
}

function estadoMantenimiento($estado) {
    $map = ['programado' => 'badge-secondary', 'en_proceso' => 'badge-warning', 'completado' => 'badge-success', 'cancelado' => 'badge-danger'];
    return $map[$estado] ?? 'badge-secondary';
}

function renderDatosHistorial($anterior, $nuevos) {
    $html = '';
    $claves = array_unique(array_merge($anterior ? array_keys($anterior) : [], $nuevos ? array_keys($nuevos) : []));
    foreach ($claves as $k) {
        if (in_array($k, ['responsable_id', 'archivo', 'acta_id', 'mantenimiento_id'], true)) continue;
        $a = $anterior[$k] ?? null;
        $n = $nuevos[$k] ?? null;
        $esArchivo = $k === 'archivo';
        if (!$esArchivo && $a === null && ($n === null || $n === '')) continue;
        $label = ucfirst(str_replace('_', ' ', $k));
        $html .= '<tr>';
        $html .= '<td style="padding:2px 8px 2px 0;color:var(--gray);font-size:0.78rem;white-space:nowrap;">' . htmlspecialchars($label) . '</td>';
        $valA = $a !== null ? htmlspecialchars(is_array($a) ? json_encode($a) : (string)$a) : '<span class="text-muted">—</span>';
        $valN = $n !== null ? htmlspecialchars(is_array($n) ? json_encode($n) : (string)$n) : '<span class="text-muted">—</span>';
        $html .= '<td style="padding:2px 8px;">' . $valA . '</td>';
        $html .= '<td style="padding:2px 8px;color:var(--primary);font-weight:600;"><i class="fas fa-long-arrow-alt-right"></i></td>';
        $html .= '<td style="padding:2px 0;">' . $valN . '</td>';
        $html .= '</tr>';
    }
    return $html;
}

$mensaje = $_SESSION['mensaje'] ?? '';
$mensajeError = $_SESSION['mensaje_error'] ?? 0;
unset($_SESSION['mensaje'], $_SESSION['mensaje_error']);

$pageTitle = $item ? htmlspecialchars($item['nombre']) . ' - MIC' : 'Artículo no encontrado - MIC';
require_once 'includes/head.php';
?>
</head>
<?php
$paginaActual = basename($_SERVER['PHP_SELF']);
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-qrcode"></i> Artículo por Código QR</h2>
        <p>Resultado de la búsqueda por código de barras / QR</p>
    </div>
    <div class="page-actions">
        <?php if ($esAdmin && $item): ?>
        <button class="btn btn-outline btn-sm" onclick="openModal('reasignarModal')"><i class="fas fa-user-check"></i> Reasignar responsable</button>
        <button class="btn btn-outline btn-sm" onclick="openModal('ubicacionModal')"><i class="fas fa-map-marker-alt"></i> Cambiar ubicación</button>
        <?php if ($puedeRegistrarMto): ?>
        <button class="btn btn-outline btn-sm" onclick="openModal('mantenimientoModal')"><i class="fas fa-tools"></i> Registrar mantenimiento</button>
        <?php endif; ?>
        <form method="POST" action="modulo_inventario_general/acciones_elemento.php" style="display:inline;margin:0;">
            <input type="hidden" name="accion" value="alternar_prestamo">
            <input type="hidden" name="elemento_id" value="<?php echo (int)$item['id']; ?>">
            <?php echo campoCSRF(); ?>
            <button type="submit" class="btn btn-outline btn-sm" title="Cambiar disponibilidad para préstamo"><i class="fas fa-hand-holding"></i> <?php echo $item['disponible_para_prestamo'] ? 'Quitar de préstamo' : 'Habilitar préstamo'; ?></button>
        </form>
        <a href="#historial" class="btn btn-outline btn-sm"><i class="fas fa-history"></i> Ver historial</a>
        <a href="#documentacion" class="btn btn-outline btn-sm"><i class="fas fa-folder-open"></i> Ver documentación</a>
        <a href="#actas" class="btn btn-outline btn-sm"><i class="fas fa-file-signature"></i> Ver actas</a>
        <?php endif; ?>
        <?php if (estaLogueado()): ?>
        <a href="modulo_inventario_general/index.php?search=<?php echo urlencode($codigo); ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Ver en Inventario</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert <?php echo $mensajeError ? 'alert-danger' : 'alert-success'; ?>"><i class="fas fa-<?php echo $mensajeError ? 'exclamation-circle' : 'check-circle'; ?>"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($item): ?>
<div class="glass-card" style="padding:24px;max-width:720px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:20px;">
        <div style="font-size:0.75rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Código Interno</div>
        <div style="font-size:1.3rem;font-weight:700;color:var(--primary);letter-spacing:0.5px;padding:8px 16px;background:rgba(99,102,241,0.08);border-radius:8px;display:inline-block;"><?php echo htmlspecialchars($item['codigo_interno']); ?></div>
        <?php if (!empty($item['qr_path']) && is_file(__DIR__ . '/assets/' . $item['qr_path'])): ?>
        <div style="margin-top:14px;">
            <img src="assets/<?php echo htmlspecialchars($item['qr_path']); ?>" alt="QR del artículo" style="width:120px;height:120px;border-radius:10px;background:#fff;padding:8px;">
        </div>
        <?php endif; ?>
    </div>

    <div style="padding:16px;border-radius:12px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);margin-bottom:20px;">
        <div style="font-size:0.72rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-info-circle"></i> Información actual</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
            <div><strong>Responsable actual</strong><br><span><?php echo $item['prof_nombre'] ? htmlspecialchars(trim($item['prof_nombre'] . ' ' . $item['prof_apellido'])) : '<span class="text-muted">Sin asignar</span>'; ?></span></div>
            <div><strong>Sede actual</strong><br><span><?php echo htmlspecialchars($item['sede_nombre'] ?? '—'); ?></span></div>
            <div><strong>Ubicación actual</strong><br><span><?php echo htmlspecialchars($item['ubicacion'] ?? '—'); ?></span></div>
            <div><strong>Estado</strong><br><span class="badge <?php
                $map = ['bueno'=>'badge-success','nuevo'=>'badge-info','regular'=>'badge-warning','malo'=>'badge-danger'];
                echo $map[$item['estado']] ?? 'badge-secondary';
            ?>"><?php echo ucfirst($item['estado']); ?></span></div>
            <div><strong>Valor de compra</strong><br><span><?php echo $item['valor_compra'] ? '$' . number_format($item['valor_compra'], 0) : '<span class="text-muted">—</span>'; ?></span></div>
            <div><strong>Valor comercial actual</strong><br><span><?php echo $item['vr_comercial'] ? '$' . number_format($item['vr_comercial'], 0) : '<span class="text-muted">—</span>'; ?></span></div>
        </div>
        <div style="margin-top:12px;">
            <strong style="display:block;font-size:0.85rem;margin-bottom:6px;">Alertas</strong>
            <?php if ($alertasElemento): ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php foreach ($alertasElemento as $al): ?>
                <span class="badge <?php echo $al['clase']; ?>"><i class="<?php echo $al['icono']; ?>"></i> <?php echo htmlspecialchars($al['texto']); ?></span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <span class="text-muted" style="font-size:0.85rem;">Ninguna</span>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
        <div><strong>Nombre</strong><br><span><?php echo htmlspecialchars($item['nombre']); ?></span></div>
        <div><strong>Categoría</strong><br><span><?php echo htmlspecialchars($item['categoria'] ?? '—'); ?></span></div>
        <div><strong>Tipo</strong><br><span><?php echo htmlspecialchars($item['tipo']); ?></span></div>
        <div><strong>Origen del bien</strong><br><span><?php echo $item['origen_bien'] ? htmlspecialchars($item['origen_bien']) : 'No registrado'; ?></span></div>
        <div><strong>Documento de adquisición</strong><br><span>
            <?php if ($item['documento_adquisicion']): ?>
            <span style="color:var(--green);font-weight:600;"><i class="fas fa-file-alt"></i> Disponible</span>
            <?php else: ?>
            <span style="color:var(--orange);font-weight:600;"><i class="fas fa-exclamation-triangle"></i> No disponible</span>
            <?php endif; ?>
        </span></div>
        <div><strong>Marca</strong><br><span><?php echo htmlspecialchars($item['marca'] ?? '—'); ?></span></div>
        <div><strong>Modelo</strong><br><span><?php echo htmlspecialchars($item['modelo'] ?? '—'); ?></span></div>
        <div><strong>N° de serie</strong><br><span><?php echo htmlspecialchars($item['numero_serie'] ?? '—'); ?></span></div>
        <div><strong>Vida Útil</strong><br><span><?php echo $item['vida_util'] ? $item['vida_util'] . ' años' : '—'; ?></span></div>
    </div>

    <?php if ($item['descripcion']): ?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong>Descripción</strong>
        <p style="margin-top:4px;font-size:0.88rem;color:var(--gray);"><?php echo nl2br(htmlspecialchars($item['descripcion'])); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($item['observacion']): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong>Observaciones</strong>
        <p style="margin-top:4px;font-size:0.88rem;color:var(--gray);"><?php echo nl2br(htmlspecialchars($item['observacion'])); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($item['origen_bien'] === 'Compra'): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong>Información de compra</strong>
        <p style="margin-top:4px;font-size:0.85rem;color:var(--gray);">
            <?php echo $item['proveedor_nombre'] ? 'Proveedor: ' . htmlspecialchars($item['proveedor_nombre']) . ($item['proveedor_nit'] ? ' (NIT ' . htmlspecialchars($item['proveedor_nit']) . ')' : '') . '<br>' : ''; ?>
            <?php echo $item['numero_factura'] ? 'Factura: ' . htmlspecialchars($item['numero_factura']) . '<br>' : ''; ?>
            <?php echo $item['fecha_compra'] ? 'Fecha de compra: ' . date('d/m/Y', strtotime($item['fecha_compra'])) . '<br>' : ''; ?>
            <?php echo $item['valor_compra'] ? 'Valor de compra: $' . number_format((float)$item['valor_compra'], 0) . '<br>' : ''; ?>
            <?php echo $item['numero_orden_compra'] ? 'Orden de compra: ' . htmlspecialchars($item['numero_orden_compra']) . '<br>' : ''; ?>
            <?php echo $item['fecha_garantia'] ? 'Garantía vence: ' . date('d/m/Y', strtotime($item['fecha_garantia'])) . '<br>' : ''; ?>
        </p>
    </div>
    <?php elseif ($item['origen_bien'] === 'Donación'): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong>Información de donación</strong>
        <p style="margin-top:4px;font-size:0.85rem;color:var(--gray);">
            <?php echo $item['donante_nombre'] ? 'Donante: ' . htmlspecialchars($item['donante_nombre']) . '<br>' : ''; ?>
            <?php echo $item['fecha_donacion'] ? 'Fecha de donación: ' . date('d/m/Y', strtotime($item['fecha_donacion'])) : ''; ?>
        </p>
    </div>
    <?php elseif ($item['origen_bien'] === 'Transferencia'): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong>Información de transferencia</strong>
        <p style="margin-top:4px;font-size:0.85rem;color:var(--gray);">
            <?php echo $item['institucion_origen'] ? 'Institución de origen: ' . htmlspecialchars($item['institucion_origen']) . '<br>' : ''; ?>
            <?php echo $item['fecha_transferencia'] ? 'Fecha de transferencia: ' . date('d/m/Y', strtotime($item['fecha_transferencia'])) : ''; ?>
        </p>
    </div>
    <?php elseif ($item['origen_bien'] === 'Otro'): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong>Descripción del origen</strong>
        <p style="margin-top:4px;font-size:0.85rem;color:var(--gray);"><?php echo $item['descripcion_origen'] ? nl2br(htmlspecialchars($item['descripcion_origen'])) : '<span class="text-muted">Sin descripción registrada.</span>'; ?></p>
    </div>
    <?php endif; ?>

    <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong><i class="fas fa-tools"></i> Información de mantenimiento</strong>
        <?php if ($mantenimientos): ?>
        <div style="margin-top:10px;overflow-x:auto;">
            <table class="table" style="font-size:0.8rem;">
                <thead>
                    <tr>
                        <th>Fecha inicio</th>
                        <th>Fecha fin</th>
                        <th>Descripción</th>
                        <th>Costo</th>
                        <th>Técnico</th>
                        <th>Estado</th>
                        <?php if ($esAdmin): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mantenimientos as $m): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($m['fecha_inicio'])); ?></td>
                        <td><?php echo $m['fecha_fin'] ? date('d/m/Y', strtotime($m['fecha_fin'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($m['descripcion_trabajo']); ?></td>
                        <td><?php echo $m['costo'] ? '$' . number_format((float)$m['costo'], 0) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($m['tecnico'] ?? '—'); ?></td>
                        <td><span class="badge <?php echo estadoMantenimiento($m['estado']); ?>"><?php echo ucfirst(str_replace('_', ' ', $m['estado'])); ?></span></td>
                        <?php if ($esAdmin): ?>
                        <td>
                            <?php if (in_array($m['estado'], ['programado', 'en_proceso'], true)): ?>
                            <form method="POST" action="modulo_inventario_general/acciones_elemento.php" style="margin:0;">
                                <input type="hidden" name="accion" value="finalizar_mantenimiento">
                                <input type="hidden" name="mantenimiento_id" value="<?php echo (int)$m['id']; ?>">
                                <input type="hidden" name="elemento_id" value="<?php echo (int)$item['id']; ?>">
                                <?php echo campoCSRF(); ?>
                                <button type="submit" class="btn btn-outline btn-sm" title="Finalizar mantenimiento"><i class="fas fa-check-double"></i> Finalizar</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="margin-top:6px;font-size:0.85rem;color:var(--gray);">No hay registros de mantenimiento para este elemento.</p>
        <?php endif; ?>
    </div>

    <div id="historial" style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong style="display:block;margin-bottom:14px;"><i class="fas fa-history"></i> Historial del elemento</strong>
        <?php if ($historial): ?>
        <div style="display:flex;flex-direction:column;gap:0;">
            <?php foreach ($historial as $h): ?>
            <?php $info = infoTipoEvento($h['tipo_evento']); ?>
            <div style="display:flex;gap:12px;position:relative;padding-bottom:14px;">
                <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?php echo $info['color']; ?>1a;color:<?php echo $info['color']; ?>;font-size:0.85rem;"><i class="<?php echo $info['icono']; ?>"></i></div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        <strong style="font-size:0.85rem;"><?php echo htmlspecialchars($info['label']); ?></strong>
                        <span style="font-size:0.72rem;color:var(--gray);white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?></span>
                    </div>
                    <?php if ($h['descripcion']): ?><div style="font-size:0.8rem;color:var(--gray);margin-top:2px;"><?php echo htmlspecialchars($h['descripcion']); ?></div><?php endif; ?>
                    <?php
                    $detalles = renderDatosHistorial($h['datos_anterior'], $h['datos_nuevos']);
                    ?>
                    <?php if ($detalles): ?>
                    <table style="margin-top:6px;border-collapse:collapse;width:100%;max-width:480px;background:rgba(255,255,255,0.02);border-radius:8px;font-size:0.78rem;">
                        <?php echo $detalles; ?>
                    </table>
                    <?php endif; ?>
                    <?php if ($h['observacion']): ?>
                    <div style="font-size:0.75rem;margin-top:4px;color:var(--gray);"><i class="fas fa-comment"></i> Motivo: <?php echo htmlspecialchars($h['observacion']); ?></div>
                    <?php endif; ?>
                    <div style="font-size:0.7rem;color:var(--gray);margin-top:3px;"><i class="fas fa-user"></i> <?php echo $h['usuario_nombre'] ? htmlspecialchars($h['usuario_nombre']) : 'Sistema'; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="font-size:0.85rem;color:var(--gray);">Este elemento aún no tiene eventos registrados.</p>
        <?php endif; ?>
    </div>

    <div id="documentacion" style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong style="display:block;margin-bottom:10px;"><i class="fas fa-folder-open"></i> Documentación</strong>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php $docUrl = $item['documento_adquisicion'] ? '../ver_archivo.php?ruta=' . urlencode($item['documento_adquisicion']) : ''; ?>
            <?php if ($docUrl): ?>
            <a href="<?php echo htmlspecialchars($docUrl); ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-alt"></i> Ver documento de adquisición</a>
            <?php endif; ?>
            <?php if ($esAdmin): ?>
            <?php
            $linkGenerar = 'modulo_inventario_general/actas.php';
            if ($item['profesor_id'] && $item['id_sede']) {
                $linkGenerar .= '?sede=' . (int)$item['id_sede'] . '&responsable=' . (int)$item['profesor_id'] . '&elemento=' . (int)$item['id'];
            } else {
                $linkGenerar .= '?elemento=' . (int)$item['id'];
            }
            ?>
            <a href="<?php echo $linkGenerar; ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-signature"></i> Generar acta</a>
            <?php endif; ?>
        </div>
        <?php if (!$docUrl): ?>
        <p style="font-size:0.78rem;color:var(--gray);margin:10px 0 0;">No hay documento de adquisición asociado a este elemento.</p>
        <?php endif; ?>
    </div>

    <div id="actas" style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
        <strong style="display:block;margin-bottom:10px;"><i class="fas fa-file-signature"></i> Actas generadas</strong>
        <?php if ($actasDelElemento): ?>
        <div style="overflow-x:auto;">
            <table class="table" style="font-size:0.8rem;">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actasDelElemento as $acta): ?>
                    <tr>
                        <td><?php echo (int)$acta['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($acta['fecha_generacion'])); ?></td>
                        <td><span class="badge badge-info"><?php echo ucfirst($acta['estado']); ?></span></td>
                        <td>
                            <a href="ver_acta.php?id=<?php echo (int)$acta['id']; ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Ver</a>
                            <a href="ver_acta.php?id=<?php echo (int)$acta['id']; ?>&accion=descargar" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Descargar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="font-size:0.78rem;color:var(--gray);margin:0;">No se han generado actas para este elemento.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($esAdmin): ?>
<script>
var micSedes = <?php echo json_encode($sedes); ?>;
var micProfesores = <?php echo json_encode($profesores); ?>;
var micCatalogos = <?php echo json_encode($catalogosUbicaciones); ?>;
var micElemento = {
    sede_id: <?php echo (int)$item['id_sede']; ?>,
    sede_nombre: <?php echo json_encode($item['sede_nombre'] ?? ''); ?>,
    ubicacion: <?php echo json_encode($item['ubicacion'] ?? ''); ?>,
    profesor_id: <?php echo $item['profesor_id'] ? (int)$item['profesor_id'] : 0; ?>
};

function micProfesoresDeSede(sedeId) {
    return micProfesores.filter(function (p) { return p.sede_id == sedeId; });
}

function cargarModalUbicaciones() {
    var selectSede = document.getElementById('modal_nueva_sede');
    var selectUbi = document.getElementById('modal_ubicacion');
    var selectProf = document.getElementById('modal_profesor');
    var sedeId = parseInt(selectSede.value || '0', 10);
    var sede = micSedes.find(function (s) { return s.id === sedeId; });
    selectUbi.innerHTML = '';
    selectProf.innerHTML = '';
    var ubiDisponibles = [];
    if (sede && micCatalogos[sede.nombre]) {
        ubiDisponibles = micCatalogos[sede.nombre].ubicaciones.map(function (u) { return u.nombre; });
    }
    if (ubiDisponibles.length === 0) {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Sin ubicaciones registradas para esta sede';
        selectUbi.appendChild(opt);
    } else {
        ubiDisponibles.forEach(function (u) {
            var opt = document.createElement('option');
            opt.value = u;
            opt.textContent = u;
            if (u === micElemento.ubicacion) opt.selected = true;
            selectUbi.appendChild(opt);
        });
    }
    var opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'Sin responsable';
    selectProf.appendChild(opt);
    micProfesoresDeSede(sedeId).forEach(function (p) {
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre + ' ' + p.apellido;
        if (p.id === micElemento.profesor_id) opt.selected = true;
        selectProf.appendChild(opt);
    });
}
</script>

<div class="modal" id="reasignarModal">
    <div class="modal-content glass-card" style="max-width:460px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-check"></i> Reasignar responsable</h3>
            <button class="modal-close" onclick="closeModal('reasignarModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.8rem;color:var(--gray);margin-bottom:12px;">
                Elemento: <strong><?php echo htmlspecialchars($item['codigo_interno']); ?></strong><br>
                Responsable actual: <strong><?php echo $item['prof_nombre'] ? htmlspecialchars(trim($item['prof_nombre'] . ' ' . $item['prof_apellido'])) : 'Sin asignar'; ?></strong><br>
                Sede del elemento: <strong><?php echo htmlspecialchars($item['sede_nombre'] ?? '—'); ?></strong>
            </p>
            <form method="POST" action="modulo_inventario_general/acciones_elemento.php">
                <input type="hidden" name="accion" value="reasignar">
                <input type="hidden" name="elemento_id" value="<?php echo (int)$item['id']; ?>">
                <div class="form-group">
                    <label>Nuevo responsable <span class="required">*</span></label>
                    <select class="form-control" name="profesor_id" required>
                        <option value="">Seleccione un responsable de la sede del elemento</option>
                        <?php foreach ($profesores as $p): ?>
                        <?php if ((int)$p['sede_id'] === (int)$item['id_sede']): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars(trim($p['nombre'] . ' ' . $p['apellido'])); ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Motivo</label>
                    <input type="text" class="form-control" name="motivo" placeholder="Ej: Cambio de responsable">
                </div>
                <?php echo campoCSRF(); ?>
                <div style="display:flex;gap:8px;margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-check"></i> Reasignar</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('reasignarModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="ubicacionModal">
    <div class="modal-content glass-card" style="max-width:460px;">
        <div class="modal-header">
            <h3><i class="fas fa-map-marker-alt"></i> Cambiar ubicación</h3>
            <button class="modal-close" onclick="closeModal('ubicacionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.8rem;color:var(--gray);margin-bottom:12px;">
                Elemento: <strong><?php echo htmlspecialchars($item['codigo_interno']); ?></strong><br>
                Sede actual: <strong><?php echo htmlspecialchars($item['sede_nombre'] ?? '—'); ?></strong> — Ubicación actual: <strong><?php echo htmlspecialchars($item['ubicacion'] ?? '—'); ?></strong>
            </p>
            <form method="POST" action="modulo_inventario_general/acciones_elemento.php">
                <input type="hidden" name="accion" value="cambiar_ubicacion">
                <input type="hidden" name="elemento_id" value="<?php echo (int)$item['id']; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Sede nueva <span class="required">*</span></label>
                        <select class="form-control" name="id_sede" id="modal_nueva_sede" onchange="cargarModalUbicaciones()">
                            <?php foreach ($sedes as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)$s['id'] === (int)$item['id_sede'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ubicación nueva <span class="required">*</span></label>
                        <select class="form-control" name="ubicacion" id="modal_ubicacion"></select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Responsable (solo si cambia la sede)</label>
                    <select class="form-control" name="profesor_id" id="modal_profesor"></select>
                    <small style="color:var(--gray);font-size:0.72rem;">Si el responsable actual no pertenece a la nueva sede, debe seleccionarse uno de la nueva sede o dejarlo sin responsable.</small>
                </div>
                <div class="form-group">
                    <label>Motivo <span class="required">*</span></label>
                    <input type="text" class="form-control" name="motivo" placeholder="Ej: Reorganización del aula" required>
                </div>
                <div class="form-group">
                    <label>Observación</label>
                    <textarea class="form-control" name="observacion" rows="2"></textarea>
                </div>
                <?php echo campoCSRF(); ?>
                <div style="display:flex;gap:8px;margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-check"></i> Guardar cambio</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('ubicacionModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($puedeRegistrarMto): ?>
<div class="modal" id="mantenimientoModal">
    <div class="modal-content glass-card" style="max-width:460px;">
        <div class="modal-header">
            <h3><i class="fas fa-tools"></i> Registrar mantenimiento</h3>
            <button class="modal-close" onclick="closeModal('mantenimientoModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="modulo_inventario_general/acciones_elemento.php">
                <input type="hidden" name="accion" value="registrar_mantenimiento">
                <input type="hidden" name="elemento_id" value="<?php echo (int)$item['id']; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de inicio <span class="required">*</span></label>
                        <input type="date" class="form-control" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select class="form-control" name="estado">
                            <option value="programado">Programado</option>
                            <option value="en_proceso">En proceso</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción del trabajo <span class="required">*</span></label>
                    <textarea class="form-control" name="descripcion_trabajo" rows="3" placeholder="Ej: No enciende, se cambió fuente de poder" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Técnico</label>
                        <input type="text" class="form-control" name="tecnico" placeholder="Nombre del técnico">
                    </div>
                    <div class="form-group">
                        <label>Costo</label>
                        <input type="text" class="form-control" name="costo" placeholder="Ej: 150000">
                    </div>
                </div>
                <div class="form-group">
                    <label>Proveedor del servicio</label>
                    <input type="text" class="form-control" name="proveedor" placeholder="Empresa que realiza el mantenimiento">
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="2"></textarea>
                </div>
                <?php echo campoCSRF(); ?>
                <div style="display:flex;gap:8px;margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-check"></i> Registrar</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('mantenimientoModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>if (document.getElementById('modal_ubicacion')) cargarModalUbicaciones();</script>
<?php endif; ?>

<?php else: ?>
<div class="glass-card" style="padding:40px;text-align:center;max-width:500px;margin:0 auto;">
    <i class="fas fa-search" style="font-size:3rem;color:var(--gray);margin-bottom:16px;display:block;"></i>
    <h3 style="color:var(--gray);font-weight:600;">Artículo no encontrado</h3>
    <p style="font-size:0.85rem;color:var(--gray);margin-top:8px;">No existe ningún artículo activo con el código <strong><?php echo htmlspecialchars($codigo); ?></strong></p>
    <?php if (estaLogueado()): ?>
    <a href="modulo_inventario_general/index.php" class="btn btn-primary" style="margin-top:16px;">Ir al Inventario</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

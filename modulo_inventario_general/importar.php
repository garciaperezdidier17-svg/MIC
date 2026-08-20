<?php
/**
 * Importación masiva de inventario desde Excel.
 * Flujo: 1) subir archivo → 2) validar y leer → 3) vista previa con errores
 *        → 4) confirmar importación (solo filas válidas, transacción + QR)
 *        → 5) registro en auditoría.
 */
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_importacion.php';
require_once __DIR__ . '/helpers_historial.php';
require_once __DIR__ . '/../modulo_toma_fisica/helpers_toma_fisica.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$institucion = require __DIR__ . '/../config/institucion.php';
$GLOBALS['institucion'] = $institucion;
$GLOBALS['catalogosUbicaciones'] = require __DIR__ . '/../config/ubicaciones.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['subir_archivo'])) {
    verificarCSRF();
    $validacion = validarArchivoExcelSubido($_FILES['archivo_excel'] ?? null);
    if (!$validacion['ok']) {
        $_SESSION['importacion_error'] = $validacion['error'];
        header('Location: importar.php');
        exit;
    }
    try {
        $filas = leerFilasExcel($_FILES['archivo_excel']['tmp_name']);
        if (count($filas) === 0) {
            $_SESSION['importacion_error'] = 'El archivo no contiene filas de datos (verifique los encabezados de la plantilla).';
            header('Location: importar.php');
            exit;
        }
        if (count($filas) > MAX_EXCEL_FILAS) {
            $_SESSION['importacion_error'] = 'El archivo supera el máximo de ' . MAX_EXCEL_FILAS . ' filas.';
            header('Location: importar.php');
            exit;
        }
        $_SESSION['importacion_pendiente'] = [
            'archivo' => $_FILES['archivo_excel']['name'],
            'filas' => $filas,
        ];
        unset($_SESSION['importacion_error']);
        header('Location: importar.php?preview=1');
        exit;
    } catch (Throwable $e) {
        logError('Error leyendo Excel de importación: ' . $e->getMessage());
        $_SESSION['importacion_error'] = 'No se pudo leer el archivo Excel: ' . $e->getMessage();
        header('Location: importar.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_importacion'])) {
    verificarCSRF();
    $pendiente = $_SESSION['importacion_pendiente'] ?? null;
    if (!$pendiente || empty($pendiente['filas'])) {
        $_SESSION['importacion_error'] = 'No hay una importación pendiente. Suba un archivo nuevamente.';
        header('Location: importar.php');
        exit;
    }
    try {
        // Revalidación en backend con el estado actual de la base de datos
        $resultado = validarFilasImportacion($conn, $pendiente['filas']);
        if (empty($resultado['validas'])) {
            $_SESSION['importacion_error'] = 'Ninguna fila es válida. Corrija el archivo e intente nuevamente.';
            header('Location: importar.php?preview=1');
            exit;
        }
        $res = importarFilasValidas($conn, $resultado['validas'], (int)$_SESSION['user_id']);
        registrarAuditoria(
            $conn, 'importar_inventario', 'inventario', 'importacion', null,
            'Importación masiva: ' . $res['creados'] . ' activos creados desde "' . $pendiente['archivo'] . '"',
            null,
            [
                'archivo' => $pendiente['archivo'],
                'total_filas' => count($pendiente['filas']),
                'validos' => count($resultado['validas']),
                'invalidos' => count($resultado['invalidas']),
                'creados' => $res['creados'],
            ]
        );
        unset($_SESSION['importacion_pendiente']);
        $_SESSION['mensaje'] = 'Importación completada: ' . $res['creados'] . ' activos creados' . (count($resultado['invalidas']) > 0 ? ' (' . count($resultado['invalidas']) . ' filas con errores no se importaron)' : '');
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        logError('Error en importación masiva: ' . $e->getMessage());
        $_SESSION['importacion_error'] = 'Error crítico durante la importación. No se guardó ningún registro: ' . $e->getMessage();
        unset($_SESSION['importacion_pendiente']);
        header('Location: importar.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancelar_importacion'])) {
    verificarCSRF();
    unset($_SESSION['importacion_pendiente'], $_SESSION['importacion_error']);
    header('Location: importar.php');
    exit;
}

$pendiente = $_SESSION['importacion_pendiente'] ?? null;
$errorMsg = $_SESSION['importacion_error'] ?? '';
unset($_SESSION['importacion_error']);

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

$pageTitle = 'Importación de Inventario - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_inventario_general/importar.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-file-import"></i> Importación de Inventario</h2>
        <p>Registra muchos activos de una sola vez desde un archivo Excel</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Volver a Inventario</a>
        <a href="plantilla.php" class="btn btn-outline btn-sm"><i class="fas fa-file-excel"></i> Descargar plantilla</a>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>

<?php if ($pendiente): ?>
    <?php
    $resultado = validarFilasImportacion($conn, $pendiente['filas']);
    $total = count($pendiente['filas']);
    $validos = count($resultado['validas']);
    $invalidos = count($resultado['invalidas']);
    ?>
    <div class="glass-card" style="padding:24px;margin-bottom:24px;">
        <h3 style="font-weight:700;margin-bottom:4px;"><i class="fas fa-file-import" style="color:var(--primary);"></i> IMPORTACIÓN DE INVENTARIO</h3>
        <p style="color:var(--gray);font-size:0.85rem;margin-bottom:18px;">Archivo: <strong><?php echo htmlspecialchars($pendiente['archivo']); ?></strong></p>
        <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
            <div class="glass-card kpi-card" style="margin-bottom:0;">
                <div class="kpi-icon blue-gradient"><i class="fas fa-table"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo $total; ?></div>
                    <div class="kpi-label">Total de filas</div>
                </div>
            </div>
            <div class="glass-card kpi-card" style="margin-bottom:0;">
                <div class="kpi-icon green-gradient"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo $validos; ?></div>
                    <div class="kpi-label">Registros válidos</div>
                </div>
            </div>
            <div class="glass-card kpi-card" style="margin-bottom:0;">
                <div class="kpi-icon red-gradient"><i class="fas fa-times-circle"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo $invalidos; ?></div>
                    <div class="kpi-label">Registros con errores</div>
                </div>
            </div>
            <div class="glass-card kpi-card" style="margin-bottom:0;">
                <div class="kpi-icon yellow-gradient"><i class="fas fa-qrcode"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo $validos; ?></div>
                    <div class="kpi-label">Nuevos activos (con QR)</div>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
            <form method="POST" style="display:inline;">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="confirmar_importacion" value="1">
                <button type="submit" class="btn btn-primary" <?php echo $validos === 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-check"></i> Importar <?php echo $validos; ?> registros
                </button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar la importación? No se creará ningún registro.')">
                <?php echo campoCSRF(); ?>
                <input type="hidden" name="cancelar_importacion" value="1">
                <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Cancelar</button>
            </form>
        </div>
        <?php if ($invalidos > 0): ?>
        <p style="font-size:0.78rem;color:var(--orange);margin-top:12px;"><i class="fas fa-info-circle"></i> No se importarán las filas con errores. Solamente se crearán los <?php echo $validos; ?> registros válidos.</p>
        <?php endif; ?>
    </div>

    <div class="glass-card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 22px;border-bottom:1px solid rgba(255,255,255,0.06);">
            <strong><i class="fas fa-list"></i> Vista previa de filas</strong>
            <small style="color:var(--gray);" class="float-right"><?php echo ($total > 150) ? 'Mostrando las primeras 150 de ' . $total . ' filas.' : 'Mostrando todas las filas.'; ?></small>
        </div>
        <div style="overflow-x:auto;">
            <table class="premium-table" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Sede</th>
                        <th>Ubicación</th>
                        <th>Responsable</th>
                        <th>Estado</th>
                        <th>Serial</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filasMostradas = 0;
                    $filasPorId = [];
                    foreach ($pendiente['filas'] as $f) {
                        $filasPorId[$f['_fila_excel']] = $f;
                    }
                    // Se intercalan filas válidas e inválidas en el orden original del archivo
                    $filasConEstado = [];
                    foreach ($filasPorId as $nFila => $f) {
                        $filasConEstado[$nFila] = ['fila' => $f, 'ok' => true, 'errores' => []];
                    }
                    foreach ($resultado['invalidas'] as $inv) {
                        $filasConEstado[$inv['fila']['_fila_excel']] = ['fila' => $inv['fila'], 'ok' => false, 'errores' => $inv['errores']];
                    }
                    ksort($filasConEstado);
                    foreach ($filasConEstado as $nFila => $estado):
                        if ($filasMostradas >= 150) break;
                        $fila = $estado['fila'];
                        $filasMostradas++;
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo (int)$nFila; ?></td>
                        <td><?php echo htmlspecialchars($fila['nombre'] ?: '—'); ?></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($fila['tipo'] ?: '—'); ?></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($fila['sede'] ?: '—'); ?></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($fila['ubicacion'] ?: '—'); ?></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($fila['responsable'] ?: '—'); ?></td>
                        <td>
                            <?php if ($fila['estado']): ?>
                            <span class="badge badge-info"><?php echo htmlspecialchars($fila['estado']); ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($fila['numero_serie'] ?: '—'); ?></td>
                        <td style="max-width:300px;">
                            <?php if ($estado['ok']): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Válida</span>
                            <?php else: ?>
                            <?php foreach ($estado['errores'] as $err): ?>
                            <div style="font-size:0.75rem;color:#f87171;margin-bottom:2px;">❌ <?php echo htmlspecialchars($err); ?></div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>

<div class="glass-card" style="padding:24px;max-width:640px;">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px;">
        <i class="fas fa-file-excel" style="color:var(--green);font-size:2.4rem;"></i>
        <div>
            <h3 style="font-weight:700;margin-bottom:2px;">Subir archivo Excel</h3>
            <p style="color:var(--gray);font-size:0.84rem;margin:0;">Use la plantilla oficial del sistema. Máximo 5 MB y 2000 filas. Solo se importan filas válidas.</p>
        </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <?php echo campoCSRF(); ?>
        <input type="hidden" name="subir_archivo" value="1">
        <div class="form-group">
            <label>Archivo Excel (.xlsx / .xls)</label>
            <input type="file" class="form-control" name="archivo_excel" accept=".xlsx,.xls" required>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <a href="plantilla.php" class="btn btn-outline"><i class="fas fa-download"></i> Descargar plantilla</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Validar y previsualizar</button>
        </div>
    </form>
    <div class="form-separator" style="margin-top:20px;"><h4 style="font-size:13px;font-weight:600;margin:0;"><i class="fas fa-list-check"></i> Columnas de la plantilla</h4></div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
        <?php foreach (COLUMNAS_IMPORTACION as $etiqueta): ?>
        <span class="badge badge-info"><?php echo htmlspecialchars($etiqueta); ?></span>
        <?php endforeach; ?>
    </div>
    <p style="font-size:0.76rem;color:var(--gray);margin-top:12px;"><i class="fas fa-info-circle"></i> El código interno y el código QR se generan automáticamente con el sistema actual de MIC (Institución + Sede + Ubicación + consecutivo). No los escriba en el archivo.</p>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
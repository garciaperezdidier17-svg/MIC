<?php
require_once '../config/conexion.php';
require_once '../vendor/autoload.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!estaLogueado()) { header('Location: ../index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$tablasPermitidas = ['equipos','prestamos','usuarios','solicitudes','inventario_general','auditoria','movimientos_activos','cambios_responsables','cambios_ubicacion','importaciones','prestamos_activos','prestamos_vencidos','prestamos_devueltos','elementos_mas_prestados','prestamos_por_sede'];

$consultas = [
    'equipos' => [
        'titulo' => 'Inventario de Equipos',
        'sql' => "SELECT e.codigo_interno, e.nombre, e.descripcion_articulo as especificaciones, e.stock, e.stock_minimo, e.estado, s.nombre as sede, te.nombre_tipo as tipo, e.vr_comercial, e.vida_util FROM equipos e LEFT JOIN sedes s ON e.id_sede=s.id LEFT JOIN tipo_equipo te ON e.id_tipo=te.id WHERE e.activo=1",
        'headers' => ['Código','Nombre','Descripción','Stock','Stock Mínimo','Estado','Sede','Tipo','Valor Comercial','Vida Útil'],
        'widths' => [14,25,45,8,12,12,15,15,16,10]
    ],
    'prestamos' => [
        'titulo' => 'Préstamos Realizados',
        'sql' => "SELECT p.id, e.nombre as equipo, e.codigo_interno, u.nombre as estudiante, p.fecha_prestamo, p.hora_prestamo, p.fecha_devolucion_esperada, p.fecha_devolucion_real, p.estado FROM prestamos p LEFT JOIN equipos e ON p.id_equipo=e.id LEFT JOIN usuarios u ON p.id_estudiante=u.id ORDER BY p.fecha_prestamo DESC",
        'headers' => ['ID','Equipo','Código','Estudiante','Fecha Préstamo','Hora','Devolución Esperada','Devolución Real','Estado'],
        'widths' => [6,25,14,22,16,10,18,18,12]
    ],
    'usuarios' => [
        'titulo' => 'Usuarios del Sistema',
        'sql' => "SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.creado_en, u.ultimo_acceso FROM usuarios ORDER BY u.nombre",
        'headers' => ['ID','Nombre','Email','Rol','Activo','Creado','Último Acceso'],
        'widths' => [6,22,30,10,10,16,18]
    ],
    'solicitudes' => [
        'titulo' => 'Solicitudes de Equipos',
        'sql' => "SELECT s.id, e.nombre as equipo, u.nombre as solicitante, s.fecha_solicitud, s.motivo, s.estado FROM solicitudes s LEFT JOIN equipos e ON s.id_equipo=e.id LEFT JOIN usuarios u ON s.id_usuario=u.id ORDER BY s.fecha_solicitud DESC",
        'headers' => ['ID','Equipo','Solicitante','Fecha','Motivo','Estado'],
        'widths' => [6,25,22,16,30,12]
    ],
    'inventario_general' => [
        'titulo' => 'Inventario General',
        'sql' => "SELECT ig.id, ig.codigo_interno, ig.nombre, ig.categoria, ig.tipo, ig.marca, ig.modelo, ig.numero_serie, ig.procesador, ig.ram, ig.almacenamiento, ig.accesorios, ig.estado, ig.ubicacion, s.nombre as sede, ig.vr_comercial, ig.vida_util FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id WHERE ig.activo=1 ORDER BY ig.tipo, ig.nombre",
        'headers' => ['ID','Código','Nombre','Categoría','Tipo','Marca','Modelo','Serial','Procesador','RAM','Almacenamiento','Accesorios','Estado','Ubicación','Sede','Valor Comercial','Vida Útil'],
        'widths' => [5,10,16,12,12,10,10,10,10,8,10,15,10,14,12,12,8]
    ],
    'auditoria' => [
        'titulo' => 'Auditoría del Sistema',
        'sql' => "SELECT a.id, a.fecha, u.nombre as usuario, a.accion, a.modulo, a.entidad, a.entidad_id, a.descripcion, a.ip FROM auditoria a LEFT JOIN usuarios u ON a.usuario_id=u.id ORDER BY a.id DESC",
        'headers' => ['ID','Fecha','Usuario','Acción','Módulo','Entidad','ID Entidad','Descripción','IP'],
        'widths' => [6,18,18,18,14,12,10,50,15]
    ],
    'movimientos_activos' => [
        'titulo' => 'Movimientos de Activos',
        'sql' => "SELECT eh.id, eh.fecha, ig.codigo_interno, ig.nombre as activo, eh.tipo_evento, eh.descripcion, u.nombre as usuario FROM elemento_historial eh JOIN inventario_general ig ON ig.id=eh.elemento_id LEFT JOIN usuarios u ON eh.usuario_id=u.id ORDER BY eh.fecha DESC",
        'headers' => ['ID','Fecha','Código','Activo','Tipo Movimiento','Descripción','Usuario'],
        'widths' => [6,18,14,28,18,50,18]
    ],
    'cambios_responsables' => [
        'titulo' => 'Cambios de Responsables',
        'sql' => "SELECT eh.id, eh.fecha, ig.codigo_interno, ig.nombre as activo, eh.descripcion, eh.datos_anterior, eh.datos_nuevos, u.nombre as usuario, eh.observacion FROM elemento_historial eh JOIN inventario_general ig ON ig.id=eh.elemento_id LEFT JOIN usuarios u ON eh.usuario_id=u.id WHERE eh.tipo_evento='reasignacion' ORDER BY eh.fecha DESC",
        'headers' => ['ID','Fecha','Código','Activo','Descripción','Responsable Anterior','Responsable Nuevo','Usuario','Motivo'],
        'widths' => [6,16,14,26,26,26,26,16,22],
        'json_cols' => ['datos_anterior' => 'responsable', 'datos_nuevos' => 'responsable'],
    ],
    'cambios_ubicacion' => [
        'titulo' => 'Cambios de Ubicación',
        'sql' => "SELECT eh.id, eh.fecha, ig.codigo_interno, ig.nombre as activo, eh.tipo_evento, eh.descripcion, eh.datos_anterior, eh.datos_nuevos, u.nombre as usuario FROM elemento_historial eh JOIN inventario_general ig ON ig.id=eh.elemento_id LEFT JOIN usuarios u ON eh.usuario_id=u.id WHERE eh.tipo_evento IN ('cambio_ubicacion','cambio_sede') ORDER BY eh.fecha DESC",
        'headers' => ['ID','Fecha','Código','Activo','Tipo','Descripción','Antes','Después','Usuario'],
        'widths' => [6,16,14,26,16,26,26,26,16],
        'json_cols' => ['datos_anterior' => ['sede', 'ubicacion'], 'datos_nuevos' => ['sede', 'ubicacion']],
    ],
    'importaciones' => [
        'titulo' => 'Importaciones de Inventario',
        'sql' => "SELECT a.id, a.fecha, u.nombre as usuario, a.descripcion, a.datos_nuevos, a.ip FROM auditoria a LEFT JOIN usuarios u ON a.usuario_id=u.id WHERE a.accion='importar_inventario' ORDER BY a.id DESC",
        'headers' => ['ID','Fecha','Usuario','Descripción','Registros Válidos','Creados con Éxito','Archivo'],
        'widths' => [6,18,18,60,16,16,24],
        'json_cols' => ['datos_nuevos' => ['validos', 'creados', 'archivo']],
    ],
    'prestamos_activos' => [
        'titulo' => 'Préstamos Activos',
        'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado
                  FROM prestamos p LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                  WHERE p.estado='activo' ORDER BY p.fecha_prestamo DESC",
        'headers' => ['ID','Responsable','Sede','Fecha Préstamo','Devolución Esperada','Estado'],
        'widths' => [6,25,18,16,18,12],
    ],
    'prestamos_vencidos' => [
        'titulo' => 'Préstamos Vencidos',
        'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado
                  FROM prestamos p LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                  WHERE p.estado='vencido' ORDER BY p.fecha_devolucion_esperada ASC",
        'headers' => ['ID','Responsable','Sede','Fecha Préstamo','Devolución Esperada','Estado'],
        'widths' => [6,25,18,16,18,12],
    ],
    'prestamos_devueltos' => [
        'titulo' => 'Préstamos Devueltos',
        'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, p.fecha_prestamo, p.fecha_devolucion_esperada, p.fecha_devolucion_real, p.estado_devolucion
                  FROM prestamos p LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                  WHERE p.estado='devuelto' ORDER BY p.fecha_devolucion_real DESC",
        'headers' => ['ID','Responsable','Sede','Fecha Préstamo','Devolución Esperada','Devolución Real','Estado Devolución'],
        'widths' => [6,25,18,16,18,18,16],
    ],
    'elementos_mas_prestados' => [
        'titulo' => 'Elementos Más Prestados',
        'sql' => "SELECT ig.nombre, ig.tipo, ig.codigo_interno, COUNT(pe.id) as veces_prestado
                  FROM prestamo_elementos pe JOIN inventario_general ig ON pe.id_elemento=ig.id
                  GROUP BY ig.id ORDER BY veces_prestado DESC LIMIT 20",
        'headers' => ['Elemento','Tipo','Código','Veces Prestado'],
        'widths' => [28,20,14,16],
    ],
    'prestamos_por_sede' => [
        'titulo' => 'Préstamos por Sede',
        'sql' => "SELECT s.nombre as sede, COUNT(p.id) as total,
                         SUM(CASE WHEN p.estado='activo' THEN 1 ELSE 0 END) as activos,
                         SUM(CASE WHEN p.estado='vencido' THEN 1 ELSE 0 END) as vencidos,
                         SUM(CASE WHEN p.estado='devuelto' THEN 1 ELSE 0 END) as devueltos
                  FROM prestamos p LEFT JOIN sedes s ON p.id_sede=s.id
                  GROUP BY p.id_sede ORDER BY total DESC",
        'headers' => ['Sede','Total','Activos','Vencidos','Devueltos'],
        'widths' => [24,10,10,10,10],
    ],
];

/**
 * Extrae el valor legible de una columna para exportación.
 * Si la columna guarda JSON, devuelve los subvalores solicitados unidos.
 */
function valorExportacion($valor, $clavesJson) {
    if (!$clavesJson || $valor === null || $valor === '') {
        return $valor ?? '-';
    }
    $datos = json_decode($valor, true);
    if (!is_array($datos)) {
        return '-';
    }
    $partes = [];
    foreach ((array)$clavesJson as $k) {
        $v = $datos[$k] ?? null;
        if ($v !== null && $v !== '') {
            $partes[] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
        }
    }
    return $partes ? implode(' / ', $partes) : '-';
}

if (isset($_GET['excel']) && $_GET['excel'] == '1') {
    $tabla = $_GET['tabla'];
    if (!in_array($tabla, $tablasPermitidas)) { die('Tabla no permitida'); }
    registrarAuditoria($conn, 'exportar_informacion', 'reportes', 'tabla', null, 'Exportación a Excel: ' . $consultas[$tabla]['titulo'], null, ['formato' => 'excel', 'tabla' => $tabla]);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($consultas[$tabla]['titulo']);

    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A56DB']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
    ];
    $styleData = [
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
    ];

    foreach ($consultas[$tabla]['headers'] as $i => $h) {
        $col = chr(65 + $i);
        $sheet->setCellValue($col . '1', $h);
        $sheet->getColumnDimension($col)->setWidth($consultas[$tabla]['widths'][$i]);
        $sheet->getStyle($col . '1')->applyFromArray($styleHeader);
    }

    $stmt = $conn->query($consultas[$tabla]['sql']);
    $rowNum = 2;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $col = 'A';
        $jsonCols = $consultas[$tabla]['json_cols'] ?? [];
        foreach ($row as $k => $val) {
            $sheet->setCellValue($col . $rowNum, valorExportacion($val, $jsonCols[$k] ?? null));
            $col++;
        }
        $sheet->getStyle('A' . $rowNum . ':' . chr(64 + count($consultas[$tabla]['headers'])) . $rowNum)->applyFromArray($styleData);
        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="mic_' . $tabla . '_' . date('Y-m-d') . '.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (isset($_GET['pdf']) && $_GET['pdf'] == '1') {
    $tabla = $_GET['tabla'];
    if (!in_array($tabla, $tablasPermitidas)) { die('Tabla no permitida'); }
    registrarAuditoria($conn, 'exportar_informacion', 'reportes', 'tabla', null, 'Exportación a PDF: ' . $consultas[$tabla]['titulo'], null, ['formato' => 'pdf', 'tabla' => $tabla]);

    $stmt = $conn->query($consultas[$tabla]['sql']);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $titulo = $consultas[$tabla]['titulo'];
    $headers = $consultas[$tabla]['headers'];

    $html = '
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; font-size: 10pt; }
        .header-logo { text-align:center; margin-bottom:14pt; }
        .header-logo img { max-height:50px; }
        .header-logo h1 { font-size:18pt; color:#1a56db; margin-bottom:2pt; }
        .header-logo p { color:#64748b; font-size:9pt; }
        h2 { font-size:14pt; margin-bottom:2pt; color:#1a56db; }
        .sub { color:#64748b; font-size:8pt; margin-bottom:14pt; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th { background: #1a56db; color: #fff; padding: 6pt 5pt; text-align: left; font-weight: 600; }
        td { padding: 5pt; border-bottom: 0.5pt solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .footer { margin-top:14pt; font-size:7pt; color:#94a3b8; text-align:center; }
    </style>
    <div class="header-logo">';
    $logo = obtenerLogo();
    if ($logo) {
        $rutaLogo = __DIR__ . '/../' . $logo;
        if (file_exists($rutaLogo)) {
            $imgData = base64_encode(file_get_contents($rutaLogo));
            $ext = pathinfo($rutaLogo, PATHINFO_EXTENSION);
            $html .= '<img src="data:image/' . $ext . ';base64,' . $imgData . '" alt="MIC" style="max-height:50px;">';
        }
    }
    $html .= '<h1>MIC</h1>
        <p>Institución Educativa 20 de Julio</p>
    </div>
    <h2>' . $titulo . '</h2>
    <div class="sub">Generado: ' . date('d/m/Y H:i') . ' | Total: ' . count($datos) . ' registros</div>
    <table>
        <thead><tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . $h . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    $jsonCols = $consultas[$tabla]['json_cols'] ?? [];
    foreach ($datos as $row) {
        $html .= '<tr>';
        foreach ($row as $k => $val) {
            $html .= '<td>' . htmlspecialchars((string)valorExportacion($val, $jsonCols[$k] ?? null)) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>
    <div class="footer">Generado el ' . date('d/m/Y H:i:s') . ' &copy; MIC - Institución Educativa 20 de Julio</div>';

    $mpdf = new Mpdf(['tempDir' => sys_get_temp_dir() . '/mpdf', 'format' => 'A4-L']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('mic_' . $tabla . '_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

$pageTitle = 'Reportes - MIC';
require_once '../includes/head.php';
?>
<style>
.btn-export {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-decoration: none;
    border: none;
    letter-spacing: 0.01em;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}
.btn-export::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.12), transparent);
    pointer-events: none;
    border-radius: 10px;
}
.btn-export:hover {
    transform: translateY(-3px);
}
.btn-export:active {
    transform: translateY(0);
}
.btn-xlsx {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
    box-shadow: 0 4px 14px rgba(34,197,94,0.35);
}
.btn-xlsx:hover {
    box-shadow: 0 8px 28px rgba(34,197,94,0.45);
    color: #fff;
}
.btn-pdf {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    box-shadow: 0 4px 14px rgba(239,68,68,0.35);
}
.btn-pdf:hover {
    box-shadow: 0 8px 28px rgba(239,68,68,0.45);
    color: #fff;
}
.report-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 14px;
}
.report-actions .btn-export {
    flex: 1;
    justify-content: center;
    min-width: 80px;
}
</style>
</head>
<?php
$paginaActual = '../modulo_reportes/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-chart-bar"></i> Reportes</h2>
        <p>Exporta los datos del sistema a CSV, Excel o PDF</p>
    </div>
</div>

<div class="reports-grid">
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#14b8a6,#0d9488);">
            <i class="fas fa-warehouse"></i>
        </div>
        <h3>Inventario General</h3>
        <p>Todos los activos: equipos, mobiliario, enseres y más</p>
        <span class="report-badge"><?= contarRegistros('inventario_general') ?> registros</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=inventario_general" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=inventario_general" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
            <i class="fas fa-history"></i>
        </div>
        <h3>Auditoría del Sistema</h3>
        <p>Acciones importantes registradas por los usuarios</p>
        <span class="report-badge"><?= (int)$conn->query("SELECT COUNT(*) FROM auditoria")->fetchColumn() ?> registros</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=auditoria" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=auditoria" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <h3>Movimientos de Activos</h3>
        <p>Historial completo de eventos por activo</p>
        <span class="report-badge"><?= (int)$conn->query("SELECT COUNT(*) FROM elemento_historial")->fetchColumn() ?> movimientos</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=movimientos_activos" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=movimientos_activos" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
            <i class="fas fa-user-check"></i>
        </div>
        <h3>Cambios de Responsables</h3>
        <p>Reasignaciones de activos entre profesores</p>
        <span class="report-badge"><?= (int)$conn->query("SELECT COUNT(*) FROM elemento_historial WHERE tipo_evento='reasignacion'")->fetchColumn() ?> cambios</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=cambios_responsables" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=cambios_responsables" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#14b8a6,#0f766e);">
            <i class="fas fa-map-marker-alt"></i>
        </div>
        <h3>Cambios de Ubicación</h3>
        <p>Movimientos de activos entre sedes y ubicaciones</p>
        <span class="report-badge"><?= (int)$conn->query("SELECT COUNT(*) FROM elemento_historial WHERE tipo_evento IN ('cambio_ubicacion','cambio_sede')")->fetchColumn() ?> cambios</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=cambios_ubicacion" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=cambios_ubicacion" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <i class="fas fa-file-import"></i>
        </div>
        <h3>Importaciones de Inventario</h3>
        <p>Importaciones masivas realizadas desde Excel</p>
        <span class="report-badge"><?= (int)$conn->query("SELECT COUNT(*) FROM auditoria WHERE accion='importar_inventario'")->fetchColumn() ?> importaciones</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=importaciones" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=importaciones" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
            <i class="fas fa-handshake"></i>
        </div>
        <h3>Préstamos</h3>
        <p>Historial completo de préstamos realizados</p>
        <span class="report-badge"><?= contarRegistros('prestamos') ?> registros</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=prestamos" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=prestamos" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#10b981,#059669);">
            <i class="fas fa-users"></i>
        </div>
        <h3>Usuarios</h3>
        <p>Lista completa de usuarios del sistema</p>
        <span class="report-badge"><?= contarRegistros('usuarios') ?> usuarios</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=usuarios" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=usuarios" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
    <div class="report-card">
        <div class="report-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <h3>Solicitudes</h3>
        <p>Todas las solicitudes de equipos</p>
        <span class="report-badge"><?= contarRegistros('solicitudes') ?> solicitudes</span>
        <div class="report-actions">
            <a href="?excel=1&tabla=solicitudes" class="btn-export btn-xlsx"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="?pdf=1&tabla=solicitudes" class="btn-export btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
</div>

<div class="glass-card" style="margin-top:24px;padding:24px;">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <i class="fas fa-info-circle" style="color:var(--primary);font-size:2rem;"></i>
        <div style="flex:1;">
            <p><strong>Excel (.xlsx)</strong> — archivo nativo de Excel con formato profesional</p>
            <p><strong>PDF</strong> — generado con mPDF, descarga directa sin diálogo de impresión</p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

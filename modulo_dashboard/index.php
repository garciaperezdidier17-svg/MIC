<?php
require_once '../config/conexion.php';
require_once __DIR__ . '/helpers_alertas.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

$usuario = obtenerUsuarioActual();

$filtros = [
    'sede' => isset($_GET['sede']) ? (int)$_GET['sede'] : 0,
    'categoria' => $_GET['categoria'] ?? '',
    'tipo' => $_GET['tipo'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'responsable' => isset($_GET['responsable']) ? (int)$_GET['responsable'] : 0,
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
];
[$fWhere, $fParams] = filtrosInventario(array_filter($filtros));

$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1) as total_registros,
    (SELECT COUNT(DISTINCT tipo) FROM inventario_general WHERE activo=1) as total_tipos,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado IN ('bueno','nuevo')) as disponibles,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado='regular') as en_reparacion,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado='malo') as danados,
    (SELECT COUNT(DISTINCT ubicacion) FROM inventario_general WHERE activo=1 AND ubicacion IS NOT NULL AND ubicacion != '') as total_ubicaciones,
    (SELECT COUNT(DISTINCT categoria) FROM inventario_general WHERE activo=1 AND categoria IS NOT NULL AND categoria != '') as total_categorias,
    (SELECT COALESCE(SUM(vr_comercial), 0) FROM inventario_general WHERE activo=1) as valor_comercial,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND MONTH(creado_en)=MONTH(CURDATE()) AND YEAR(creado_en)=YEAR(CURDATE())) as registros_mes,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado='bueno') as estado_bueno,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado='regular') as estado_regular,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado='malo') as estado_malo,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND estado='nuevo') as estado_nuevo
")->fetch(PDO::FETCH_ASSOC);

$stmtV = $conn->prepare("SELECT COALESCE(SUM(ig.valor_compra), 0) FROM inventario_general ig WHERE $fWhere");
$stmtV->execute($fParams);
$valorTotalFiltrado = (float)$stmtV->fetchColumn();
$stmtC = $conn->prepare("SELECT COALESCE(SUM(ig.vr_comercial), 0) FROM inventario_general ig WHERE $fWhere");
$stmtC->execute($fParams);
$valorComercialFiltrado = (float)$stmtC->fetchColumn();

$stmtVS = $conn->prepare("SELECT s.nombre, COALESCE(SUM(ig.valor_compra), 0) as valor, COUNT(ig.id) as total
                          FROM sedes s LEFT JOIN inventario_general ig ON ig.id_sede=s.id AND $fWhere
                          GROUP BY s.id, s.nombre ORDER BY valor DESC");
$stmtVS->execute($fParams);
$valorPorSede = $stmtVS->fetchAll(PDO::FETCH_ASSOC);

$stmtVC = $conn->prepare("SELECT ig.categoria, COALESCE(SUM(ig.valor_compra), 0) as valor, COUNT(*) as total
                          FROM inventario_general ig WHERE $fWhere AND ig.categoria IS NOT NULL AND ig.categoria != ''
                          GROUP BY ig.categoria ORDER BY valor DESC");
$stmtVC->execute($fParams);
$valorPorCategoria = $stmtVC->fetchAll(PDO::FETCH_ASSOC);

$stmtVE = $conn->prepare("SELECT ig.estado, COALESCE(SUM(ig.valor_compra), 0) as valor, COUNT(*) as total
                          FROM inventario_general ig WHERE $fWhere GROUP BY ig.estado ORDER BY valor DESC");
$stmtVE->execute($fParams);
$valorPorEstado = $stmtVE->fetchAll(PDO::FETCH_ASSOC);

$sedes = $conn->query("SELECT s.nombre, COUNT(ig.id) as total
                       FROM sedes s
                       LEFT JOIN inventario_general ig ON s.id = ig.id_sede AND ig.activo=1
                       GROUP BY s.id, s.nombre")->fetchAll(PDO::FETCH_ASSOC);

$igPorTipo = $conn->query("SELECT tipo, COUNT(*) as registros FROM inventario_general WHERE activo=1 GROUP BY tipo ORDER BY registros DESC")->fetchAll(PDO::FETCH_ASSOC);
$igRecientes = $conn->query("SELECT id, nombre, tipo, estado FROM inventario_general WHERE activo=1 ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$igPorCategoria = $conn->query("SELECT categoria, COUNT(*) as registros FROM inventario_general WHERE activo=1 AND categoria IS NOT NULL AND categoria != '' GROUP BY categoria ORDER BY registros DESC")->fetchAll(PDO::FETCH_ASSOC);

$igPorUbicacion = $conn->query("SELECT ubicacion, COUNT(*) as registros FROM inventario_general WHERE activo=1 AND ubicacion IS NOT NULL AND ubicacion != '' GROUP BY ubicacion ORDER BY registros DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$vidaUtil = $conn->query("SELECT
    SUM(CASE WHEN vida_util IS NULL OR vida_util < 1 THEN 1 ELSE 0 END) as menos_1,
    SUM(CASE WHEN vida_util >= 1 AND vida_util <= 3 THEN 1 ELSE 0 END) as de_1_a_3,
    SUM(CASE WHEN vida_util > 3 AND vida_util <= 5 THEN 1 ELSE 0 END) as de_3_a_5,
    SUM(CASE WHEN vida_util > 5 THEN 1 ELSE 0 END) as mas_5
FROM inventario_general WHERE activo=1")->fetch(PDO::FETCH_ASSOC);

$ultimoRegistro = $conn->query("SELECT nombre, codigo_interno, creado_en FROM inventario_general WHERE activo=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$vidaUtilVencida = $conn->query("SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND vida_util IS NOT NULL AND DATE_ADD(creado_en, INTERVAL vida_util YEAR) <= DATE_ADD(CURDATE(), INTERVAL 1 YEAR)")->fetchColumn();

$alertas = calcularAlertas($conn);

$statsToma = $conn->query("SELECT
    (SELECT COUNT(*) FROM tomas_fisicas) as total_tomas,
    (SELECT COALESCE(SUM(encontrados), 0) FROM tomas_fisicas WHERE estado='finalizada') as total_encontrados,
    (SELECT COALESCE(SUM(no_encontrados), 0) FROM tomas_fisicas WHERE estado='finalizada') as total_no_encontrados,
    (SELECT COALESCE(SUM(con_novedades), 0) FROM tomas_fisicas WHERE estado='finalizada') as total_novedades,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND situacion='en_mantenimiento') as en_mantenimiento,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND situacion='en_reparacion') as en_reparacion,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND situacion='dado_de_baja') as dados_de_baja,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND situacion='no_encontrado') as no_encontrados,
    (SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND situacion='en_investigacion') as en_investigacion
")->fetch(PDO::FETCH_ASSOC);

$ultimaToma = $conn->query(
    "SELECT t.*, s.nombre AS sede_nombre FROM tomas_fisicas t
     LEFT JOIN sedes s ON t.sede_id=s.id
     ORDER BY t.id DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$ultimaImportacion = $conn->query(
    "SELECT a.id, a.descripcion, a.fecha, u.nombre AS usuario_nombre
     FROM auditoria a LEFT JOIN usuarios u ON a.usuario_id = u.id
     WHERE a.accion = 'importar_inventario'
     ORDER BY a.id DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$ultimasActividades = $conn->query(
    "SELECT a.accion, a.modulo, a.descripcion, a.fecha, u.nombre AS usuario_nombre
     FROM auditoria a LEFT JOIN usuarios u ON a.usuario_id = u.id
     ORDER BY a.id DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$pageTitle = 'Dashboard - MIC';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_dashboard/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.kpi-grid-extended {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.kpi-grid-extended .glass-card {
    margin-bottom: 0;
}
.alerts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.alert-card {
    padding: 18px 20px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.alert-card .alert-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.alert-card .alert-body h4 {
    font-size: 0.88rem;
    font-weight: 600;
    margin-bottom: 2px;
}
.alert-card .alert-body p {
    font-size: 0.78rem;
    color: var(--gray);
    margin: 0;
}
</style>

<div class="welcome-section">
    <div class="welcome-content">
        <div class="welcome-badge"><i class="fas fa-shield-alt"></i> Sistema Activo</div>
        <h1>¡Bienvenido, <?php echo explode(' ', $usuario['nombre'])[0]; ?>!</h1>
        <p>Panel de control del inventario tecnológico</p>
    </div>
    <div class="welcome-stats">
        <div class="stat-card-mini"><i class="fas fa-calendar-day"></i> <span id="fechaActual"></span></div>
        <div class="stat-card-mini"><i class="fas fa-shield-alt"></i> Seguro</div>
        <div class="stat-card-mini"><i class="fas fa-wifi"></i> En línea</div>
    </div>
</div>

<div class="kpi-grid">
    <div class="glass-card kpi-card animate-fade-up">
        <div class="kpi-icon blue-gradient"><i class="fas fa-warehouse"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['total_registros']; ?></div>
            <div class="kpi-label">Total Registros</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1">
        <div class="kpi-icon yellow-gradient"><i class="fas fa-tags"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['total_tipos']; ?></div>
            <div class="kpi-label">Tipos Distintos</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2">
        <div class="kpi-icon green-gradient"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['estado_bueno'] + $stats['estado_nuevo']; ?></div>
            <div class="kpi-label">En Buen Estado</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-3">
        <div class="kpi-icon purple-gradient"><i class="fas fa-map-marked-alt"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo count($sedes); ?></div>
            <div class="kpi-label">Sedes</div>
        </div>
    </div>
</div>

<div class="kpi-grid-extended">
    <div class="glass-card kpi-card animate-fade-up">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#22c55e,#16a34a);"><i class="fas fa-thumbs-up"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['disponibles']; ?></div>
            <div class="kpi-label">Activos Disponibles</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-tools"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['en_reparacion']; ?></div>
            <div class="kpi-label">En Reparación</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['danados']; ?></div>
            <div class="kpi-label">Activos Dañados</div>
        </div>
    </div>
</div>

<div class="section-header">
    <h3><i class="fas fa-dollar-sign"></i> Valorización del Inventario</h3>
    <p>Cálculo automático desde la base de datos (valor de compra)</p>
</div>

<div class="glass-card kpi-card animate-fade-up" style="margin-bottom:16px;padding:22px 28px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
    <div class="kpi-icon" style="background:linear-gradient(135deg,#14b8a6,#0d9488);width:56px;height:56px;font-size:1.4rem;flex-shrink:0;"><i class="fas fa-money-bill-wave"></i></div>
    <div class="kpi-content" style="min-width:0;">
        <div class="kpi-value" style="font-size:1.8rem;">$<?php echo number_format($valorTotalFiltrado, 0); ?></div>
        <div class="kpi-label" style="font-size:0.9rem;">Valor total del inventario (compra)</div>
    </div>
    <div class="kpi-content" style="min-width:0;border-left:1px solid rgba(255,255,255,0.08);padding-left:20px;">
        <div class="kpi-value" style="font-size:1.2rem;">$<?php echo number_format($valorComercialFiltrado, 0); ?></div>
        <div class="kpi-label" style="font-size:0.8rem;">Valor comercial actual</div>
    </div>
</div>

<div class="kpi-grid-extended">
    <div class="glass-card" style="padding:18px 20px;">
        <div style="font-size:0.75rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-school" style="color:#3b82f6;"></i> Valor por sede</div>
        <?php foreach ($valorPorSede as $vs): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.85rem;">
            <span><?php echo htmlspecialchars($vs['nombre']); ?></span>
            <span style="font-weight:600;white-space:nowrap;">$<?php echo number_format($vs['valor'], 0); ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$valorPorSede): ?><p style="font-size:0.8rem;color:var(--gray);margin:0;">Sin datos</p><?php endif; ?>
    </div>
    <div class="glass-card" style="padding:18px 20px;">
        <div style="font-size:0.75rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-tags" style="color:#8b5cf6;"></i> Valor por categoría</div>
        <?php foreach ($valorPorCategoria as $vc): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.85rem;">
            <span><?php echo htmlspecialchars($vc['categoria']); ?> <span style="color:var(--gray);font-size:0.72rem;">(<?php echo (int)$vc['total']; ?>)</span></span>
            <span style="font-weight:600;white-space:nowrap;">$<?php echo number_format($vc['valor'], 0); ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$valorPorCategoria): ?><p style="font-size:0.8rem;color:var(--gray);margin:0;">Sin datos</p><?php endif; ?>
    </div>
    <div class="glass-card" style="padding:18px 20px;">
        <div style="font-size:0.75rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-heartbeat" style="color:#10b981;"></i> Valor por estado</div>
        <?php
        $coloresEstado = ['bueno' => '#22c55e', 'nuevo' => '#3b82f6', 'regular' => '#f59e0b', 'malo' => '#ef4444'];
        foreach ($valorPorEstado as $ve): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.85rem;align-items:center;">
            <span><i class="fas fa-circle" style="font-size:0.5rem;color:<?php echo $coloresEstado[$ve['estado']] ?? '#6b7280'; ?>;"></i> <?php echo ucfirst($ve['estado']); ?> <span style="color:var(--gray);font-size:0.72rem;">(<?php echo (int)$ve['total']; ?>)</span></span>
            <span style="font-weight:600;white-space:nowrap;">$<?php echo number_format($ve['valor'], 0); ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$valorPorEstado): ?><p style="font-size:0.8rem;color:var(--gray);margin:0;">Sin datos</p><?php endif; ?>
    </div>
</div>

<div class="section-header">
    <h3><i class="fas fa-bell"></i> Alertas del Sistema</h3>
    <p>Estado actual del inventario</p>
</div>
<div class="alerts-grid">
    <?php foreach ($alertas as $alerta): ?>
    <?php
    $colores = [
        'critica' => ['borde' => '#ef4444', 'bg' => 'rgba(239,68,68,0.15)', 'color' => '#dc2626'],
        'advertencia' => ['borde' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)', 'color' => '#d97706'],
        'informacion' => ['borde' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.15)', 'color' => '#2563eb'],
    ];
    $c = $colores[$alerta['prioridad']] ?? $colores['informacion'];
    ?>
    <div class="glass-card alert-card" style="border-left:4px solid <?php echo $c['borde']; ?>;">
        <div class="alert-icon" style="background:<?php echo $c['bg']; ?>;color:<?php echo $c['color']; ?>;"><i class="<?php echo $alerta['icono']; ?>"></i></div>
        <div class="alert-body" style="flex:1;min-width:0;">
            <h4><?php echo $alerta['cantidad']; ?> — <?php echo htmlspecialchars($alerta['titulo']); ?></h4>
            <p><?php echo htmlspecialchars($alerta['descripcion']); ?></p>
            <a href="alertas.php#<?php echo $alerta['clave']; ?>" style="font-size:0.75rem;color:<?php echo $c['color']; ?>;font-weight:600;">Ver elementos <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$alertas): ?>
    <div class="glass-card alert-card" style="border-left:4px solid #22c55e;">
        <div class="alert-icon" style="background:rgba(34,197,94,0.15);color:#16a34a;"><i class="fas fa-check-circle"></i></div>
        <div class="alert-body">
            <h4>Sin alertas</h4>
            <p>Actualmente no existen alertas en el inventario.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="section-header">
    <h3><i class="fas fa-clipboard-check"></i> Toma Física e Inspección de Activos</h3>
    <p>Resumen de verificaciones físicas y situaciones de los activos</p>
</div>
<div class="kpi-grid-extended">
    <div class="glass-card kpi-card animate-fade-up">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#22c55e,#16a34a);"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo (int)$statsToma['total_encontrados']; ?></div>
            <div class="kpi-label">Activos Encontrados</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-1">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-eye-slash"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo (int)$statsToma['total_no_encontrados'] + (int)$statsToma['no_encontrados']; ?></div>
            <div class="kpi-label">No Encontrados</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-2">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-sticky-note"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo (int)$statsToma['total_novedades']; ?></div>
            <div class="kpi-label">Con Novedades</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-3">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);"><i class="fas fa-tools"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo (int)$statsToma['en_mantenimiento'] + (int)$statsToma['en_reparacion']; ?></div>
            <div class="kpi-label">En Mantenimiento / Reparación</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-4">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#6b7280,#4b5563);"><i class="fas fa-ban"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo (int)$statsToma['dados_de_baja']; ?></div>
            <div class="kpi-label">Dados de Baja</div>
        </div>
    </div>
    <div class="glass-card kpi-card animate-fade-up delay-5">
        <div class="kpi-icon" style="background:linear-gradient(135deg,#14b8a6,#0d9488);"><i class="fas fa-history"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo (int)$statsToma['total_tomas']; ?></div>
            <div class="kpi-label">Tomas Físicas Realizadas</div>
        </div>
    </div>
</div>
<?php if ($ultimaToma): ?>
<div class="glass-card" style="padding:16px 20px;margin-bottom:16px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
    <div class="kpi-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);"><i class="fas fa-clipboard-check"></i></div>
    <div style="flex:1;min-width:200px;">
        <div class="kpi-label" style="font-size:0.75rem;">ÚLTIMA TOMA FÍSICA</div>
        <div class="kpi-value" style="font-size:1.1rem;"><?php echo htmlspecialchars($ultimaToma['sede_nombre'] ?? 'Sede'); ?> — <?php echo htmlspecialchars($ultimaToma['ubicacion']); ?></div>
        <div style="font-size:0.8rem;color:var(--gray);">
            <?php echo date('d/m/Y H:i', strtotime($ultimaToma['fecha_toma'])); ?> ·
            <?php echo (int)$ultimaToma['total_esperados']; ?> esperados ·
            <?php echo (int)$ultimaToma['encontrados']; ?> encontrados ·
            <?php echo (int)$ultimaToma['no_encontrados']; ?> no encontrados
        </div>
    </div>
    <a href="../modulo_toma_fisica/ver_toma.php?id=<?php echo (int)$ultimaToma['id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Ver detalle</a>
    <a href="../modulo_toma_fisica/index.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nueva toma</a>
</div>
<?php endif; ?>

<div class="section-header">
    <h3><i class="fas fa-history"></i> Actividad Reciente</h3>
    <p>Últimas operaciones registradas en el sistema</p>
</div>
<div class="kpi-grid-extended">
    <div class="glass-card" style="padding:18px 20px;">
        <div style="font-size:0.75rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-file-import" style="color:#10b981;"></i> Última importación Excel</div>
        <?php if ($ultimaImportacion): ?>
            <div class="kpi-value" style="font-size:1.05rem;"><?php echo htmlspecialchars($ultimaImportacion['usuario_nombre'] ?? 'Usuario'); ?></div>
            <div style="font-size:0.8rem;color:var(--gray);margin-top:4px;">
                <?php echo htmlspecialchars(mb_strimwidth((string)$ultimaImportacion['descripcion'], 0, 90, '…')); ?>
            </div>
            <div style="font-size:0.75rem;color:var(--gray);margin-top:6px;">
                <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($ultimaImportacion['fecha'])); ?>
            </div>
            <a href="../modulo_auditoria/detalle.php?id=<?php echo (int)$ultimaImportacion['id']; ?>" class="btn btn-outline btn-sm" style="margin-top:10px;"><i class="fas fa-eye"></i> Ver detalle</a>
        <?php else: ?>
            <p style="font-size:0.82rem;color:var(--gray);margin:0;">Aún no se han realizado importaciones.</p>
            <a href="../modulo_inventario_general/importar.php" class="btn btn-primary btn-sm" style="margin-top:10px;"><i class="fas fa-file-import"></i> Importar ahora</a>
        <?php endif; ?>
    </div>
    <div class="glass-card" style="padding:18px 20px;">
        <div style="font-size:0.75rem;color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-shield-alt" style="color:#3b82f6;"></i> Última actividad del sistema</div>
        <?php if ($ultimasActividades): ?>
            <?php foreach ($ultimasActividades as $act): ?>
            <div style="display:flex;gap:10px;align-items:flex-start;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                <span class="badge badge-info" style="flex-shrink:0;font-size:0.65rem;"><?php echo htmlspecialchars(etiquetaAccionAuditoria($act['accion'])); ?></span>
                <div style="min-width:0;line-height:1.4;">
                    <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($act['descripcion'] ?: '—'); ?></div>
                    <small style="color:var(--gray);"><?php echo htmlspecialchars($act['usuario_nombre'] ?? '—'); ?> · <?php echo date('d/m/Y H:i', strtotime($act['fecha'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
            <a href="../modulo_auditoria/index.php" class="btn btn-outline btn-sm" style="margin-top:10px;"><i class="fas fa-list"></i> Ver auditoría completa</a>
        <?php else: ?>
            <p style="font-size:0.82rem;color:var(--gray);margin:0;">Sin actividad registrada por el momento.</p>
        <?php endif; ?>
    </div>
</div>

<div class="section-header">
    <h3><i class="fas fa-chart-pie"></i> Analíticas</h3>
    <p>Vista general del sistema</p>
</div>
<div class="charts-grid">
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie"></i> Estado del Inventario</h3>
            <p>Distribución por estado</p>
        </div>
        <div class="chart-container"><canvas id="estadoChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Inventario por Sede</h3>
            <p>Cantidad de registros por sede</p>
        </div>
        <div class="chart-container"><canvas id="sedeChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Inventario por Tipo</h3>
            <p>Distribución por tipo de activo</p>
        </div>
        <div class="chart-container"><canvas id="igTipoChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Inventario por Categoría</h3>
            <p>Cantidad por categoría</p>
        </div>
        <div class="chart-container"><canvas id="categoriaChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Activos por Ubicación</h3>
            <p>Top ubicaciones con más activos</p>
        </div>
        <div class="chart-container"><canvas id="ubicacionChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Vida Útil del Inventario</h3>
            <p>Clasificación por años de vida útil</p>
        </div>
        <div class="chart-container"><canvas id="vidaUtilChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-dollar-sign"></i> Valor del inventario por sede</h3>
            <p>Suma de valor de compra por sede</p>
        </div>
        <div class="chart-container"><canvas id="valorSedeChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-dollar-sign"></i> Valor del inventario por categoría</h3>
            <p>Suma de valor de compra por categoría</p>
        </div>
        <div class="chart-container"><canvas id="valorCategoriaChart"></canvas></div>
    </div>
    <div class="glass-card chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-dollar-sign"></i> Valor del inventario por estado</h3>
            <p>Suma de valor de compra por estado</p>
        </div>
        <div class="chart-container"><canvas id="valorEstadoChart"></canvas></div>
    </div>
</div>

<div class="glass-card" style="margin-top:24px;">
    <div class="card-header">
        <h3><i class="fas fa-clock"></i> Últimos Elementos Agregados</h3>
        <a href="../modulo_inventario_general/index.php" class="btn btn-outline btn-sm">Ver todo</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($igRecientes as $ig): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($ig['nombre']); ?></strong></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($ig['tipo']); ?></span></td>
                    <td><span class="badge <?php echo $ig['estado'] == 'bueno' ? 'badge-success' : ($ig['estado'] == 'regular' ? 'badge-warning' : ($ig['estado'] == 'nuevo' ? 'badge-info' : 'badge-danger')); ?>"><?php echo ucfirst($ig['estado']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="glass-card">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="../modulo_inventario_general/index.php" class="quick-action-btn">
                <div class="quick-action-icon blue-gradient"><i class="fas fa-warehouse"></i></div>
                <div>
                    <strong>Ver Inventario</strong>
                    <small>Gestionar activos</small>
                </div>
            </a>
            <a href="../modulo_prestamos/solicitudes.php" class="quick-action-btn">
                <div class="quick-action-icon green-gradient"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <strong>Solicitudes</strong>
                    <small>Revisar peticiones</small>
                </div>
            </a>
            <a href="../modulo_prestamos/prestamos.php" class="quick-action-btn">
                <div class="quick-action-icon yellow-gradient"><i class="fas fa-handshake"></i></div>
                <div>
                    <strong>Préstamos</strong>
                    <small>Control de préstamos</small>
                </div>
            </a>
            <a href="../modulo_reportes/index.php" class="quick-action-btn">
                <div class="quick-action-icon red-gradient"><i class="fas fa-chart-bar"></i></div>
                <div>
                    <strong>Reportes</strong>
                    <small>Exportar datos</small>
                </div>
            </a>
            <a href="../modulo_dashboard/manual_usuario.php" class="quick-action-btn" style="opacity:0.55;">
                <div class="quick-action-icon" style="background:linear-gradient(135deg,#6b7280,#9ca3af);"><i class="fas fa-book"></i></div>
                <div>
                    <strong>Manual de Usuario</strong>
                    <small>Guía del sistema</small>
                </div>
            </a>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
const hoy = new Date();
const opciones = { weekday:"long", year:"numeric", month:"long", day:"numeric" };
const fechaEl = document.getElementById("fechaActual");
if(fechaEl) fechaEl.textContent = hoy.toLocaleDateString("es-ES", opciones);

var coloresBase = ["#10b981","#f59e0b","#ef4444","#3b82f6","#8b5cf6","#14b8a6","#ec4899","#f97316","#6366f1","#22c55e","#eab308","#a855f7","#06b6d4","#84cc16"];

const ctxEstado = document.getElementById("estadoChart");
if(ctxEstado) {
    new Chart(ctxEstado, {
        type:"doughnut",
        data: {
            labels:["Bueno","Regular","Malo","Nuevo"],
            datasets:[{
                data:['.$stats['estado_bueno'].','.$stats['estado_regular'].','.$stats['estado_malo'].','.$stats['estado_nuevo'].'],
                backgroundColor:["#10b981","#f59e0b","#ef4444","#3b82f6"],
                borderWidth:0, hoverOffset:15
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{position:"bottom",labels:{font:{size:12}}},
                tooltip:{callbacks:{label:function(ctx){return ctx.label+": "+ctx.raw;}}}
            },
            cutout:"60%"
        }
    });
}

const ctxSede = document.getElementById("sedeChart");
if(ctxSede) {
    new Chart(ctxSede, {
        type:"bar",
        data: {
            labels:'.json_encode(array_column($sedes, 'nombre')).',
            datasets:[{
                label:"Registros por Sede",
                data:'.json_encode(array_column($sedes, 'total')).',
                backgroundColor:"rgba(59, 130, 246, 0.7)",
                borderRadius:10, barPercentage:0.6
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:"top"}},
            scales:{y:{beginAtZero:true,title:{display:true,text:"Cantidad"}}}
        }
    });
}

const ctxIgTipo = document.getElementById("igTipoChart");
if(ctxIgTipo) {
    const igTipos = '.json_encode($igPorTipo).';
    new Chart(ctxIgTipo, {
        type:"bar",
        data: {
            labels:igTipos.map(i=>i.tipo),
            datasets:[{label:"Registros",data:igTipos.map(i=>i.registros),backgroundColor:"rgba(139,92,246,0.7)",borderRadius:8}]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            indexAxis:"y",
            plugins:{legend:{position:"top"}},
            scales:{x:{title:{display:true,text:"Cantidad"},beginAtZero:true}}
        }
    });
}

const ctxCategoria = document.getElementById("categoriaChart");
if(ctxCategoria) {
    const catData = '.json_encode($igPorCategoria).';
    new Chart(ctxCategoria, {
        type:"bar",
        data: {
            labels:catData.map(i=>i.categoria),
            datasets:[{label:"Registros",data:catData.map(i=>i.registros),backgroundColor:coloresBase.slice(0,catData.length),borderRadius:8}]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{position:"top"},
                tooltip:{callbacks:{label:function(ctx){return ctx.label+": "+ctx.raw+" registros";}}}
            },
            scales:{y:{beginAtZero:true,title:{display:true,text:"Cantidad"}}}
        }
    });
}

const ctxUbicacion = document.getElementById("ubicacionChart");
if(ctxUbicacion) {
    const ubiData = '.json_encode($igPorUbicacion).';
    new Chart(ctxUbicacion, {
        type:"bar",
        data: {
            labels:ubiData.map(i=>i.ubicacion),
            datasets:[{label:"Activos",data:ubiData.map(i=>i.registros),backgroundColor:"rgba(20,184,166,0.7)",borderRadius:8}]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            indexAxis:"y",
            plugins:{legend:{position:"top"}},
            scales:{x:{beginAtZero:true,title:{display:true,text:"Cantidad"}}}
        }
    });
}

const ctxVidaUtil = document.getElementById("vidaUtilChart");
if(ctxVidaUtil) {
    new Chart(ctxVidaUtil, {
        type:"bar",
        data: {
            labels:["Menos de 1 año","1 a 3 años","3 a 5 años","Más de 5 años"],
            datasets:[{
                label:"Activos",
                data:['.$vidaUtil['menos_1'].','.$vidaUtil['de_1_a_3'].','.$vidaUtil['de_3_a_5'].','.$vidaUtil['mas_5'].'],
                backgroundColor:["#ef4444","#f59e0b","#3b82f6","#10b981"],
                borderRadius:8, barPercentage:0.6
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:"top"}},
            scales:{y:{beginAtZero:true,title:{display:true,text:"Cantidad"}}}
        }
    });
}

const valorSedeData = '.json_encode(array_map(function($r){ return ['nombre'=>$r['nombre'],'valor'=>(float)$r['valor']]; }, $valorPorSede)).';
const valorCatData = '.json_encode(array_map(function($r){ return ['categoria'=>$r['categoria'],'valor'=>(float)$r['valor']]; }, $valorPorCategoria)).';
const valorEstData = '.json_encode(array_map(function($r){ return ['estado'=>$r['estado'],'valor'=>(float)$r['valor']]; }, $valorPorEstado)).';
const fmtValor = function (v) { return "$" + Number(v).toLocaleString("es-CO"); };

const ctxValorSede = document.getElementById("valorSedeChart");
if(ctxValorSede) {
    new Chart(ctxValorSede, {
        type:"bar",
        data: {
            labels:valorSedeData.map(i=>i.nombre),
            datasets:[{
                label:"Valor de compra",
                data:valorSedeData.map(i=>i.valor),
                backgroundColor:"rgba(20, 184, 166, 0.75)",
                borderRadius:8, barPercentage:0.6
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{position:"top"},
                tooltip:{callbacks:{label:function(ctx){return ctx.label+": "+fmtValor(ctx.raw);}}}
            },
            scales:{y:{beginAtZero:true,ticks:{callback:function(v){return "$"+v.toLocaleString("es-CO");}}}}
        }
    });
}

const ctxValorCategoria = document.getElementById("valorCategoriaChart");
if(ctxValorCategoria) {
    new Chart(ctxValorCategoria, {
        type:"bar",
        data: {
            labels:valorCatData.map(i=>i.categoria),
            datasets:[{
                label:"Valor de compra",
                data:valorCatData.map(i=>i.valor),
                backgroundColor:coloresBase.slice(0,Math.max(valorCatData.length,1)),
                borderRadius:8
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            indexAxis:"y",
            plugins:{
                legend:{position:"top"},
                tooltip:{callbacks:{label:function(ctx){return ctx.label+": "+fmtValor(ctx.raw);}}}
            },
            scales:{x:{beginAtZero:true,ticks:{callback:function(v){return "$"+v.toLocaleString("es-CO");}}}}
        }
    });
}

const ctxValorEstado = document.getElementById("valorEstadoChart");
if(ctxValorEstado) {
    const mapEstColor = {bueno:"#10b981", nuevo:"#3b82f6", regular:"#f59e0b", malo:"#ef4444"};
    new Chart(ctxValorEstado, {
        type:"doughnut",
        data: {
            labels:valorEstData.map(i=>ucFirst(i.estado)),
            datasets:[{
                data:valorEstData.map(i=>i.valor),
                backgroundColor:valorEstData.map(i=>mapEstColor[i.estado]||"#6b7280"),
                borderWidth:0, hoverOffset:15
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{position:"bottom",labels:{font:{size:12}}},
                tooltip:{callbacks:{label:function(ctx){return ctx.label+": "+fmtValor(ctx.raw);}}}
            },
            cutout:"60%"
        }
    });
}
function ucFirst(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
</script>';
require_once '../includes/footer.php';
?>

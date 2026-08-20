<?php
$paginaActual = $paginaActual ?? basename($_SERVER['PHP_SELF']);

function navActiva($href, $actual) {
    $hrefBase = basename($href);
    return ($hrefBase === $actual || strpos($href, $actual) !== false) ? 'active' : '';
}
?>
    <aside class="sidebar-left" id="sidebar">
    <nav class="sidebar-nav">
        <?php if (esAdmin()): ?>
        <div class="nav-section-title">Gestión</div>
        <a href="../modulo_dashboard/index.php" class="nav-item <?php echo navActiva('../modulo_dashboard/index.php', $paginaActual); ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="../modulo_inventario_general/index.php" class="nav-item <?php echo navActiva('../modulo_inventario_general/index.php', $paginaActual); ?>">
            <i class="fas fa-warehouse"></i> Inventario
        </a>
        <a href="../modulo_inventario_general/actas.php" class="nav-item <?php echo navActiva('../modulo_inventario_general/actas.php', $paginaActual); ?>">
            <i class="fas fa-file-signature"></i> Actas de Entrega
        </a>
        <a href="../modulo_inventario_general/proveedores.php" class="nav-item <?php echo navActiva('../modulo_inventario_general/proveedores.php', $paginaActual); ?>">
            <i class="fas fa-truck"></i> Proveedores
        </a>
        <a href="../modulo_toma_fisica/index.php" class="nav-item <?php echo navActiva('../modulo_toma_fisica/index.php', $paginaActual); ?>">
            <i class="fas fa-clipboard-check"></i> Toma Física
        </a>
        <a href="../modulo_reportes/index.php" class="nav-item <?php echo navActiva('../modulo_reportes/index.php', $paginaActual); ?>">
            <i class="fas fa-chart-bar"></i> Reportes
        </a>
        <a href="../modulo_usuarios/index.php" class="nav-item <?php echo navActiva('../modulo_usuarios/index.php', $paginaActual); ?>">
            <i class="fas fa-users"></i> Usuarios
        </a>
        <a href="../modulo_sedes/index.php" class="nav-item <?php echo navActiva('../modulo_sedes/index.php', $paginaActual); ?>">
            <i class="fas fa-school"></i> Sedes
        </a>
        <?php endif; ?>
        <div class="nav-section-title">Préstamos</div>
        <a href="../modulo_prestamos/solicitudes.php" class="nav-item <?php echo navActiva('../modulo_prestamos/solicitudes.php', $paginaActual); ?>">
            <i class="fas fa-clipboard-list"></i> Solicitudes
        </a>
        <a href="../modulo_prestamos/prestamos.php" class="nav-item <?php echo navActiva('../modulo_prestamos/prestamos.php', $paginaActual); ?>">
            <i class="fas fa-handshake"></i> Préstamos
        </a>
        <div class="nav-section-title">Sistema</div>
        <?php if (esAdmin()): ?>
        <a href="../modulo_dashboard/alertas.php" class="nav-item <?php echo navActiva('../modulo_dashboard/alertas.php', $paginaActual); ?>">
            <i class="fas fa-exclamation-triangle"></i> Centro de Alertas
        </a>
        <a href="../modulo_auditoria/index.php" class="nav-item <?php echo navActiva('../modulo_auditoria/index.php', $paginaActual); ?>">
            <i class="fas fa-history"></i> Auditoría
        </a>
        <?php endif; ?>
    </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

<main class="content-right">
    <div class="page active">

<?php
if (!isset($usuario)) {
    $usuario = obtenerUsuarioActual();
}

?>
<body>

<div class="app" style="display:block;">
    <header class="glass-header">
        <div class="logo">
            <?php $logo = obtenerLogo(); if ($logo): ?>
            <img src="../<?php echo $logo; ?>" alt="MIC" style="height:42px;width:auto;">
            <?php else: ?>
            <div class="logo-icon"><i class="fas fa-cubes"></i></div>
            <?php endif; ?>
            <div class="logo-text">
                <h2>MIC</h2>
                <span>Institución Educativa 20 de Julio</span>
            </div>
        </div>
        <div class="header-actions">
            <div class="user-menu" onclick="toggleDropdown()">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($usuario['nombre'] ?? 'Usuario'); ?></div>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </header>

    <div class="dropdown-menu" id="dropdownMenu">
        <div class="dropdown-header">
            <div class="dropdown-avatar"><i class="fas fa-user"></i></div>
            <div class="dropdown-info">
                <strong><?php echo htmlspecialchars($usuario['nombre'] ?? 'Usuario'); ?></strong>
                <span><?php echo htmlspecialchars($usuario['email'] ?? ''); ?></span>
            </div>
        </div>
        <div class="dropdown-divider"></div>
        <a href="<?php echo BASE_URL; ?>actions/cerrar_sesion.php" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
    </div>

    <div class="main-layout">

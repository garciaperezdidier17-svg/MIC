<?php
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../index.php'); exit; }

$usuario = obtenerUsuarioActual();
$pageTitle = 'Manual de Usuario - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_dashboard/manual_usuario.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<style>
.manual-container { max-width: 960px; margin: 0 auto; padding: 30px 20px; }
.manual-container h1 { font-size: 1.6rem; color: var(--primary); margin-bottom: 6px; }
.manual-container .subtitle { color: var(--gray); margin-bottom: 30px; }
.manual-section { margin-bottom: 35px; background: #fff; border-radius: 12px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.manual-section h2 { font-size: 1.1rem; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
.manual-section h2 i { color: var(--primary); width: 20px; }
.manual-section p, .manual-section li { color: #475569; line-height: 1.7; font-size: 0.9rem; }
.manual-section ul { padding-left: 20px; }
.manual-section ul li { margin-bottom: 6px; }
.manual-section ol { padding-left: 20px; }
.manual-section ol li { margin-bottom: 6px; color: #475569; line-height: 1.7; font-size: 0.9rem; }
.manual-section strong { color: #1e293b; }
.manual-section .nota { background: #fef9c3; border-left: 3px solid #eab308; padding: 8px 14px; border-radius: 6px; font-size: 0.85rem; margin-top: 10px; }
.manual-section .paso { background: #f0fdf4; border-left: 3px solid #22c55e; padding: 8px 14px; border-radius: 6px; font-size: 0.85rem; margin-top: 10px; }
</style>

<div class="manual-container">
    <h1><i class="fas fa-book"></i> Manual de Usuario</h1>
    <p class="subtitle">Guía completa del Sistema MIC - Institución Educativa 20 de Julio</p>

    <!-- ============================================================ -->
    <!-- INICIO DE SESIÓN -->
    <!-- ============================================================ -->
    <div class="manual-section">
        <h2><i class="fas fa-sign-in-alt"></i> Inicio de Sesión y Registro</h2>

        <p><strong>Pantalla de inicio:</strong> Al abrir el sistema en su navegador, aparece la pantalla de inicio de sesión. Está compuesta por:</p>
        <ul>
            <li><strong>Logo y nombre:</strong> Arriba, el logo de la institución (si se cargó) y el nombre "MIC" con el lema "<?php echo $instNombre; ?>".</li>
            <li><strong>Campo "Usuario":</strong> Ingrese aquí el nombre de usuario que creó al registrarse o el que le asignó el administrador.</li>
            <li><strong>Campo "Contraseña":</strong> Ingrese su contraseña. Los caracteres se ocultan por seguridad.</li>
            <li><strong>Botón "Ingresar":</strong> Haga clic aquí o presione Enter para iniciar sesión.</li>
            <li><strong>Enlace "¿No tienes cuenta? Regístrate":</strong> Lo lleva al formulario de registro.</li>
        </ul>

        <p><strong>Registro de nuevo usuario:</strong></p>
        <ol>
            <li>Haga clic en "¿No tienes cuenta? Regístrate".</li>
            <li>Complete los campos obligatorios: <strong>Nombre de usuario</strong> (debe ser único), <strong>Correo electrónico</strong>, <strong>Contraseña</strong> y <strong>Confirmar contraseña</strong>.</li>
            <li>Presione "Registrarse".</li>
            <li>Si los datos son válidos, el sistema lo redirige al inicio de sesión para que ingrese con su nueva cuenta.</li>
            <li>Todos los nuevos usuarios se crean con el rol <strong>Usuario</strong> (sin permisos de administración).</li>
        </ol>

        <p><strong>Después de iniciar sesión:</strong></p>
        <ul>
            <li>Si es <strong>Administrador</strong>, verá el Dashboard con todas las opciones de gestión en la barra lateral izquierda.</li>
            <li>Si es <strong>Usuario regular</strong>, verá únicamente los módulos de Solicitudes y Préstamos en la barra lateral, más el enlace a este Manual de Usuario.</li>
            <li>En el encabezado superior derecho aparece su nombre de usuario y un botón para <strong>Cerrar sesión</strong>.</li>
            <li>Si el usuario está <strong>desactivado</strong>, el sistema impedirá el ingreso mostrando un mensaje de error.</li>
        </ul>

        <div class="nota">Si olvidó su contraseña, contacte al administrador del sistema para que restablezca su cuenta. No hay función de recuperación automática.</div>
    </div>

    <!-- ============================================================ -->
    <!-- DASHBOARD -->
    <!-- ============================================================ -->
    <?php if (esAdmin()): ?>
    <div class="manual-section">
        <h2><i class="fas fa-qrcode"></i> Toma Física</h2>
        <p>Inicie una toma para una sede y ubicación, verifique activos mediante su código o QR y registre coincidencias, novedades y evidencias. El módulo permite gestionar mantenimientos y solicitar bajas; las bajas requieren aprobación administrativa.</p>
    </div>
    <div class="manual-section">
        <h2><i class="fas fa-file-signature"></i> Actas</h2>
        <p>Genere actas para activos seleccionados y un responsable de la sede. El sistema conserva el documento y registra el evento en el historial.</p>
    </div>
    <div class="manual-section">
        <h2><i class="fas fa-truck"></i> Proveedores</h2>
        <p>Registre y actualice proveedores, sus datos de contacto y su estado. Los proveedores activos pueden asociarse a bienes adquiridos por compra.</p>
    </div>
    <div class="manual-section">
        <h2><i class="fas fa-file-excel"></i> Importación desde Excel</h2>
        <p>Suba una plantilla Excel, revise la validación por fila y confirme la importación de filas válidas. El sistema genera códigos y QR, registra el historial y audita la importación.</p>
    </div>
    <div class="manual-section">
        <h2><i class="fas fa-bell"></i> Centro de Alertas</h2>
        <p>Consulte alertas de inventario y préstamos, incluidos daños, garantías, vida útil, documentación pendiente y fechas de devolución. El período de alerta de garantías se configura desde el módulo.</p>
    </div>
    <div class="manual-section">
        <h2><i class="fas fa-history"></i> Auditoría</h2>
        <p>Revise las acciones registradas con filtros por usuario, acción, módulo y fechas. El historial de cada elemento también muestra sus movimientos y cambios.</p>
    </div>
    <div class="manual-section">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard / Panel Principal</h2>
        <p>Es la primera pantalla que ve el administrador al iniciar sesión. Proporciona una vista general del estado del inventario unificado.</p>

        <p><strong>Tarjetas de resumen (KPIs):</strong> Ocho indicadores numéricos con iconos de colores:</p>
        <ul>
            <li><strong>Total Registros:</strong> Cantidad total de elementos activos en el Inventario General.</li>
            <li><strong>Tipos Distintos:</strong> Número de tipos diferentes de elementos registrados.</li>
            <li><strong>En Buen Estado:</strong> Elementos marcados como "bueno" o "nuevo".</li>
            <li><strong>Sedes:</strong> Número de sedes registradas.</li>
            <li><strong>Activos Disponibles:</strong> Elementos listos para préstamo o uso (estado bueno o nuevo).</li>
            <li><strong>En Reparación:</strong> Elementos actualmente en estado regular (requieren mantenimiento).</li>
            <li><strong>Activos Dañados:</strong> Elementos con estado "malo".</li>
            <li><strong>Valor Total Inventario:</strong> Suma del valor comercial de todos los activos registrados.</li>
        </ul>

        <p><strong>Alertas del Sistema:</strong> Sección que muestra tarjetas de advertencia según el estado del inventario:</p>
        <ul>
            <li><strong>En reparación:</strong> Muestra la cantidad de activos que requieren mantenimiento.</li>
            <li><strong>Dañados:</strong> Muestra la cantidad de activos registrados como dañados.</li>
            <li><strong>Vida útil próxima:</strong> Muestra los activos cuya vida útil está por vencer o ya venció.</li>
        </ul>
        <p>Si no hay novedades, muestra "Actualmente no existen alertas."</p>

        <p><strong>Gráficos:</strong> Seis gráficos interactivos que se actualizan automáticamente:</p>
        <ul>
            <li><strong>Estado del Inventario:</strong> Gráfico de dona con la proporción de elementos en cada estado (bueno, regular, malo, nuevo).</li>
            <li><strong>Inventario por Sede:</strong> Barras con la cantidad de registros por cada sede.</li>
            <li><strong>Inventario por Tipo:</strong> Barras horizontales con los tipos de activo más comunes.</li>
            <li><strong>Inventario por Categoría:</strong> Barras con la cantidad de elementos por categoría (Equipos de Cómputo, Mobiliario, etc.).</li>
            <li><strong>Activos por Ubicación:</strong> Barras horizontales con las 10 ubicaciones que más activos tienen, ordenadas de mayor a menor.</li>
            <li><strong>Vida Útil del Inventario:</strong> Barras que clasifican los activos según años de vida útil: menos de 1 año, 1 a 3 años, 3 a 5 años y más de 5 años.</li>
        </ul>

        <p><strong>Barra lateral izquierda:</strong> Menú de navegación principal con las secciones: Dashboard, Inventario, Reportes, Usuarios, Sedes, Manual de Usuario, Solicitudes y Préstamos. La sección activa se resalta con un color diferente. Los módulos "Inventario Equipos" e "Inventario General" ahora están unificados en una sola entrada "Inventario".</p>

        <div class="nota">Los datos del Dashboard se actualizan en tiempo real cada vez que ingresa o recarga la página.</div>
    </div>

    <!-- ============================================================ -->
    <!-- INVENTARIO GENERAL (UNIFICADO) -->
    <!-- ============================================================ -->
    <div class="manual-section">
        <h2><i class="fas fa-warehouse"></i> Inventario General</h2>
        <p>Administra activos institucionales en <code>inventario_general</code>, con código interno, categoría, tipo, sede, ubicación, estado, valores, documentos e historial.</p>
        <ul>
            <li>Las sedes se consultan desde la base de datos. En el código actual, las opciones de ubicación asociadas a cada sede todavía se cargan desde <code>config/ubicaciones.php</code>; no se describen aquí como si ya provinieran de la tabla <code>ubicaciones</code>.</li>
            <li>Al crear un elemento, el sistema calcula el código interno y puede generar su QR. Los elementos inactivos permanecen registrados.</li>
            <li>La disponibilidad para préstamo depende de la marca de préstamo, la situación actual y el estado del elemento.</li>
        </ul>
    </div>

    <!-- ============================================================ -->
    <!-- SOLICITUDES -->
    <!-- ============================================================ -->
    <?php endif; ?>
    <div class="manual-section">
        <h2><i class="fas fa-clipboard-list"></i> Solicitudes de Préstamo</h2>
        <p>Las solicitudes reúnen varios activos de <code>inventario_general</code>. Quien solicita indica la sede, un responsable de esa sede, el motivo, las fechas y uno o más elementos.</p>
        <ul>
            <li>Los elementos se agregan en modo individual o por cantidad. La cantidad se valida contra las unidades que estén disponibles.</li>
            <li>La solicitud queda <strong>Pendiente</strong> hasta que un administrador la apruebe o rechace. Al aprobar, el sistema vuelve a validar la disponibilidad y crea el préstamo con sus elementos.</li>
            <li>Los estados visibles de solicitud incluyen Pendiente, Aprobada, Rechazada, Entregada, Devuelta y Cancelada.</li>
            <li>Los administradores registran devoluciones parciales o totales. Por cada elemento indican Bueno, Regular, Dañado o Perdido; el préstamo queda parcialmente devuelto mientras falten elementos y Devuelto al completar la devolución.</li>
            <li>Los recordatorios de préstamo se procesan para 3 días, 1 día, el día de vencimiento y préstamos vencidos.</li>
        </ul>
        <p>La aprobación reserva los activos disponibles; los elementos Perdidos quedan en situación perdida y no vuelven a ofrecerse para préstamo.</p>
    </div>

    <!-- ============================================================ -->
    <!-- PRÉSTAMOS -->
    <!-- ============================================================ -->
    <div class="manual-section">
        <h2><i class="fas fa-handshake"></i> Préstamos</h2>
        <p>El préstamo se genera al aprobar una solicitud. Los administradores consultan y gestionan préstamos con uno o varios elementos; los usuarios consultan los propios.</p>
        <ul>
            <li>Los estados incluyen Activo, Vencido, Parcialmente devuelto, Devuelto y Cancelado.</li>
            <li>Se puede registrar la devolución de algunos elementos y completar el resto después. Cada devolución guarda estado, observaciones y, si se adjunta, evidencia.</li>
            <li>El estado físico de cada elemento devuelto puede ser Bueno, Regular, Dañado o Perdido. Un elemento Perdido queda marcado como perdido y no disponible.</li>
            <li>El sistema maneja recordatorios a 3 días, 1 día, el día de vencimiento y cuando el préstamo está vencido.</li>
        </ul>
    </div>

    <!-- ============================================================ -->
    <!-- REPORTES -->
    <!-- ============================================================ -->
    <?php if (esAdmin()): ?>
    <div class="manual-section">
        <h2><i class="fas fa-chart-bar"></i> Reportes</h2>
        <p>Exporta la información del sistema a archivos descargables en formatos Excel (.xlsx) y PDF.</p>

        <p><strong>Tipos de reporte disponibles:</strong></p>
        <ul>
            <li><strong>Equipos:</strong> Todos los equipos con nombre, marca, modelo, serial, sede, estado, ubicación, fechas, valores y descripción.</li>
            <li><strong>Inventario General:</strong> Todos los elementos con código, nombre, categoría, tipo, sede, ubicación, estado y valor comercial.</li>
            <li><strong>Préstamos:</strong> Todos los préstamos (activos y finalizados) con usuario, equipo, fechas y estado.</li>
            <li><strong>Usuarios:</strong> Todos los usuarios registrados con nombre, correo, rol y estado.</li>
        </ul>

        <p><strong>Formato Excel:</strong></p>
        <ul>
            <li>Descarga un archivo .xlsx compatible con Microsoft Excel, LibreOffice Calc y Google Sheets.</li>
            <li>Incluye encabezados con fondo azul (#1a56db) y texto blanco.</li>
            <li>Celdas con bordes finos para mejor legibilidad.</li>
            <li>Ancho de columnas ajustado automáticamente al contenido.</li>
            <li>Nombre del archivo: mic_[tipo]_[fecha].xlsx (ej. mic_inventario_general_2026-07-18.xlsx).</li>
        </ul>

        <p><strong>Formato PDF:</strong></p>
        <ul>
            <li>Descarga un archivo PDF en orientación horizontal (A4 apaisado).</li>
            <li>Incluye el logo de la institución en la parte superior (si se cargó).</li>
            <li>Título del reporte, fecha de generación y total de registros.</li>
            <li>Tabla con encabezados azules, filas alternadas en gris claro y bordes.</li>
            <li>Pie de página con fecha de generación y nombre del sistema.</li>
            <li>Nombre del archivo: mic_[tipo]_[fecha].pdf.</li>
        </ul>

        <p><strong>Cómo generar un reporte:</strong></p>
        <ol>
            <li>Haga clic en el botón del reporte deseado (Equipos, Inventario General, Préstamos o Usuarios).</li>
            <li>Seleccione el formato: "Excel" (XLSX) o "PDF".</li>
            <li>El archivo se descarga automáticamente. Si el navegador lo bloquea, permita las descargas desde este sitio.</li>
        </ol>

        <div class="nota">Los reportes reflejan la información actual del sistema. Si hay datos desactivados, NO aparecen en el reporte.</div>
    </div>

    <!-- ============================================================ -->
    <!-- USUARIOS -->
    <!-- ============================================================ -->
    <div class="manual-section">
        <h2><i class="fas fa-users"></i> Usuarios</h2>
        <p>Administración completa de las cuentas de usuario del sistema.</p>

        <p><strong>Estructura de la página:</strong></p>
        <ul>
            <li><strong>Título:</strong> "Gestión de Usuarios" con botón "Agregar Usuario".</li>
            <li><strong>Tabla de usuarios:</strong> Columnas:
                <ul>
                    <li><strong>Nº:</strong> Consecutivo.</li>
                    <li><strong>Usuario:</strong> Nombre de inicio de sesión.</li>
                    <li><strong>Correo:</strong> Correo electrónico del usuario.</li>
                    <li><strong>Rol:</strong> "Admin" con distintivo azul o "Usuario" con distintivo gris.</li>
                    <li><strong>Estado:</strong> "Activo" (verde) o "Inactivo" (rojo).</li>
                    <li><strong>Acciones:</strong> Editar, Cambiar Rol, Desactivar/Reactivar.</li>
                </ul>
            </li>
        </ul>

        <p><strong>Cómo agregar un usuario:</strong></p>
        <ol>
            <li>Haga clic en "Agregar Usuario".</li>
            <li>Ingrese <strong>Nombre de usuario</strong> (debe ser único, sin espacios).</li>
            <li>Ingrese <strong>Correo electrónico</strong>.</li>
            <li>Ingrese <strong>Contraseña</strong> (mínimo 4 caracteres).</li>
            <li>Seleccione el <strong>Rol</strong> (Admin o Usuario).</li>
            <li>Presione "Guardar".</li>
        </ol>

        <p><strong>Cómo editar un usuario:</strong></p>
        <ol>
            <li>Haga clic en "Editar" (lápiz).</li>
            <li>Puede cambiar el nombre de usuario, correo o contraseña (si deja la contraseña en blanco, se conserva la actual).</li>
            <li>Presione "Actualizar".</li>
        </ol>

        <p><strong>Cómo cambiar el rol:</strong></p>
        <ol>
            <li>Haga clic en el botón de intercambio (⇄) junto al usuario.</li>
            <li>Si era "Usuario" pasa a "Admin". Si era "Admin" pasa a "Usuario".</li>
            <li>El cambio es inmediato. La próxima vez que el usuario inicie sesión, verá las opciones correspondientes a su nuevo rol.</li>
        </ol>

        <p><strong>Cómo desactivar un usuario:</strong></p>
        <ol>
            <li>Haga clic en el botón rojo "Desactivar".</li>
            <li>El usuario no podrá iniciar sesión.</li>
            <li>Sus datos y préstamos históricos se conservan.</li>
            <li>Para reactivarlo, haga clic en el botón verde "Reactivar" junto al usuario inactivo.</li>
        </ol>

        <div class="nota">El usuario "admin" principal no puede ser desactivado ni cambiado de rol por seguridad.</div>
    </div>

    <!-- ============================================================ -->
    <!-- SEDES -->
    <!-- ============================================================ -->
    <div class="manual-section">
        <h2><i class="fas fa-school"></i> Sedes</h2>
        <p>Administra las sedes físicas donde se ubican los equipos y elementos.</p>

        <p><strong>Estructura de la página:</strong></p>
        <ul>
            <li><strong>Título:</strong> "Gestión de Sedes" con botón "Agregar Sede".</li>
            <li><strong>Tarjetas de sedes:</strong> Cada sede se muestra como una tarjeta independiente con:
                <ul>
                    <li><strong>Nombre:</strong> Nombre de la sede (ej. "Sede Principal", "La Paz").</li>
                    <li><strong>Dirección:</strong> Ubicación física.</li>
                    <li><strong>Estado:</strong> Indicador "Activo" (verde) o "Inactivo" (gris).</li>
                    <li><strong>Equipos:</strong> Número de equipos activos asociados a esa sede.</li>
                    <li><strong>Acciones:</strong> Editar, Activar/Desactivar.</li>
                </ul>
            </li>
        </ul>

        <p><strong>Cómo agregar una sede:</strong></p>
        <ol>
            <li>Haga clic en "Agregar Sede".</li>
            <li>Ingrese el <strong>Nombre</strong> de la sede.</li>
            <li>Ingrese la <strong>Dirección</strong>.</li>
            <li>Presione "Guardar". La sede se crea automáticamente como Activa.</li>
        </ol>

        <p><strong>Cómo editar una sede:</strong></p>
        <ol>
            <li>Haga clic en "Editar" en la tarjeta de la sede.</li>
            <li>Modifique el nombre y/o la dirección.</li>
            <li>Presione "Actualizar".</li>
        </ol>

        <p><strong>Cómo desactivar una sede:</strong></p>
        <ol>
            <li>Haga clic en "Desactivar".</li>
            <li>Las sedes inactivas NO aparecen en los filtros de los formularios de inventario.</li>
            <li>Los elementos ya asociados a esa sede se conservan.</li>
            <li>Para activarla de nuevo, haga clic en "Activar".</li>
        </ol>

        <div class="nota">No se puede eliminar una sede que tenga elementos asociados. Debe primero reasignarlos a otra sede o eliminarlos.</div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

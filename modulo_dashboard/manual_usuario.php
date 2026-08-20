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
            <li><strong>Logo y nombre:</strong> Arriba, el logo de la institución (si se cargó) y el nombre "MIC" con el lema "Institución Educativa 20 de Julio".</li>
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
        <p>Módulo unificado para administrar TODOS los activos de la institución: equipos de cómputo, periféricos, mobiliario, equipos audiovisuales, material educativo, herramientas, y cualquier otro elemento. Reemplaza los antiguos módulos separados de "Inventario Equipos" e "Inventario General".</p>

        <p><strong>Estructura de la página:</strong></p>
        <ul>
            <li><strong>Título:</strong> "Inventario General" con botón "Agregar Elemento" y "Eliminar Todos".</li>
            <li><strong>Buscador y filtros:</strong> Barra de búsqueda por nombre, marca, modelo o código. Filtros por Tipo, Estado y Sede.</li>
            <li><strong>Tabla de elementos:</strong> Cada fila contiene:
                <ul>
                    <li><strong>Código:</strong> Código único auto-generado (formato: INST-SEDE-UBICACIÓN-CONSECUTIVO, ej. 20J-01-S001-001).</li>
                    <li><strong>Nombre:</strong> Nombre descriptivo del elemento.</li>
                    <li><strong>Tipo:</strong> Tipo específico dentro de la categoría seleccionada.</li>
                    <li><strong>Especificaciones:</strong> Marca, modelo, procesador, RAM, almacenamiento (si aplica).</li>
                    <li><strong>Estado:</strong> Indicador de color: azul = nuevo, verde = bueno, amarillo = regular, rojo = malo.</li>
                    <li><strong>Ubicación/Sede:</strong> Lugar físico y sede donde se encuentra.</li>
                    <li><strong>VR Comercial:</strong> Valor comercial del elemento.</li>
                    <li><strong>QR:</strong> Botones para Ver QR, Descargar PNG, Imprimir QR, e Imprimir Etiqueta (solo si el QR fue generado).</li>
                    <li><strong>Acciones:</strong> Editar (lápiz) y Eliminar (papelera).</li>
                </ul>
            </li>
        </ul>

        <p><strong>Cómo agregar un elemento:</strong></p>
        <ol>
            <li>Haga clic en "Agregar Elemento".</li>
            <li>Ingrese el <strong>Nombre</strong> del elemento (ej. "Portátil Dell", "Silla ergonómica").</li>
            <li>El <strong>Código</strong> se genera automáticamente al guardar. No puede editarlo.</li>
            <li>Seleccione la <strong>Categoría</strong> de una lista profesional de 20 opciones (Equipos de Cómputo, Periféricos, Impresión, Redes, Mobiliario, etc.).</li>
            <li>Al seleccionar la categoría, el campo <strong>Tipo</strong> se llena automáticamente con los tipos disponibles para esa categoría. Si no encuentra el tipo exacto, seleccione "Otro (especifique)" y escríbalo manualmente.</li>
            <li>Seleccione el <strong>Estado</strong>: Nuevo, Bueno, Regular o Malo. Si el elemento es donado, marque la casilla "Donado / No aplica VR" para ocultar los campos de valor comercial y vida útil.</li>
            <li>Si el tipo seleccionado es "Computador de escritorio" o "Portátil", aparecerá la sección <strong>Especificaciones Técnicas</strong> donde puede ingresar Marca, Modelo, N° de Serie, Procesador, RAM, Almacenamiento y Accesorios.</li>
            <li>Seleccione la <strong>Sede</strong> donde se encuentra el elemento.</li>
            <li>Al seleccionar la sede, el campo <strong>Ubicación</strong> se llena automáticamente con las ubicaciones disponibles para esa sede. Seleccione una de la lista. Si la sede no tiene ubicaciones registradas, se mostrará "No hay ubicaciones disponibles".</li>
            <li>Ingrese <strong>Vida Útil</strong> (años), <strong>Descripción</strong> y <strong>VR Comercial</strong> si aplica.</li>
            <li>Presione "Guardar". El sistema generará automáticamente:
                <ul>
                    <li>El <strong>código único</strong> del elemento (ej. 20J-01-S001-001).</li>
                    <li>El <strong>código QR</strong> correspondiente, guardado en el servidor.</li>
                </ul>
            </li>
        </ol>

        <p><strong>Cómo editar un elemento:</strong></p>
        <ol>
            <li>Haga clic en el botón "Editar" (icono de lápiz) en la fila del elemento.</li>
            <li>Se abre el formulario con los datos actuales cargados.</li>
            <li>El campo <strong>Código</strong> es de solo lectura (se asigna al crear y no puede modificarse).</li>
            <li>Modifique los campos necesarios.</li>
            <li>Presione "Guardar Cambios".</li>
        </ol>

        <p><strong>Cómo eliminar un elemento:</strong></p>
        <ol>
            <li>Haga clic en "Eliminar" (icono de papelera).</li>
            <li>Confirme la acción en la ventana emergente.</li>
            <li>El elemento se desactiva y desaparece del listado. Se conserva en la base de datos para historial.</li>
        </ol>

        <p><strong>Código QR y Etiquetas:</strong></p>
        <ul>
            <li>Al guardar un elemento nuevo, el sistema genera automáticamente un código QR con el código único.</li>
            <li>En la columna QR de la tabla encontrará botones para:
                <ul>
                    <li><strong>Ver QR:</strong> Abre una ventana modal con el código QR en tamaño grande. Desde allí puede descargarlo o imprimirlo.</li>
                    <li><strong>Descargar QR:</strong> Descarga la imagen PNG del QR directamente.</li>
                    <li><strong>Imprimir QR:</strong> Abre una vista de impresión del código QR.</li>
                    <li><strong>Imprimir Etiqueta:</strong> Abre una página optimizada para impresión con el logo de la institución, nombre, código, QR, nombre del elemento y ubicación.</li>
                </ul>
            </li>
        </ul>

        <p><strong>Catálogos y escalabilidad:</strong></p>
        <ul>
            <li>Las <strong>categorías, tipos y estados</strong> se guardan en la base de datos. Desde el formulario de <strong>Agregar Elemento</strong>, el administrador puede crear una opción que no exista con los botones <strong>"+"</strong> junto a Categoría, Tipo y Estado: se abre un pequeño modal, se guarda en la base de datos y el desplegable se actualiza seleccionando automáticamente la opción nueva, sin abandonar el formulario. El archivo <code>config/catalogos_inventario.php</code> solo actúa como respaldo si la base de datos está vacía.</li>
            <li>Las <strong>ubicaciones por sede</strong> se cargan desde <code>config/ubicaciones.php</code>. Para agregar nuevas ubicaciones o sedes, solo se edita ese archivo.</li>
            <li>El <strong>código de institución</strong> se configura en <code>config/institucion.php</code>.</li>
        </ul>

        <div class="nota">El código único sigue el formato: [Institución]-[Sede]-[Ubicación]-[Consecutivo]. Ejemplo: 20J-01-S001-001. El consecutivo se reinicia por cada ubicación.</div>
    </div>

    <!-- ============================================================ -->
    <!-- SOLICITUDES -->
    <!-- ============================================================ -->
    <?php endif; ?>
    <div class="manual-section">
        <h2><i class="fas fa-clipboard-list"></i> Solicitudes de Préstamo</h2>
        <p>Los usuarios crean solicitudes para pedir equipos prestados. El administrador las revisa y decide si las aprueba o rechaza.</p>

        <p><strong>Vista del usuario regular:</strong></p>
        <ul>
            <li>Al entrar a "Solicitudes", ve una tabla con sus propias solicitudes: número, equipo, fechas, estado (Pendiente/Aprobada/Rechazada) y observaciones.</li>
            <li>Botón "Nueva Solicitud" para crear una.</li>
            <li>Una vez creada, no puede modificarla ni eliminarla. Solo el administrador puede cambiar su estado.</li>
        </ul>

        <p><strong>Vista del administrador:</strong></p>
        <ul>
            <li>Ve <strong>TODAS</strong> las solicitudes de todos los usuarios.</li>
            <li>Columnas adicionales: nombre del usuario que la creó y botones de acción.</li>
            <li>Puede <strong>Aprobar</strong>: cambia el estado a "Aprobada" y crea automáticamente un préstamo activo con los datos de la solicitud. El equipo asociado se marca como "Prestado".</li>
            <li>Puede <strong>Rechazar</strong>: cambia el estado a "Rechazada". El usuario ve el motivo si se agregó en las observaciones.</li>
        </ul>

        <p><strong>Cómo crear una solicitud (usuario):</strong></p>
        <ol>
            <li>Haga clic en "Nueva Solicitud".</li>
            <li>Seleccione el <strong>Equipo</strong> de la lista desplegable (solo equipos disponibles).</li>
            <li>Seleccione la <strong>Sede</strong> donde lo necesita.</li>
            <li>Elija <strong>Fecha Inicio</strong> y <strong>Fecha Fin</strong> del préstamo deseado.</li>
            <li>Agregue una <strong>Observación</strong> si es necesario (ej. "Para proyecto de ciencias").</li>
            <li>Presione "Enviar Solicitud".</li>
            <li>Espere la respuesta del administrador.</li>
        </ol>

        <p><strong>Cómo aprobar/rechazar (administrador):</strong></p>
        <ol>
            <li>En la tabla de solicitudes, ubique la solicitud pendiente.</li>
            <li>Haga clic en el botón verde "✔ Aprobar" o rojo "✖ Rechazar".</li>
            <li>Si aprueba, el sistema crea automáticamente el préstamo. La solicitud cambia a "Aprobada".</li>
            <li>Si rechaza, la solicitud cambia a "Rechazada". El usuario lo verá en su listado.</li>
        </ol>

        <div class="paso"><strong>Flujo completo:</strong> Usuario crea solicitud → Administrador la aprueba → Sistema crea préstamo automáticamente → Usuario recibe el equipo → Administrador registra la devolución en Préstamos.</div>
    </div>

    <!-- ============================================================ -->
    <!-- PRÉSTAMOS -->
    <!-- ============================================================ -->
    <div class="manual-section">
        <h2><i class="fas fa-handshake"></i> Préstamos</h2>
        <p>Gestiona los préstamos activos y finalizados de equipos.</p>

        <p><strong>Vista del usuario regular:</strong></p>
        <ul>
            <li>Ve únicamente sus propios préstamos.</li>
            <li>Columnas: equipo, sede, fechas (inicio y fin), estado (Activo/Finalizado), observaciones.</li>
            <li>No puede realizar ninguna acción, solo consultar.</li>
        </ul>

        <p><strong>Vista del administrador:</strong></p>
        <ul>
            <li>Ve <strong>TODOS</strong> los préstamos del sistema.</li>
            <li>Columnas adicionales: nombre del usuario, equipo, fechas, estado y acciones.</li>
            <li>Botón "Nuevo Préstamo" para crear uno sin solicitud previa.</li>
            <li>Puede <strong>Devolver</strong> (marca como finalizado y el equipo vuelve a "Disponible").</li>
            <li>Puede <strong>Eliminar</strong> (borra el registro del préstamo permanentemente).</li>
        </ul>

        <p><strong>Cómo crear un préstamo directo (administrador):</strong></p>
        <ol>
            <li>Haga clic en "Nuevo Préstamo".</li>
            <li>Seleccione el <strong>Usuario</strong> que recibe el equipo.</li>
            <li>Seleccione el <strong>Equipo</strong> (solo disponibles).</li>
            <li>Seleccione la <strong>Sede</strong>.</li>
            <li>Defina <strong>Fecha Inicio</strong> y <strong>Fecha Fin</strong>.</li>
            <li>Agregue <strong>Observaciones</strong> si aplica.</li>
            <li>Presione "Guardar". El equipo se marca como "Prestado".</li>
        </ol>

        <p><strong>Cómo registrar una devolución (administrador):</strong></p>
        <ol>
            <li>Ubique el préstamo activo en la tabla.</li>
            <li>Haga clic en "Devolver".</li>
            <li>El estado del préstamo cambia a "Finalizado".</li>
            <li>El equipo vuelve a estar "Disponible" para futuros préstamos.</li>
        </ol>

        <div class="nota">No se puede eliminar un préstamo que ya fue devuelto, solo los que están activos.</div>
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

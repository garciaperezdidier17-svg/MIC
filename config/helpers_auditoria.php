<?php
/**
 * Auditoría del sistema: registro central de acciones importantes
 * realizadas por los usuarios dentro de MIC.
 * Tabla: auditoria (ver database/migracion_auditoria.sql)
 *
 * No reemplaza el historial de elementos (elemento_historial): la auditoría
 * es un registro global por usuario/acción, mientras que el historial es
 * específico de cada activo. Ambos conviven.
 */

const ACCIONES_AUDITORIA = [
    'crear_activo'           => 'Crear activo',
    'editar_activo'          => 'Editar activo',
    'eliminar_activo'        => 'Eliminar activo',
    'eliminar_todos'         => 'Eliminar todos los activos',
    'cambio_estado'          => 'Cambio de estado',
    'cambio_ubicacion'       => 'Cambio de ubicación',
    'cambio_sede'            => 'Cambio de sede',
    'reasignar_activo'       => 'Reasignación',
    'registrar_mantenimiento'=> 'Registrar mantenimiento',
    'finalizar_mantenimiento'=> 'Finalizar mantenimiento',
    'enviar_mantenimiento'   => 'Enviar a mantenimiento',
    'iniciar_toma_fisica'    => 'Registrar toma física',
    'finalizar_toma_fisica'  => 'Finalizar toma física',
    'cancelar_toma_fisica'   => 'Cancelar toma física',
    'verificar_inspeccion'   => 'Inspección física',
    'registrar_novedad'      => 'Registrar novedad',
    'generar_acta'           => 'Generar acta',
    'subir_documento'        => 'Subir documento de adquisición',
    'eliminar_documento'     => 'Eliminar documento',
    'descargar_documento'    => 'Descargar documento',
    'importar_inventario'    => 'Importar inventario desde Excel',
    'exportar_informacion'   => 'Exportar información',
    'crear_categoria'        => 'Crear categoría',
    'editar_categoria'       => 'Editar categoría',
    'activar_categoria'      => 'Activar categoría',
    'desactivar_categoria'   => 'Desactivar categoría',
    'crear_tipo'             => 'Crear tipo',
    'editar_tipo'            => 'Editar tipo',
    'activar_tipo'           => 'Activar tipo',
    'desactivar_tipo'        => 'Desactivar tipo',
    'crear_estado'           => 'Crear estado',
    'editar_estado'          => 'Editar estado',
    'activar_estado'         => 'Activar estado',
    'desactivar_estado'      => 'Desactivar estado',
    'crear_proveedor'        => 'Crear proveedor',
    'modificar_proveedor'    => 'Modificar proveedor',
    'solicitar_baja'         => 'Solicitar baja',
    'aprobar_baja'           => 'Dar de baja un activo',
    'rechazar_baja'          => 'Rechazar baja',
    'regenerar_qrs'          => 'Regenerar códigos QR',
];

const MODULOS_AUDITORIA = ['inventario', 'toma_fisica', 'actas', 'proveedores', 'reportes', 'sistema'];

function etiquetaAccionAuditoria($accion) {
    return ACCIONES_AUDITORIA[$accion] ?? ucfirst(str_replace('_', ' ', (string)$accion));
}

function obtenerIpClienteAuditoria() {
    return substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
}

function obtenerUserAgentAuditoria() {
    return substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
}

/**
 * Registra una acción en la tabla auditoria.
 * Los datos anteriores/nuevos se guardan como JSON para reconstruir qué cambió.
 * Nunca interrumpe el flujo de la aplicación (errores solo se loguean).
 */
function registrarAuditoria($conn, $accion, $modulo, $entidad = null, $entidadId = null, $descripcion = null, $datosAnteriores = null, $datosNuevos = null) {
    try {
        $usuarioId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $stmt = $conn->prepare(
            "INSERT INTO auditoria (usuario_id, accion, modulo, entidad, entidad_id, descripcion, datos_anteriores, datos_nuevos, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $usuarioId,
            trim((string)$accion),
            trim((string)$modulo),
            $entidad !== null && trim((string)$entidad) !== '' ? trim((string)$entidad) : null,
            $entidadId !== null ? (int)$entidadId : null,
            $descripcion !== null && trim((string)$descripcion) !== '' ? trim((string)$descripcion) : null,
            $datosAnteriores !== null ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null,
            $datosNuevos !== null ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null,
            obtenerIpClienteAuditoria(),
            obtenerUserAgentAuditoria(),
        ]);
        return true;
    } catch (Throwable $e) {
        logError("Error registrando auditoría: " . $e->getMessage());
        return false;
    }
}

/**
 * Lista registros de auditoría con filtros combinables:
 * buscar, usuario_id, accion, modulo, fecha_desde, fecha_hasta.
 */
function auditoriaListar($conn, array $filtros = []) {
    $where = [];
    $params = [];
    $buscar = trim((string)($filtros['buscar'] ?? ''));
    $usuarioId = (int)($filtros['usuario_id'] ?? 0);
    $accion = trim((string)($filtros['accion'] ?? ''));
    $modulo = trim((string)($filtros['modulo'] ?? ''));
    $desde = trim((string)($filtros['fecha_desde'] ?? ''));
    $hasta = trim((string)($filtros['fecha_hasta'] ?? ''));

    if ($buscar !== '') {
        $where[] = '(a.descripcion LIKE ? OR a.entidad LIKE ? OR u.nombre LIKE ?)';
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }
    if ($usuarioId > 0) {
        $where[] = 'a.usuario_id=?';
        $params[] = $usuarioId;
    }
    if ($accion !== '') {
        $where[] = 'a.accion=?';
        $params[] = $accion;
    }
    if ($modulo !== '') {
        $where[] = 'a.modulo=?';
        $params[] = $modulo;
    }
    if ($desde !== '') {
        $where[] = 'a.fecha >= ?';
        $params[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where[] = 'a.fecha <= ?';
        $params[] = $hasta . ' 23:59:59';
    }

    $sql = "SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email
            FROM auditoria a
            LEFT JOIN usuarios u ON a.usuario_id = u.id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.id DESC LIMIT 500';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Devuelve un registro de auditoría por id (datos JSON ya decodificados).
 */
function obtenerAuditoria($conn, $id) {
    $stmt = $conn->prepare(
        "SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email
         FROM auditoria a
         LEFT JOIN usuarios u ON a.usuario_id = u.id
         WHERE a.id = ?"
    );
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['datos_anteriores'] = $row['datos_anteriores'] ? json_decode($row['datos_anteriores'], true) : null;
        $row['datos_nuevos'] = $row['datos_nuevos'] ? json_decode($row['datos_nuevos'], true) : null;
    }
    return $row ?: null;
}

/**
 * Usuarios con actividad registrada (para el filtro de la pantalla).
 */
function auditoriaUsuariosActivos($conn) {
    return $conn->query(
        "SELECT DISTINCT u.id, u.nombre FROM auditoria a JOIN usuarios u ON a.usuario_id = u.id ORDER BY u.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Acciones realmente registradas (para el filtro).
 */
function auditoriaAccionesUsadas($conn) {
    return $conn->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
}
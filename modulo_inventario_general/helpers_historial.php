<?php
/**
 * Historial de elementos: registro de eventos con datos estructurados (JSON).
 * Tabla: elemento_historial
 */

const TIPOS_EVENTO_HISTORIAL = [
    'registro'                => ['label' => 'Registrado',               'icono' => 'fas fa-plus-circle',            'color' => '#3b82f6'],
    'modificacion'            => ['label' => 'Modificado',               'icono' => 'fas fa-pen',                    'color' => '#6366f1'],
    'reasignacion'            => ['label' => 'Reasignado',               'icono' => 'fas fa-user-check',             'color' => '#8b5cf6'],
    'cambio_ubicacion'        => ['label' => 'Cambio de ubicación',      'icono' => 'fas fa-map-marker-alt',         'color' => '#14b8a6'],
    'cambio_sede'             => ['label' => 'Cambio de sede',           'icono' => 'fas fa-school',                 'color' => '#0ea5e9'],
    'cambio_estado'           => ['label' => 'Cambio de estado',         'icono' => 'fas fa-exchange-alt',           'color' => '#f59e0b'],
    'mantenimiento_iniciado'  => ['label' => 'Mantenimiento iniciado',   'icono' => 'fas fa-tools',                  'color' => '#f59e0b'],
    'mantenimiento_finalizado'=> ['label' => 'Mantenimiento finalizado', 'icono' => 'fas fa-check-double',           'color' => '#22c55e'],
    'prestamo'                => ['label' => 'Préstamo',                 'icono' => 'fas fa-handshake',              'color' => '#10b981'],
    'devolucion'              => ['label' => 'Devolución',               'icono' => 'fas fa-undo',                   'color' => '#10b981'],
    'generacion_acta'         => ['label' => 'Acta generada',            'icono' => 'fas fa-file-signature',         'color' => '#ec4899'],
    'documento_agregado'      => ['label' => 'Documento agregado',       'icono' => 'fas fa-file-alt',               'color' => '#22c55e'],
    'documento_eliminado'     => ['label' => 'Documento eliminado',      'icono' => 'fas fa-file-minus',             'color' => '#ef4444'],
    'baja'                    => ['label' => 'Baja del activo',          'icono' => 'fas fa-ban',                    'color' => '#ef4444'],
    'inspeccion_fisica'       => ['label' => 'Inspección física',        'icono' => 'fas fa-clipboard-check',         'color' => '#3b82f6'],
    'cambio_situacion'        => ['label' => 'Cambio de situación',      'icono' => 'fas fa-arrows-alt-h',            'color' => '#64748b'],
    'novedad_registrada'      => ['label' => 'Novedad registrada',       'icono' => 'fas fa-sticky-note',             'color' => '#f97316'],
    'baja_solicitada'         => ['label' => 'Baja solicitada',          'icono' => 'fas fa-file-signature',          'color' => '#ef4444'],
    'baja_aprobada'           => ['label' => 'Baja aprobada',            'icono' => 'fas fa-check-circle',            'color' => '#ef4444'],
    'baja_rechazada'          => ['label' => 'Baja rechazada',           'icono' => 'fas fa-times-circle',            'color' => '#6b7280'],
];

function infoTipoEvento($tipo) {
    return TIPOS_EVENTO_HISTORIAL[$tipo] ?? ['label' => ucfirst(str_replace('_', ' ', $tipo)), 'icono' => 'fas fa-circle', 'color' => '#6b7280'];
}

/**
 * Registra un evento en el historial del elemento.
 * datosAnterior / datosNuevos: arreglos asociativos (se guardan como JSON).
 */
function registrarEventoHistorial($conn, $elementoId, $tipoEvento, $descripcion = null, $datosAnterior = null, $datosNuevos = null, $usuarioId = null, $observacion = null, $actaId = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO elemento_historial (elemento_id, tipo_evento, descripcion, datos_anterior, datos_nuevos, usuario_id, acta_id, observacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (int)$elementoId,
            $tipoEvento,
            $descripcion ?: null,
            $datosAnterior !== null ? json_encode($datosAnterior, JSON_UNESCAPED_UNICODE) : null,
            $datosNuevos !== null ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null,
            $usuarioId ? (int)$usuarioId : null,
            $actaId ? (int)$actaId : null,
            $observacion ?: null,
        ]);
        return true;
    } catch (Throwable $e) {
        logError("Error registrando historial: " . $e->getMessage());
        return false;
    }
}

/**
 * Devuelve el historial completo de un elemento (orden cronológico ascendente).
 */
function historialDeElemento($conn, $elementoId) {
    $stmt = $conn->prepare("SELECT h.*, u.nombre as usuario_nombre FROM elemento_historial h LEFT JOIN usuarios u ON h.usuario_id=u.id WHERE h.elemento_id=? ORDER BY h.fecha ASC, h.id ASC");
    $stmt->execute([(int)$elementoId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['datos_anterior'] = $r['datos_anterior'] ? json_decode($r['datos_anterior'], true) : null;
        $r['datos_nuevos'] = $r['datos_nuevos'] ? json_decode($r['datos_nuevos'], true) : null;
    }
    return $rows;
}

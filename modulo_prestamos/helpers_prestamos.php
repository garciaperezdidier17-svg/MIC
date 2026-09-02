<?php
/**
 * Helpers del módulo de Préstamos (lógica reutilizable).
 *
 * Sistema GENERAL de préstamos de activos institucionales:
 * cualquier elemento de inventario_general habilitado para préstamo
 * puede prestarse, sea por código individual o por cantidad.
 *
 * Mantiene el flujo de equipos existente y agrega el flujo multi-elemento
 * sobre inventario_general usando las tablas de detalle:
 *   - solicitud_elementos    (renglones solicitados)
 *   - prestamo_elementos     (activos realmente prestados, 1 fila por activo)
 *   - prestamo_recordatorios (estado de recordatorios, sin duplicados)
 *
 * Todas las reglas de disponibilidad se validan SIEMPRE en backend.
 * No confía en el frontend.
 */

require_once __DIR__ . '/../config/helpers_auditoria.php';
require_once __DIR__ . '/../modulo_inventario_general/helpers_historial.php';

const ESTADOS_PRESTAMO = ['pendiente', 'aprobado', 'activo', 'devuelto', 'vencido', 'rechazado', 'cancelado', 'extraviado'];
const ESTADOS_SOLICITUD = ['pendiente', 'aprobada', 'rechazada', 'entregada', 'devuelta', 'cancelada'];
const ESTADOS_DEVOLUCION = ['Bueno', 'Regular', 'Dañado'];

// estados del activo que NO permiten préstamo
const ESTADOS_NO_PRESTABLES = ['malo', 'dañado', 'fuera de servicio', 'baja', 'mantenimiento'];

/**
 * Devuelve true si el activo está actualmente en mantenimiento.
 */
function elementoEnMantenimiento($conn, $idElemento) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM mantenimiento WHERE elemento_id=? AND estado IN ('programado','en_proceso')");
    $stmt->execute([(int)$idElemento]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Valida que un elemento de inventario_general sea seleccionable.
 * Reglas: existe, activo, prestable, situación disponible, estado válido,
 * no en mantenimiento y (opcional) pertenece a la sede.
 * Devuelve ['ok' => bool, 'error' => string|null, 'elemento' => array|null].
 */
function validarElementoPrestable($conn, $idElemento, $idSede = null) {
    $stmt = $conn->prepare("SELECT * FROM inventario_general WHERE id=?");
    $stmt->execute([(int)$idElemento]);
    $el = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$el) {
        return ['ok' => false, 'error' => 'El elemento seleccionado no existe.', 'elemento' => null];
    }
    if ((int)$el['activo'] !== 1) {
        return ['ok' => false, 'error' => "El elemento {$el['nombre']} está dado de baja.", 'elemento' => $el];
    }
    if ((int)$el['disponible_para_prestamo'] !== 1) {
        return ['ok' => false, 'error' => "El elemento {$el['nombre']} no está habilitado para préstamo.", 'elemento' => $el];
    }
    if ((string)$el['situacion'] !== 'disponible') {
        return ['ok' => false, 'error' => "El elemento {$el['nombre']} no está disponible actualmente.", 'elemento' => $el];
    }
    if (in_array(strtolower((string)$el['estado']), ESTADOS_NO_PRESTABLES, true)) {
        return ['ok' => false, 'error' => "El elemento {$el['nombre']} está en estado " . strtolower($el['estado']) . ' y no puede prestarse.', 'elemento' => $el];
    }
    if (elementoEnMantenimiento($conn, $el['id'])) {
        return ['ok' => false, 'error' => "El elemento {$el['nombre']} está en mantenimiento.", 'elemento' => $el];
    }
    if ($idSede && !empty($el['id_sede']) && (int)$el['id_sede'] !== (int)$idSede) {
        return ['ok' => false, 'error' => "El elemento {$el['nombre']} no pertenece a la sede seleccionada.", 'elemento' => $el];
    }
    return ['ok' => true, 'error' => null, 'elemento' => $el];
}

/**
 * Cuenta disponibilidad de un grupo de elementos con el mismo nombre
 * (y misma sede cuando aplica). Un activo por fila en inventario_general.
 */
function disponibilidadGrupo($conn, $nombre, $idSede = null) {
    $where = "activo=1 AND disponible_para_prestamo=1 AND situacion='disponible' AND LOWER(nombre)=LOWER(?)";
    $params = [(string)$nombre];
    foreach (ESTADOS_NO_PRESTABLES as $e) {
        $where .= ' AND estado<>?';
        $params[] = $e;
    }
    if ($idSede) {
        $where .= ' AND id_sede=?';
        $params[] = (int)$idSede;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventario_general WHERE $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * Verifica disponibilidad real de una línea solicitada.
 * - individual (cantidad <= 1): el activo concreto debe estar disponible.
 * - cantidad: deben existir al menos $cantidad activos disponibles del grupo.
 * Devuelve ['ok','error','disponible'].
 */
function verificarDisponibilidad($conn, $idElemento, $cantidad = 1, $idSede = null) {
    $v = validarElementoPrestable($conn, $idElemento, $idSede);
    if (!$v['ok']) {
        return ['ok' => false, 'error' => $v['error'], 'disponible' => 0];
    }
    $el = $v['elemento'];
    if ($cantidad <= 1) {
        return ['ok' => true, 'error' => null, 'disponible' => 1];
    }
    $disponible = disponibilidadGrupo($conn, $el['nombre'], $idSede ?: $el['id_sede']);
    if ((int)$cantidad > $disponible) {
        return [
            'ok' => false,
            'error' => "Cantidad no disponible. Actualmente hay $disponible unidades disponibles de {$el['nombre']}.",
            'disponible' => $disponible,
        ];
    }
    return ['ok' => true, 'error' => null, 'disponible' => $disponible];
}

/**
 * Grupos de elementos prestables para el formulario de solicitud.
 * Agrupa por nombre y muestra la cantidad disponible.
 */
function gruposElementosPrestables($conn, $idSede = null) {
    $where = "ig.activo=1 AND ig.disponible_para_prestamo=1 AND ig.situacion='disponible'";
    $params = [];
    foreach (ESTADOS_NO_PRESTABLES as $e) {
        $where .= ' AND ig.estado<>?';
        $params[] = $e;
    }
    $where .= " AND NOT EXISTS (SELECT 1 FROM mantenimiento m WHERE m.elemento_id=ig.id AND m.estado IN ('programado','en_proceso'))";
    if ($idSede) {
        $where .= ' AND ig.id_sede=?';
        $params[] = (int)$idSede;
    }
    $sql = "SELECT ig.nombre, ig.tipo, ig.categoria,
                   MIN(ig.id) as representante_id,
                   GROUP_CONCAT(DISTINCT ig.codigo_interno SEPARATOR ', ') as codigos_internos,
                   GROUP_CONCAT(DISTINCT COALESCE(ig.ubicacion,'') SEPARATOR ', ') as ubicaciones,
                   COUNT(*) as disponibles
            FROM inventario_general ig
            WHERE $where
            GROUP BY ig.nombre, ig.tipo, ig.categoria
            ORDER BY ig.nombre";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Lista de profesionales (responsables) activos de una sede.
 * Sede -> Profesores (relación existente del módulo de sedes).
 */
function profesoresDeSede($conn, $idSede) {
    $stmt = $conn->prepare("SELECT id, nombre, apellido, correo FROM profesores WHERE sede_id=? AND estado='Activo' ORDER BY nombre, apellido");
    $stmt->execute([(int)$idSede]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sedesActivas($conn) {
    return $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Valida la relación Sede -> Profesor en backend.
 */
function validarProfesorDeSede($conn, $idProfesor, $idSede) {
    if (!$idProfesor || !$idSede) {
        return false;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM profesores WHERE id=? AND sede_id=? AND estado='Activo'");
    $stmt->execute([(int)$idProfesor, (int)$idSede]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Crea una solicitud de préstamo (multi-elemento).
 * $datos: usuario_id, id_profesor, id_sede, motivo, fecha_prestamo,
 *         hora_prestamo, fecha_devolucion_esperada, hora_devolucion_esperada,
 *         observaciones, items[] = [['elemento_id'=>, 'cantidad'=>, 'tipo_prestamo'=>, 'observaciones'=>]]
 * Devuelve ['ok'=>bool, 'error'=>string|null, 'solicitud_id'=>int|null, 'errores'=>array].
 */
function crearSolicitud($conn, array $datos) {
    $usuarioId = (int)($datos['usuario_id'] ?? 0);
    $idProfesor = (int)($datos['id_profesor'] ?? 0);
    $idSede = (int)($datos['id_sede'] ?? 0);
    $motivo = trim((string)($datos['motivo'] ?? ''));
    $fechaPrestamo = (string)($datos['fecha_prestamo'] ?? '');
    $horaPrestamo = (string)($datos['hora_prestamo'] ?? '');
    $fechaDev = (string)($datos['fecha_devolucion_esperada'] ?? '');
    $horaDev = (string)($datos['hora_devolucion_esperada'] ?? '');
    $observaciones = trim((string)($datos['observaciones'] ?? ''));
    $items = (array)($datos['items'] ?? []);

    $errores = [];

    if (!$usuarioId) { $errores[] = 'Usuario no identificado.'; }
    if (!$idSede) { $errores[] = 'Debe seleccionar la sede.'; }
    else {
        $sedeOk = $conn->prepare("SELECT COUNT(*) FROM sedes WHERE id=? AND activo=1");
        $sedeOk->execute([$idSede]);
        if ((int)$sedeOk->fetchColumn() === 0) { $errores[] = 'La sede seleccionada no es válida.'; }
    }
    if (!$idProfesor) { $errores[] = 'Debe seleccionar el responsable / solicitante.'; }
    elseif (!$errores && !validarProfesorDeSede($conn, $idProfesor, $idSede)) {
        $errores[] = 'El profesor no pertenece a la sede seleccionada.';
    }
    if ($motivo === '') { $errores[] = 'El motivo del préstamo es obligatorio.'; }
    if ($fechaPrestamo === '') { $errores[] = 'Debe indicar la fecha del préstamo.'; }
    if ($fechaDev !== '' && $fechaPrestamo !== '' && strtotime($fechaDev) < strtotime($fechaPrestamo)) {
        $errores[] = 'La fecha de devolución no puede ser anterior a la fecha del préstamo.';
    }
    if (count($items) === 0) {
        $errores[] = 'Debe agregar al menos un elemento a la solicitud.';
    }

    // Validar cada renglón (backend: disponibilidad real)
    foreach ($items as $i => $item) {
        $idEl = (int)($item['elemento_id'] ?? 0);
        $cant = (int)($item['cantidad'] ?? 1);
        $tipo = ($item['tipo_prestamo'] ?? 'individual') === 'cantidad' ? 'cantidad' : 'individual';
        if ($idEl <= 0) { $errores[] = "El renglón " . ($i + 1) . " no tiene un elemento válido."; continue; }
        if ($tipo === 'cantidad') {
            if ($cant < 2) { $errores[] = "Para el renglón " . ($i + 1) . " la cantidad debe ser mayor a 1."; continue; }
            $d = verificarDisponibilidad($conn, $idEl, $cant, $idSede);
        } else {
            $d = verificarDisponibilidad($conn, $idEl, 1, $idSede);
        }
        if (!$d['ok']) {
            $errores[] = $d['error'];
        }
    }

    if ($errores) {
        return ['ok' => false, 'error' => implode(' ', $errores), 'solicitud_id' => null, 'errores' => $errores];
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_estudiante, id_profesor, id_sede, id_equipo, fecha_solicitud, hora_solicitud, motivo, fecha_prestamo, hora_prestamo, fecha_devolucion_esperada, hora_devolucion_esperada, observaciones, estado)
             VALUES (?, NULL, ?, ?, NULL, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, 'pendiente')"
        );
        $stmt->execute([$usuarioId, $idProfesor, $idSede, $motivo, $fechaPrestamo ?: date('Y-m-d'), $horaPrestamo ?: '00:00:00', $fechaDev ?: null, $horaDev ?: null, $observaciones ?: null]);
        $idSolicitud = (int)$conn->lastInsertId();

        $ins = $conn->prepare("INSERT INTO solicitud_elementos (id_solicitud, id_elemento, cantidad, tipo_prestamo, observaciones) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $idEl = (int)$item['elemento_id'];
            $cant = (int)($item['cantidad'] ?? 1);
            $tipo = ($item['tipo_prestamo'] ?? 'individual') === 'cantidad' ? 'cantidad' : 'individual';
            $obs = trim((string)($item['observaciones'] ?? '')) ?: null;
            $ins->execute([$idSolicitud, $idEl, $tipo === 'cantidad' ? $cant : 1, $tipo, $obs]);
        }

        registrarAuditoria($conn, 'crear_solicitud', 'prestamos', 'solicitud', $idSolicitud,
            'Solicitud de préstamo creada', null,
            ['sede' => $idSede, 'profesor' => $idProfesor, 'items' => count($items)]);

        $conn->commit();
        return ['ok' => true, 'error' => null, 'solicitud_id' => $idSolicitud, 'errores' => []];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        logError('crearSolicitud: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo guardar la solicitud. Intente nuevamente.', 'solicitud_id' => null, 'errores' => []];
    }
}

/**
 * Reserva un activo concreto dentro de un préstamo.
 * Marca situacion='prestado', registra historial y crea la fila de detalle.
 */
function reservarActivoEnPrestamo($conn, $idPrestamo, $idElemento, $tipo, $usuarioId) {
    $stmt = $conn->prepare("SELECT id, codigo_interno, nombre FROM inventario_general WHERE id=?");
    $stmt->execute([(int)$idElemento]);
    $el = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$el) {
        throw new RuntimeException('Elemento no encontrado para reservar.');
    }
    $conn->prepare("UPDATE inventario_general SET situacion='prestado' WHERE id=?")->execute([(int)$idElemento]);

    $conn->prepare("INSERT INTO prestamo_elementos (id_prestamo, id_elemento, cantidad, tipo_prestamo, codigo_interno) VALUES (?, ?, 1, ?, ?)")
        ->execute([(int)$idPrestamo, (int)$idElemento, $tipo, $el['codigo_interno'] ?: null]);

    registrarEventoHistorial($conn, (int)$idElemento, 'prestamo',
        "Entregado en préstamo #$idPrestamo",
        ['situacion' => 'disponible'],
        ['situacion' => 'prestado', 'prestamo_id' => (int)$idPrestamo],
        $usuarioId ?: null);
}

/**
 * Reserva $cantidad activos disponibles del grupo con nombre $nombre.
 */
function reservarGrupoActivados($conn, $idPrestamo, $representanteId, $nombre, $cantidad, $idSede, $usuarioId) {
    $where = "activo=1 AND disponible_para_prestamo=1 AND situacion='disponible' AND LOWER(nombre)=LOWER(?)";
    $params = [(string)$nombre];
    foreach (ESTADOS_NO_PRESTABLES as $e) {
        $where .= ' AND estado<>?';
        $params[] = $e;
    }
    if ($idSede) {
        $where .= ' AND id_sede=?';
        $params[] = (int)$idSede;
    }
    // nunca reservar el representante: se reserva por separado
    $where .= ' AND id<>?';
    $params[] = (int)$representanteId;

    $stmt = $conn->prepare("SELECT id FROM inventario_general WHERE $where ORDER BY id");
    $stmt->execute($params);
    $ids = array_slice($stmt->fetchAll(PDO::FETCH_COLUMN), 0, (int)$cantidad);

    if (count($ids) < (int)$cantidad) {
        throw new RuntimeException("No hay suficientes unidades disponibles de $nombre.");
    }
    foreach ($ids as $elId) {
        $conn->prepare("UPDATE inventario_general SET situacion='prestado' WHERE id=?")->execute([(int)$elId]);
        $stmtC = $conn->prepare("SELECT codigo_interno FROM inventario_general WHERE id=?");
        $stmtC->execute([(int)$elId]);
        $cod = $stmtC->fetchColumn();
        $conn->prepare("INSERT INTO prestamo_elementos (id_prestamo, id_elemento, cantidad, tipo_prestamo, codigo_interno) VALUES (?, ?, 1, 'cantidad', ?)")
            ->execute([(int)$idPrestamo, (int)$elId, $cod ?: null]);
        registrarEventoHistorial($conn, (int)$elId, 'prestamo',
            "Entregado en préstamo #$idPrestamo (cantidad)",
            ['situacion' => 'disponible'],
            ['situacion' => 'prestado', 'prestamo_id' => (int)$idPrestamo],
            $usuarioId ?: null);
    }
}

/**
 * Aprobación de solicitud: valida en backend la disponibilidad y genera el
 * préstamo activo reservando los activos. Si algo no está disponible,
 * la solicitud NO se aprueba (queda pendiente para rechazo manual).
 */
function aprobarSolicitud($conn, $adminId, $idSolicitud) {
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM solicitudes WHERE id=?");
        $stmt->execute([(int)$idSolicitud]);
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sol) { throw new RuntimeException('La solicitud no existe.'); }
        if ($sol['estado'] !== 'pendiente') { throw new RuntimeException('La solicitud no está en estado pendiente.'); }

        $stmt = $conn->prepare("SELECT * FROM solicitud_elementos WHERE id_solicitud=? ORDER BY id");
        $stmt->execute([(int)$idSolicitud]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) { throw new RuntimeException('La solicitud no tiene elementos.'); }

        foreach ($items as $item) {
            $d = verificarDisponibilidad($conn, (int)$item['id_elemento'], (int)$item['cantidad'], (int)$sol['id_sede']);
            if (!$d['ok']) {
                throw new RuntimeException($d['error']);
            }
        }

        $conn->prepare("UPDATE solicitudes SET estado='aprobada', fecha_atencion=NOW(), id_atendido=? WHERE id=?")
            ->execute([(int)$adminId, (int)$idSolicitud]);

        $fechaPrestamo = $sol['fecha_prestamo'] ?: date('Y-m-d');
        $horaPrestamo = $sol['hora_prestamo'] ?: '00:00:00';
        $fechaDev = $sol['fecha_devolucion_esperada'] ?: date('Y-m-d', strtotime('+7 days', strtotime($fechaPrestamo)));

        $conn->prepare(
            "INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, id_profesor, id_sede, fecha_prestamo, hora_prestamo, fecha_devolucion_esperada, estado)
             VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, 'activo')"
        )->execute([(int)$idSolicitud, $sol['id_profesor'] ? (int)$sol['id_profesor'] : null, (int)$sol['id_sede'], $fechaPrestamo, $horaPrestamo, $fechaDev]);
        $idPrestamo = (int)$conn->lastInsertId();

        foreach ($items as $item) {
            $idEl = (int)$item['id_elemento'];
            $cant = (int)$item['cantidad'];
            $tipo = $item['tipo_prestamo'] === 'cantidad' ? 'cantidad' : 'individual';
            if ($tipo === 'cantidad') {
                $stmtN = $conn->prepare("SELECT nombre, id_sede FROM inventario_general WHERE id=?");
                $stmtN->execute([$idEl]);
                $rep = $stmtN->fetch(PDO::FETCH_ASSOC);
                // reservar $cant - 1 adicionales (el representante se reserva aparte)
                reservarGrupoActivados($conn, $idPrestamo, $idEl, $rep['nombre'], $cant - 1, $rep['id_sede'], (int)$adminId);
                reservarActivoEnPrestamo($conn, $idPrestamo, $idEl, 'cantidad', (int)$adminId);
            } else {
                reservarActivoEnPrestamo($conn, $idPrestamo, $idEl, 'individual', (int)$adminId);
            }
        }

        foreach (['3_dias', '1_dia', 'hoy', 'vencido'] as $tipoR) {
            $conn->prepare("INSERT IGNORE INTO prestamo_recordatorios (id_prestamo, tipo) VALUES (?, ?)")
                ->execute([$idPrestamo, $tipoR]);
        }

        registrarAuditoria($conn, 'aprobar_solicitud', 'prestamos', 'solicitud', (int)$idSolicitud,
            "Solicitud #$idSolicitud aprobada -> préstamo #$idPrestamo",
            ['estado' => 'pendiente'], ['estado' => 'aprobada', 'prestamo_id' => $idPrestamo]);
        registrarAuditoria($conn, 'prestamo_creado', 'prestamos', 'prestamo', $idPrestamo,
            "Préstamo #$idPrestamo creado a partir de la solicitud #$idSolicitud", null,
            ['solicitud_id' => (int)$idSolicitud, 'elementos' => count($items)]);

        $conn->commit();
        return ['ok' => true, 'error' => null, 'prestamo_id' => $idPrestamo, 'solicitud_id' => (int)$idSolicitud];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        logError('aprobarSolicitud: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage(), 'prestamo_id' => null, 'solicitud_id' => (int)$idSolicitud];
    }
}

function rechazarSolicitud($conn, $adminId, $idSolicitud) {
    $stmt = $conn->prepare("SELECT estado FROM solicitudes WHERE id=?");
    $stmt->execute([(int)$idSolicitud]);
    $estado = $stmt->fetchColumn();
    if ($estado === false) {
        return ['ok' => false, 'error' => 'La solicitud no existe.'];
    }
    if ($estado !== 'pendiente') {
        return ['ok' => false, 'error' => 'Solo se pueden rechazar solicitudes pendientes.'];
    }
    $conn->prepare("UPDATE solicitudes SET estado='rechazada', fecha_atencion=NOW(), id_atendido=? WHERE id=?")
        ->execute([(int)$adminId, (int)$idSolicitud]);
    registrarAuditoria($conn, 'rechazar_solicitud', 'prestamos', 'solicitud', (int)$idSolicitud,
        "Solicitud #$idSolicitud rechazada", ['estado' => 'pendiente'], ['estado' => 'rechazada']);
    return ['ok' => true, 'error' => null];
}

/**
 * Registrar devolución (parcial o total) de un préstamo.
 * $detalles[id_detalle] = ['estado'=>'Bueno|Regular|Dañado','observaciones'=>,'evidencia'=>]
 */
function registrarDevolucion($conn, $usuarioId, $idPrestamo, array $detalles) {
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM prestamos WHERE id=?");
        $stmt->execute([(int)$idPrestamo]);
        $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prestamo) { throw new RuntimeException('El préstamo no existe.'); }
        if (!in_array($prestamo['estado'], ['activo', 'vencido', 'aprobado'], true)) {
            throw new RuntimeException('El préstamo no admite devolución en su estado actual.');
        }

        $items = $conn->prepare("SELECT * FROM prestamo_elementos WHERE id_prestamo=?");
        $items->execute([(int)$idPrestamo]);
        $renglones = $items->fetchAll(PDO::FETCH_ASSOC);
        if (!$renglones) { throw new RuntimeException('El préstamo no tiene elementos.'); }

        $hayDano = false;
        $penalizado = false;
        $lista = [];
        $devueltosAhora = 0;
        foreach ($renglones as $renglon) {
            if (!empty($renglon['estado_devolucion'])) { continue; } // Ya devuelto
            if (!isset($detalles[(int)$renglon['id']])) { continue; } // No seleccionado para devolver ahora
            
            $det = $detalles[(int)$renglon['id']];
            $estadoDev = ($det['estado'] ?? 'Bueno');
            if (!in_array($estadoDev, ESTADOS_DEVOLUCION, true)) {
                $estadoDev = 'Bueno';
            }
            $obs = trim((string)($det['observaciones'] ?? '')) ?: null;
            $evidencia = $det['evidencia'] ?? $renglon['evidencia_foto'];

            $conn->prepare("UPDATE prestamo_elementos SET estado_devolucion=?, observaciones_devolucion=?, evidencia_foto=? WHERE id=?")
                ->execute([$estadoDev, $obs, $evidencia ?: null, (int)$renglon['id']]);

            $eStmt = $conn->prepare("SELECT * FROM inventario_general WHERE id=?");
            $eStmt->execute([(int)$renglon['id_elemento']]);
            $el = $eStmt->fetch(PDO::FETCH_ASSOC);
            if (!$el) { continue; }

            $nuevoEstado = match ($estadoDev) {
                'Dañado' => 'malo',
                'Regular' => 'regular',
                default => 'bueno',
            };
            // Si está perdido, lo dejamos como no disponible y reportamos
            $nuevaSituacion = ($estadoDev === 'Perdido') ? 'perdido' : 'disponible';
            if ($estadoDev === 'Perdido') { $nuevoEstado = 'malo'; }
            
            $conn->prepare("UPDATE inventario_general SET situacion=?, estado=? WHERE id=?")
                ->execute([$nuevaSituacion, $nuevoEstado, (int)$el['id']]);

            registrarEventoHistorial($conn, (int)$el['id'], 'devolucion',
                "Devolución del préstamo #$idPrestamo (estado: $estadoDev)",
                ['situacion' => 'prestado'],
                ['situacion' => $nuevaSituacion, 'estado' => $nuevoEstado, 'estado_devolucion' => $estadoDev, 'prestamo_id' => (int)$idPrestamo],
                $usuarioId ?: null);

            registrarAuditoria($conn, 'elemento_devuelto', 'prestamos', 'elemento', (int)$el['id'],
                ($el['codigo_interno'] ?: $el['nombre']) . " devuelto en préstamo #$idPrestamo (estado: $estadoDev)",
                ['situacion' => 'prestado'], ['situacion' => $nuevaSituacion, 'estado_devolucion' => $estadoDev]);

            if ($estadoDev === 'Dañado' || $estadoDev === 'Perdido') {
                $hayDano = true;
                registrarAuditoria($conn, 'devolucion_con_dano', 'prestamos', 'elemento', (int)$el['id'],
                    ($el['codigo_interno'] ?: $el['nombre']) . " devuelto con estado $estadoDev (préstamo #$idPrestamo)", null,
                    ['prestamo_id' => (int)$idPrestamo]);
                // Notificación para administradores
                $admins = $conn->query("SELECT id FROM usuarios WHERE rol='admin' AND activo=1")->fetchAll(PDO::FETCH_COLUMN);
                $not = $conn->prepare("INSERT INTO notificaciones (id_usuario, tipo, titulo, mensaje) VALUES (?, 'alerta', ?, ?)");
                foreach ($admins as $adminId) {
                    $not->execute([(int)$adminId, "Devolución: $estadoDev",
                        ($el['codigo_interno'] ?: $el['nombre']) . " fue devuelto con estado $estadoDev en el préstamo #$idPrestamo."]);
                }
            }
            if ($estadoDev === 'Regular' || $estadoDev === 'Dañado' || $estadoDev === 'Perdido') {
                $penalizado = true;
            }
            $lista[] = $el['nombre'];
            $devueltosAhora++;
        }
        
        if ($devueltosAhora === 0) {
            throw new RuntimeException('No se procesó ningún elemento válido.');
        }

        // Verificar cuántos faltan por devolver
        $faltantes = $conn->prepare("SELECT COUNT(*) FROM prestamo_elementos WHERE id_prestamo=? AND (estado_devolucion IS NULL OR estado_devolucion='')");
        $faltantes->execute([(int)$idPrestamo]);
        $cantidadFaltantes = (int)$faltantes->fetchColumn();

        $estadoFinalPrestamo = ($cantidadFaltantes > 0) ? 'parcialmente devuelto' : 'devuelto';
        
        // Si hay daños previos o actuales, mantenemos registro
        $prev = $conn->query("SELECT estado_devolucion FROM prestamos WHERE id=$idPrestamo")->fetchColumn();
        $estadoGlobalDev = $hayDano ? 'Dañado' : ($penalizado ? 'Regular' : ($prev ?: 'Bueno'));

        $conn->prepare("UPDATE prestamos SET fecha_devolucion_real=CURDATE(), hora_devolucion=CURTIME(), estado=?, estado_devolucion=? WHERE id=?")
            ->execute([$estadoFinalPrestamo, $estadoGlobalDev, (int)$idPrestamo]);
        
        if ($estadoFinalPrestamo === 'devuelto') {
            $conn->prepare("UPDATE prestamo_recordatorios SET enviado=1, fecha_envio=NOW() WHERE id_prestamo=?")
                ->execute([(int)$idPrestamo]);
        }

        registrarAuditoria($conn, 'devolucion_registrada', 'prestamos', 'prestamo', (int)$idPrestamo,
            "Préstamo #$idPrestamo $estadoFinalPrestamo. Elementos: " . implode(', ', array_unique($lista)), null,
            ['estado_devolucion' => $estadoGlobalDev, 'estado_prestamo' => $estadoFinalPrestamo]);

        $conn->commit();
        return ['ok' => true, 'error' => null, 'prestamo_id' => (int)$idPrestamo, 'con_dano' => $hayDano];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        logError('registrarDevolucion: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage(), 'prestamo_id' => (int)$idPrestamo, 'con_dano' => false];
    }
}

/**
 * Cancela un préstamo y libera los activos reservados.
 */
function cancelarPrestamo($conn, $usuarioId, $idPrestamo) {
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM prestamos WHERE id=?");
        $stmt->execute([(int)$idPrestamo]);
        $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prestamo) { throw new RuntimeException('El préstamo no existe.'); }
        if (!in_array($prestamo['estado'], ['pendiente', 'aprobado', 'activo', 'vencido'], true)) {
            throw new RuntimeException('El préstamo no puede cancelarse en su estado actual.');
        }

        $items = $conn->prepare("SELECT * FROM prestamo_elementos WHERE id_prestamo=?");
        $items->execute([(int)$idPrestamo]);
        $renglones = $items->fetchAll(PDO::FETCH_ASSOC);

        foreach ($renglones as $r) {
            $conn->prepare("UPDATE inventario_general SET situacion='disponible' WHERE id=?")->execute([(int)$r['id_elemento']]);
            registrarEventoHistorial($conn, (int)$r['id_elemento'], 'devolucion',
                "Préstamo #$idPrestamo cancelado",
                ['situacion' => 'prestado'], ['situacion' => 'disponible', 'prestamo_id' => (int)$idPrestamo],
                $usuarioId ?: null);
        }

        $conn->prepare("UPDATE prestamos SET estado='cancelado' WHERE id=?")->execute([(int)$idPrestamo]);
        $conn->prepare("UPDATE prestamo_recordatorios SET enviado=1, fecha_envio=NOW() WHERE id_prestamo=?")->execute([(int)$idPrestamo]);
        registrarAuditoria($conn, 'prestamo_cancelado', 'prestamos', 'prestamo', (int)$idPrestamo,
            "Préstamo #$idPrestamo cancelado", ['estado' => $prestamo['estado']], ['estado' => 'cancelado']);

        $conn->commit();
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        logError('cancelarPrestamo: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Marca como vencidos los préstamos activos cuya fecha de devolución ya pasó.
 * Registra auditoría (una sola vez por préstamo mediante recordatorio 'vencido').
 */
function detectarVencidos($conn, $hoy = null) {
    $hoy = $hoy ?: date('Y-m-d');
    $stmt = $conn->prepare("SELECT id FROM prestamos WHERE estado IN ('activo', 'parcialmente devuelto') AND fecha_devolucion_esperada < ?");
    $stmt->execute([$hoy]);
    $vencidos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $detectados = [];
    foreach ($vencidos as $idPrestamo) {
        $existe = $conn->prepare("SELECT COUNT(*) FROM prestamo_recordatorios WHERE id_prestamo=? AND tipo='vencido'");
        $existe->execute([(int)$idPrestamo]);
        $yaRegistrado = (int)$existe->fetchColumn() > 0;

        $conn->prepare("UPDATE prestamos SET estado='vencido' WHERE id=?")->execute([(int)$idPrestamo]);
        $conn->prepare("INSERT IGNORE INTO prestamo_recordatorios (id_prestamo, tipo, enviado, fecha_envio) VALUES (?, 'vencido', 1, NOW())")->execute([(int)$idPrestamo]);

        if (!$yaRegistrado) {
            registrarAuditoria($conn, 'prestamo_vencido', 'prestamos', 'prestamo', (int)$idPrestamo,
                "Préstamo #$idPrestamo detectado como vencido", ['estado' => 'activo'], ['estado' => 'vencido']);
        }
        $detectados[] = (int)$idPrestamo;
    }
    return $detectados;
}

/**
 * Genera los recordatorios pendientes para préstamos activos (sin duplicados).
 * Tipos: 3_dias, 1_dia, hoy, vencido. Persiste estado en prestamo_recordatorios.
 */
function generarRecordatorios($conn, $hoy = null) {
    $hoy = $hoy ?: date('Y-m-d');
    $creados = [];
    $stmt = $conn->prepare(
        "SELECT id, fecha_devolucion_esperada FROM prestamos
         WHERE estado IN ('activo', 'parcialmente devuelto') AND fecha_devolucion_esperada IS NOT NULL"
    );
    $stmt->execute();
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ins = $conn->prepare("INSERT IGNORE INTO prestamo_recordatorios (id_prestamo, tipo, enviado, fecha_envio) VALUES (?, ?, 1, NOW())");
    foreach ($prestamos as $p) {
        $dif = (strtotime($p['fecha_devolucion_esperada']) - strtotime($hoy)) / 86400;
        $tipo = null;
        if ($dif < 0) {
            $tipo = 'vencido';
        } elseif ($dif == 0) {
            $tipo = 'hoy';
        } elseif ($dif <= 1) {
            $tipo = '1_dia';
        } elseif ($dif <= 3) {
            $tipo = '3_dias';
        }
        if ($tipo) {
            $ins->execute([(int)$p['id'], $tipo]);
            $creados[] = ['prestamo_id' => (int)$p['id'], 'tipo' => $tipo];
        }
    }
    return $creados;
}

/**
 * Elementos entregados en préstamo (detalle con datos del activo).
 */
function elementosDePrestamo($conn, $idPrestamo) {
    $stmt = $conn->prepare(
        "SELECT pe.*, ig.nombre, ig.codigo_interno as codigo_ig, ig.tipo, ig.estado as estado_activo, ig.situacion
         FROM prestamo_elementos pe
         JOIN inventario_general ig ON pe.id_elemento = ig.id
         WHERE pe.id_prestamo = ?
         ORDER BY ig.nombre"
    );
    $stmt->execute([(int)$idPrestamo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Historial de préstamos de un elemento (nunca se sobrescribe).
 */
function historialPrestamosElemento($conn, $idElemento) {
    $eventos = $conn->prepare(
        "SELECT h.id, h.tipo_evento, h.descripcion, h.datos_anterior, h.datos_nuevos, h.usuario_id, h.fecha,
                u.nombre as usuario_nombre
         FROM elemento_historial h
         LEFT JOIN usuarios u ON h.usuario_id = u.id
         WHERE h.elemento_id = ? AND h.tipo_evento IN ('prestamo','devolucion')
         ORDER BY h.fecha ASC, h.id ASC"
    );
    $eventos->execute([(int)$idElemento]);
    return $eventos->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Resumen de préstamos para el panel (tarjetas).
 * $scope: arreglo con filtro opcional por sede / profesor responsable.
 */
function statsPrestamos($conn, array $scope = []) {
    $where = '1=1';
    $params = [];
    if (!empty($scope['usuario_profesor'])) {
        $where .= ' AND id_profesor=?';
        $params[] = (int)$scope['usuario_profesor'];
    }
    if (!empty($scope['id_sede'])) {
        $where .= ' AND id_sede=?';
        $params[] = (int)$scope['id_sede'];
    }

    $q = function ($extra = '') use ($conn, $params, $where) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM prestamos WHERE $where $extra");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    };

    return [
        'activos'   => $q("AND estado IN ('activo', 'parcialmente devuelto')"),
        'proximos'  => $q("AND estado IN ('activo', 'parcialmente devuelto') AND fecha_devolucion_esperada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)"),
        'vence_hoy' => $q("AND estado IN ('activo', 'parcialmente devuelto') AND fecha_devolucion_esperada = CURDATE()"),
        'vencidos'  => $q("AND (estado='vencido' OR (estado IN ('activo', 'parcialmente devuelto') AND fecha_devolucion_esperada < CURDATE()))"),
        'devueltos' => $q("AND estado='devuelto'"),
        'pendientes'=> $q("AND estado IN ('pendiente','aprobado')"),
        'total'     => $q(''),
    ];
}

/**
 * Listado de préstamos con filtros para la tabla del panel.
 * Filtros: estado, sede, responsable, tipo_elemento, fecha_desde, fecha_hasta,
 *          vencidos=1, proximos=1, usuario_id (scope), buscar.
 */
function listarPrestamos($conn, array $filtros = [], $page = 1, $porPagina = 15) {
    $where = ['1=1'];
    $params = [];

    if (!empty($filtros['estado'])) { $where[] = 'p.estado=?'; $params[] = $filtros['estado']; }
    if (!empty($filtros['sede'])) { $where[] = 'p.id_sede=?'; $params[] = (int)$filtros['sede']; }
    if (!empty($filtros['responsable'])) { $where[] = 'p.id_profesor=?'; $params[] = (int)$filtros['responsable']; }
    if (!empty($filtros['tipo_elemento'])) { $where[] = 'ig.tipo=?'; $params[] = $filtros['tipo_elemento']; }
    if (!empty($filtros['fecha_desde'])) { $where[] = 'p.fecha_prestamo>=?'; $params[] = $filtros['fecha_desde']; }
    if (!empty($filtros['fecha_hasta'])) { $where[] = 'p.fecha_prestamo<=?'; $params[] = $filtros['fecha_hasta']; }
    if (!empty($filtros['vencidos'])) { $where[] = "p.estado='vencido'"; }
    if (!empty($filtros['proximos'])) { $where[] = "p.estado IN ('activo', 'parcialmente devuelto') AND p.fecha_devolucion_esperada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)"; }
    if (!empty($filtros['usuario_id'])) { $where[] = 'p.id_profesor=?'; $params[] = (int)$filtros['usuario_id']; }
    if (!empty($filtros['buscar'])) {
        $where[] = '(CONCAT(p.id, "") LIKE ? OR CONCAT(COALESCE(pr.nombre,"")," ",COALESCE(pr.apellido,"")) LIKE ? OR ig.nombre LIKE ?)';
        $b = '%' . $filtros['buscar'] . '%';
        $params[] = $b; $params[] = $b; $params[] = $b;
    }

    $whereSql = implode(' AND ', $where);

    $total = $conn->prepare("SELECT COUNT(DISTINCT p.id) FROM prestamos p LEFT JOIN prestamo_elementos pe ON pe.id_prestamo=p.id LEFT JOIN inventario_general ig ON pe.id_elemento=ig.id LEFT JOIN profesores pr ON p.id_profesor=pr.id WHERE $whereSql");
    $total->execute($params);
    $totalRegistros = (int)$total->fetchColumn();

    $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
    $page = max(1, (int)$page);
    if ($page > $totalPaginas) { $page = $totalPaginas; }
    $offset = ($page - 1) * $porPagina;

    $sql = "SELECT DISTINCT p.*, pr.nombre as profesor_nombre, pr.apellido as profesor_apellido, s.nombre as sede_nombre,
                   u.nombre as estudiante_nombre, e.nombre as equipo_nombre
            FROM prestamos p
            LEFT JOIN profesores pr ON p.id_profesor = pr.id
            LEFT JOIN sedes s ON p.id_sede = s.id
            LEFT JOIN usuarios u ON p.id_estudiante = u.id
            LEFT JOIN equipos e ON p.id_equipo = e.id
            LEFT JOIN prestamo_elementos pe ON pe.id_prestamo = p.id
            LEFT JOIN inventario_general ig ON pe.id_elemento = ig.id
            WHERE $whereSql
            ORDER BY p.fecha_prestamo DESC, p.id DESC
            LIMIT $porPagina OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // elementos agregados por préstamo
    foreach ($rows as &$r) {
        $r['elementos'] = elementosDePrestamo($conn, (int)$r['id']);
        $r['elementos_txt'] = formatearElementos($r['elementos']);
    }
    unset($r);

    return [
        'rows' => $rows,
        'total' => $totalRegistros,
        'paginas' => $totalPaginas,
        'page' => $page,
        'por_pagina' => $porPagina,
    ];
}

function formatearElementos(array $elementos) {
    if (!$elementos) {
        return '—';
    }
    $grouped = [];
    foreach ($elementos as $el) {
        $key = $el['nombre'];
        if (!isset($grouped[$key])) { $grouped[$key] = 0; }
        $grouped[$key]++;
    }
    $partes = [];
    foreach ($grouped as $nombre => $cnt) {
        $partes[] = $cnt > 1 ? "$nombre x$cnt" : $nombre;
    }
    return implode(', ', $partes);
}

/**
 * Consultas para el módulo de reportes (formatos existentes).
 */
function consultasReportesPrestamos($conn) {
    return [
        'prestamos_activos' => [
            'titulo' => 'Préstamos Activos',
            'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, p.fecha_prestamo, p.hora_prestamo, p.fecha_devolucion_esperada, p.estado
                      FROM prestamos p LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                      WHERE p.estado='activo' ORDER BY p.fecha_prestamo DESC",
            'headers' => ['ID','Responsable','Sede','Fecha Préstamo','Hora','Devolución Esperada','Estado'],
        ],
        'prestamos_vencidos' => [
            'titulo' => 'Préstamos Vencidos',
            'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado
                      FROM prestamos p LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                      WHERE p.estado='vencido' ORDER BY p.fecha_devolucion_esperada ASC",
            'headers' => ['ID','Responsable','Sede','Fecha Préstamo','Devolución Esperada','Estado'],
        ],
        'prestamos_devueltos' => [
            'titulo' => 'Préstamos Devueltos',
            'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, p.fecha_prestamo, p.fecha_devolucion_real, p.fecha_devolucion_esperada, p.estado_devolucion
                      FROM prestamos p LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                      WHERE p.estado='devuelto' ORDER BY p.fecha_devolucion_real DESC",
            'headers' => ['ID','Responsable','Sede','Fecha Préstamo','Devolución Real','Esperada','Estado Devolución'],
        ],
        'elementos_mas_prestados' => [
            'titulo' => 'Elementos Más Prestados',
            'sql' => "SELECT ig.nombre, ig.tipo, COUNT(pe.id) as veces_prestado
                      FROM prestamo_elementos pe JOIN inventario_general ig ON pe.id_elemento=ig.id
                      GROUP BY ig.id ORDER BY veces_prestado DESC LIMIT 20",
            'headers' => ['Elemento','Tipo','Veces Prestado'],
        ],
        'responsables_con_prestamos' => [
            'titulo' => 'Responsables con Préstamos',
            'sql' => "SELECT CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede, COUNT(p.id) as total_prestamos
                      FROM prestamos p JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                      GROUP BY p.id_profesor ORDER BY total_prestamos DESC",
            'headers' => ['Responsable','Sede','Total Préstamos'],
        ],
        'prestamos_por_sede' => [
            'titulo' => 'Préstamos por Sede',
            'sql' => "SELECT s.nombre as sede, COUNT(p.id) as total, SUM(CASE WHEN p.estado='activo' THEN 1 ELSE 0 END) as activos
                      FROM prestamos p LEFT JOIN sedes s ON p.id_sede=s.id
                      GROUP BY p.id_sede ORDER BY total DESC",
            'headers' => ['Sede','Total','Activos'],
        ],
        'historial_prestamos' => [
            'titulo' => 'Historial de Préstamos',
            'sql' => "SELECT p.id, CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable, s.nombre as sede,
                             GROUP_CONCAT(DISTINCT CONCAT(ig.nombre) ORDER BY ig.nombre SEPARATOR ', ') as elementos,
                             p.fecha_prestamo, p.fecha_devolucion_esperada, p.fecha_devolucion_real, p.estado
                      FROM prestamos p
                      LEFT JOIN prestamo_elementos pe ON pe.id_prestamo=p.id
                      LEFT JOIN inventario_general ig ON pe.id_elemento=ig.id
                      LEFT JOIN profesores pr ON p.id_profesor=pr.id LEFT JOIN sedes s ON p.id_sede=s.id
                      GROUP BY p.id ORDER BY p.fecha_prestamo DESC, p.id DESC",
            'headers' => ['ID','Responsable','Sede','Elementos','Fecha Préstamo','Devolución Esperada','Devolución Real','Estado'],
        ],
    ];
}

/**
 * Procesa las alertas de préstamos (3_dias, 1_dia, hoy, vencido) y marca los préstamos como vencidos
 * Registra alertas en prestamo_recordatorios y la auditoría.
 */
function procesarAlertasAutomaticasPrestamos($conn) {
    $stmt = $conn->prepare("SELECT id, id_profesor, id_estudiante, fecha_devolucion_esperada, estado FROM prestamos WHERE estado IN ('activo', 'parcialmente devuelto') AND fecha_devolucion_esperada IS NOT NULL");
    $stmt->execute();
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fechaActual = new DateTime();
    $fechaActual->setTime(0,0,0);

    $alertasGeneradas = 0;
    $prestamosVencidos = 0;

    foreach ($prestamos as $p) {
        $idPrestamo = (int)$p['id'];
        $estadoActual = $p['estado'];
        
        $fechaEsperada = new DateTime($p['fecha_devolucion_esperada']);
        $fechaEsperada->setTime(0,0,0);
        
        $diff = $fechaActual->diff($fechaEsperada);
        // %R%a format gives the days with sign
        $diasDiferencia = (int)$diff->format('%R%a');
        
        $tipoAlerta = null;
        $descripcionAlerta = '';
        
        if ($diasDiferencia === 3) {
            $tipoAlerta = '3_dias';
            $descripcionAlerta = "Préstamo #$idPrestamo próximo a vencer en 3 días.";
        } elseif ($diasDiferencia === 1) {
            $tipoAlerta = '1_dia';
            $descripcionAlerta = "Préstamo #$idPrestamo vence mañana.";
        } elseif ($diasDiferencia === 0) {
            $tipoAlerta = 'hoy';
            $descripcionAlerta = "Préstamo #$idPrestamo vence hoy.";
        } elseif ($diasDiferencia < 0) {
            $tipoAlerta = 'vencido';
            $descripcionAlerta = "Préstamo #$idPrestamo vencido.";
        }
        
        if ($tipoAlerta !== null) {
            $stmtCheck = $conn->prepare("SELECT id FROM prestamo_recordatorios WHERE id_prestamo = ? AND tipo = ?");
            $stmtCheck->execute([$idPrestamo, $tipoAlerta]);
            if (!$stmtCheck->fetch()) {
                $stmtInsert = $conn->prepare("INSERT INTO prestamo_recordatorios (id_prestamo, tipo, enviado, fecha_envio) VALUES (?, ?, 1, NOW())");
                if ($stmtInsert->execute([$idPrestamo, $tipoAlerta])) {
                    $alertasGeneradas++;
                    if (function_exists('registrarAuditoria')) {
                        registrarAuditoria($conn, 'cambio_estado', 'prestamos', 'prestamo', $idPrestamo, "Alerta automática generada: $descripcionAlerta");
                    }
                }
            }
        }
        
        if ($diasDiferencia < 0 && $estadoActual !== 'vencido') {
            $stmtUpdate = $conn->prepare("UPDATE prestamos SET estado = 'vencido' WHERE id = ?");
            if ($stmtUpdate->execute([$idPrestamo])) {
                $prestamosVencidos++;
                if (function_exists('registrarAuditoria')) {
                    registrarAuditoria($conn, 'cambio_estado', 'prestamos', 'prestamo', $idPrestamo, "Estado actualizado a 'vencido' por proceso automático (fecha expirada).", ['estado' => $estadoActual], ['estado' => 'vencido']);
                }
            }
        }
    }

    return [
        'alertas_generadas' => $alertasGeneradas,
        'prestamos_vencidos' => $prestamosVencidos,
        'msg' => "Alertas nuevas generadas: $alertasGeneradas\nPrestamos marcados como vencidos: $prestamosVencidos\n"
    ];
}
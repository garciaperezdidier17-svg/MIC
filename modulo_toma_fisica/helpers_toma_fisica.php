<?php
/**
 * Helpers del módulo "Toma Física e Inspección de Activos".
 * Reutiliza el historial existente (elemento_historial) y las validaciones
 * de archivos del inventario general.
 */

require_once __DIR__ . '/../modulo_inventario_general/helpers_historial.php';
require_once __DIR__ . '/../modulo_inventario_general/helpers_inventario.php';

const SITUACIONES_VALIDAS = [
    'disponible', 'asignado', 'en_mantenimiento', 'en_reparacion',
    'no_encontrado', 'en_investigacion', 'dado_de_baja',
];

const TIPOS_NOVEDAD = [
    'Daño físico', 'Falla técnica', 'Falta de accesorio', 'Ubicación incorrecta',
    'Responsable incorrecto', 'Serial no coincide', 'Código no coincide',
    'Elemento no encontrado', 'Otro',
];

const MOTIVOS_BAJA = [
    'Daño irreparable', 'Obsolescencia', 'Pérdida', 'Hurto',
    'Destrucción', 'Donación', 'Otro',
];

const TIPOS_EVIDENCIA = [
    'Foto del elemento completo', 'Foto del código QR', 'Foto del serial',
    'Foto del estado', 'Foto del daño', 'Documento adicional',
];

const RESULTADOS_MANTENIMIENTO = [
    'Reparado', 'Reparado parcialmente', 'No reparable', 'Requiere nueva reparación',
];

const MAX_EVIDENCIA_SIZE = 8 * 1024 * 1024;

/* ============================ BÚSQUEDA / QR ============================ */

/**
 * Extrae el código de un contenido escaneado (URL del QR o código directo).
 */
function parsearCodigoQR($contenido) {
    $texto = trim((string)$contenido);
    if ($texto === '') return null;
    if (preg_match('/[?&]codigo=([^&]+)/i', $texto, $m)) {
        return urldecode($m[1]);
    }
    return $texto;
}

function buscarElementoPorCodigo($conn, $codigo) {
    $stmt = $conn->prepare(
        "SELECT ig.*, s.nombre AS sede_nombre, p.nombre AS prof_nombre, p.apellido AS prof_apellido
         FROM inventario_general ig
         LEFT JOIN sedes s ON ig.id_sede = s.id
         LEFT JOIN profesores p ON ig.profesor_id = p.id
         WHERE ig.codigo_interno = ? AND ig.activo = 1"
    );
    $stmt->execute([$codigo]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* ============================ TOMA FÍSICA ============================ */

function elementosEsperadosEnUbicacion($conn, $sedeId, $ubicacion) {
    $stmt = $conn->prepare(
        "SELECT ig.*, s.nombre AS sede_nombre, p.nombre AS prof_nombre, p.apellido AS prof_apellido
         FROM inventario_general ig
         LEFT JOIN sedes s ON ig.id_sede = s.id
         LEFT JOIN profesores p ON ig.profesor_id = p.id
         WHERE ig.activo = 1 AND ig.id_sede = ? AND ig.ubicacion = ? AND ig.situacion <> 'dado_de_baja'
         ORDER BY ig.codigo_interno, ig.nombre"
    );
    $stmt->execute([(int)$sedeId, $ubicacion]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Crea una toma física con su detalle (activos esperados en la ubicación).
 */
function iniciarTomaFisica($conn, $sedeId, $ubicacion, $usuarioId) {
    $sede = $conn->prepare("SELECT id, nombre FROM sedes WHERE id=? AND activo=1");
    $sede->execute([(int)$sedeId]);
    $sedeData = $sede->fetch(PDO::FETCH_ASSOC);
    if (!$sedeData) {
        throw new RuntimeException('La sede seleccionada no existe');
    }

    global $catalogosUbicaciones;
    if (!ubicacionValidaEnSede($sedeData['nombre'], $ubicacion)) {
        throw new RuntimeException('La ubicación no pertenece a la sede seleccionada');
    }

    $esperados = elementosEsperadosEnUbicacion($conn, $sedeId, $ubicacion);
    if (count($esperados) === 0) {
        throw new RuntimeException('No hay activos registrados en esa ubicación');
    }

    $conn->prepare("INSERT INTO tomas_fisicas (sede_id, ubicacion, usuario_id, total_esperados, estado) VALUES (?, ?, ?, ?, 'en_progreso')")
        ->execute([(int)$sedeId, $ubicacion, (int)$usuarioId, count($esperados)]);
    $tomaId = (int)$conn->lastInsertId();

    $ins = $conn->prepare(
        "INSERT INTO tomas_fisicas_detalle (toma_fisica_id, elemento_id, estado_registrado)
         VALUES (?, ?, ?)"
    );
    foreach ($esperados as $el) {
        $ins->execute([$tomaId, (int)$el['id'], $el['estado'] ?: null]);
    }
    return $tomaId;
}

function obtenerToma($conn, $tomaId) {
    $stmt = $conn->prepare(
        "SELECT t.*, s.nombre AS sede_nombre, u.nombre AS usuario_nombre
         FROM tomas_fisicas t
         LEFT JOIN sedes s ON t.sede_id = s.id
         LEFT JOIN usuarios u ON t.usuario_id = u.id
         WHERE t.id = ?"
    );
    $stmt->execute([(int)$tomaId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function obtenerTomaActiva($conn, $usuarioId) {
    $stmt = $conn->prepare(
        "SELECT * FROM tomas_fisicas WHERE usuario_id=? AND estado='en_progreso' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([(int)$usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function obtenerDetallesToma($conn, $tomaId) {
    $stmt = $conn->prepare(
        "SELECT td.*, ig.codigo_interno, ig.nombre AS elemento_nombre, ig.tipo, ig.estado AS estado_actual,
                ig.situacion AS situacion_actual, ig.marca, ig.numero_serie,
                p.nombre AS prof_nombre, p.apellido AS prof_apellido, s.nombre AS sede_nombre
         FROM tomas_fisicas_detalle td
         JOIN inventario_general ig ON ig.id = td.elemento_id
         LEFT JOIN profesores p ON ig.profesor_id = p.id
         LEFT JOIN sedes s ON ig.id_sede = s.id
         WHERE td.toma_fisica_id = ?
         ORDER BY ig.codigo_interno, ig.nombre"
    );
    $stmt->execute([(int)$tomaId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function evidenciasDeEntidad($conn, $entidad, $entidadId) {
    $stmt = $conn->prepare(
        "SELECT e.*, u.nombre AS usuario_nombre FROM evidencias e
         LEFT JOIN usuarios u ON e.subida_por = u.id
         WHERE e.entidad = ? AND e.entidad_id = ? ORDER BY e.id"
    );
    $stmt->execute([$entidad, (int)$entidadId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica físicamente un elemento dentro de una toma.
 * $datos: encontrado(bool), estado_encontrado, coincide_codigo/sede/ubicacion/responsable,
 *         observacion, cambiar_estado(bool), situacion_despues
 */
function verificarElementoEnToma($conn, $detalleId, $datos, $usuarioId) {
    $det = $conn->prepare(
        "SELECT td.*, t.id AS toma_id, t.sede_id, t.ubicacion FROM tomas_fisicas_detalle td
         JOIN tomas_fisicas t ON t.id = td.toma_fisica_id WHERE td.id = ?"
    );
    $det->execute([(int)$detalleId]);
    $detalle = $det->fetch(PDO::FETCH_ASSOC);
    if (!$detalle) {
        throw new RuntimeException('El registro de verificación no existe');
    }

    $el = $conn->prepare("SELECT * FROM inventario_general WHERE id=?");
    $el->execute([(int)$detalle['elemento_id']]);
    $elemento = $el->fetch(PDO::FETCH_ASSOC);
    if (!$elemento) {
        throw new RuntimeException('El activo no existe');
    }

    $encontrado = !empty($datos['encontrado']);
    $estadoEncontrado = trim((string)($datos['estado_encontrado'] ?? ''));
    $situacionDespues = trim((string)($datos['situacion_despues'] ?? ''));
    if ($situacionDespues !== '' && !in_array($situacionDespues, SITUACIONES_VALIDAS, true)) {
        throw new RuntimeException('Situación inválida');
    }
    $observacion = trim((string)($datos['observacion'] ?? ''));

    $antesEstado = $elemento['estado'];
    $antesSituacion = $elemento['situacion'];

    $conn->beginTransaction();
    try {
        if (!$encontrado) {
            $conn->prepare(
                "UPDATE tomas_fisicas_detalle
                 SET encontrado=0, estado_encontrado=NULL, observacion=?, verificador_id=?, verificada_en=NOW()
                 WHERE id=?"
            )->execute([$observacion ?: null, (int)$usuarioId, (int)$detalleId]);

            $conn->prepare("UPDATE inventario_general SET situacion='no_encontrado' WHERE id=?")
                ->execute([(int)$elemento['id']]);

            registrarEventoHistorial(
                $conn, (int)$elemento['id'], 'inspeccion_fisica',
                'Elemento NO encontrado en toma física',
                ['estado' => $antesEstado, 'situacion' => $antesSituacion],
                ['situacion' => 'no_encontrado', 'encontrado' => false, 'toma_fisica_id' => (int)$detalle['toma_id']],
                (int)$usuarioId, $observacion ?: null
            );
        } else {
            $cambiarEstado = !empty($datos['cambiar_estado']) && $estadoEncontrado !== ''
                && $estadoEncontrado !== $elemento['estado'];

            $conn->prepare(
                "UPDATE tomas_fisicas_detalle
                 SET encontrado=1, estado_encontrado=?, coincide_codigo=?, coincide_sede=?,
                     coincide_ubicacion=?, coincide_responsable=?, situacion_despues=?,
                     observacion=?, verificador_id=?, verificada_en=NOW()
                 WHERE id=?"
            )->execute([
                $estadoEncontrado ?: null,
                !empty($datos['coincide_codigo']) ? 1 : 0,
                !empty($datos['coincide_sede']) ? 1 : 0,
                !empty($datos['coincide_ubicacion']) ? 1 : 0,
                !empty($datos['coincide_responsable']) ? 1 : 0,
                $situacionDespues !== '' ? $situacionDespues : null,
                $observacion ?: null,
                (int)$usuarioId,
                (int)$detalleId,
            ]);

            $upd = "UPDATE inventario_general SET ";
            $params = [];
            if ($cambiarEstado) {
                $upd .= "estado=?, ";
                $params[] = $estadoEncontrado;
            }
            if ($situacionDespues !== '' && $situacionDespues !== 'no_encontrado') {
                $upd .= "situacion=?, ";
                $params[] = $situacionDespues;
            }
            $upd = rtrim($upd, ', ');
            if ($params) {
                $upd .= " WHERE id=?";
                $params[] = (int)$elemento['id'];
                $conn->prepare($upd)->execute($params);
            }

            $nuevos = [
                'estado' => $estadoEncontrado ?: $antesEstado,
                'situacion' => $situacionDespues !== '' ? $situacionDespues : $antesSituacion,
                'encontrado' => true,
                'toma_fisica_id' => (int)$detalle['toma_id'],
            ];
            registrarEventoHistorial(
                $conn, (int)$elemento['id'], 'inspeccion_fisica',
                $cambiarEstado
                    ? 'Inspección física: estado actualizado (' . $antesEstado . ' → ' . $estadoEncontrado . ')'
                    : 'Inspección física: activo verificado',
                ['estado' => $antesEstado, 'situacion' => $antesSituacion],
                $nuevos,
                (int)$usuarioId,
                $observacion ?: null
            );
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
    return true;
}

/**
 * Cambia la situación de un activo (no_encontrado → en_investigacion → encontrado/confirmado perdido...).
 */
function cambiarSituacion($conn, $elementoId, $situacion, $usuarioId, $observacion = null) {
    if (!in_array($situacion, SITUACIONES_VALIDAS, true)) {
        throw new RuntimeException('Situación inválida');
    }
    $el = $conn->prepare("SELECT estado, situacion FROM inventario_general WHERE id=? AND activo=1");
    $el->execute([(int)$elementoId]);
    $elemento = $el->fetch(PDO::FETCH_ASSOC);
    if (!$elemento) {
        throw new RuntimeException('El activo no existe');
    }
    if ($elemento['situacion'] === $situacion) {
        return true;
    }
    $conn->prepare("UPDATE inventario_general SET situacion=? WHERE id=?")
        ->execute([$situacion, (int)$elementoId]);
    registrarEventoHistorial(
        $conn, (int)$elementoId, 'cambio_situacion',
        'Situación cambiada: ' . $elemento['situacion'] . ' → ' . $situacion,
        ['situacion' => $elemento['situacion']],
        ['situacion' => $situacion],
        (int)$usuarioId, $observacion ?: null
    );
    return true;
}

function cancelarTomaFisica($conn, $tomaId, $usuarioId) {
    $toma = obtenerToma($conn, $tomaId);
    if (!$toma || $toma['estado'] !== 'en_progreso' || (int)$toma['usuario_id'] !== (int)$usuarioId) {
        throw new RuntimeException('La toma física no está en progreso o no es de este usuario');
    }
    $conn->prepare("UPDATE tomas_fisicas SET estado='cancelada' WHERE id=?")->execute([(int)$tomaId]);
    return true;
}

function finalizarTomaFisica($conn, $tomaId, $observaciones = null) {
    $toma = obtenerToma($conn, $tomaId);
    if (!$toma || $toma['estado'] !== 'en_progreso') {
        throw new RuntimeException('La toma física no está en progreso');
    }
    $total = (int)$toma['total_esperados'];
    $encontrados = (int)$conn->query("SELECT COUNT(*) FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND encontrado=1")->fetchColumn();
    $noEncontrados = $total - $encontrados;
    $danados = (int)$conn->query("SELECT COUNT(*) FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND encontrado=1 AND estado_encontrado IN ('dañado','malo','fuera de servicio')")->fetchColumn();
    $enBuenEstado = (int)$conn->query("SELECT COUNT(*) FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND encontrado=1 AND estado_encontrado IN ('bueno','nuevo')")->fetchColumn();
    $enMantenimiento = (int)$conn->query("SELECT COUNT(*) FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND situacion_despues='en_mantenimiento'")->fetchColumn();
    $enReparacion = (int)$conn->query("SELECT COUNT(*) FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND situacion_despues='en_reparacion'")->fetchColumn();
    $novedades = (int)$conn->query("SELECT COUNT(*) FROM novedades WHERE toma_fisica_id=$tomaId")->fetchColumn();

    $conn->prepare(
        "UPDATE tomas_fisicas SET estado='finalizada', finalizada_en=NOW(),
         encontrados=?, no_encontrados=?, con_novedades=?, danados=?, en_buen_estado=?,
         en_mantenimiento=?, en_reparacion=?, observaciones=?
         WHERE id=?"
    )->execute([
        $encontrados, $noEncontrados, $novedades, $danados, $enBuenEstado,
        $enMantenimiento, $enReparacion,
        $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null,
        (int)$tomaId,
    ]);
    return true;
}

/* ============================ NOVEDADES ============================ */

function registrarNovedad($conn, $elementoId, $tipo, $descripcion, $usuarioId, $tomaId = null) {
    $el = $conn->prepare("SELECT id FROM inventario_general WHERE id=? AND activo=1");
    $el->execute([(int)$elementoId]);
    if (!$el->fetchColumn()) {
        throw new RuntimeException('El activo no existe');
    }
    $tipo = trim((string)$tipo);
    $descripcion = trim((string)$descripcion);
    if ($tipo === '' || $descripcion === '') {
        throw new RuntimeException('Tipo y descripción de la novedad son obligatorios');
    }
    $conn->prepare(
        "INSERT INTO novedades (elemento_id, toma_fisica_id, tipo, descripcion, usuario_id, estado)
         VALUES (?, ?, ?, ?, ?, 'abierta')"
    )->execute([(int)$elementoId, $tomaId ? (int)$tomaId : null, $tipo, $descripcion, (int)$usuarioId]);
    $novedadId = (int)$conn->lastInsertId();

    registrarEventoHistorial(
        $conn, (int)$elementoId, 'novedad_registrada',
        'Novedad registrada: ' . $tipo,
        null,
        ['novedad_id' => $novedadId, 'tipo' => $tipo, 'descripcion' => $descripcion],
        (int)$usuarioId
    );
    return $novedadId;
}

/* ============================ MANTENIMIENTO ============================ */

/**
 * Envía un activo a mantenimiento (reutiliza la tabla mantenimiento existente).
 */
function enviarAMantenimiento($conn, $elementoId, $datos, $usuarioId) {
    $el = $conn->prepare("SELECT * FROM inventario_general WHERE id=? AND activo=1");
    $el->execute([(int)$elementoId]);
    $elemento = $el->fetch(PDO::FETCH_ASSOC);
    if (!$elemento) {
        throw new RuntimeException('El activo no existe');
    }
    if ($elemento['situacion'] === 'dado_de_baja') {
        throw new RuntimeException('Un activo dado de baja no puede enviarse a mantenimiento');
    }
    $descripcion = trim((string)($datos['descripcion'] ?? ''));
    if ($descripcion === '') {
        throw new RuntimeException('La descripción del mantenimiento es obligatoria');
    }

    $conn->beginTransaction();
    try {
        $conn->prepare(
            "INSERT INTO mantenimiento (id_equipo, elemento_id, id_usuario, fecha_inicio, descripcion_trabajo, costo, proveedor, tecnico, estado, observaciones)
             VALUES (NULL, ?, ?, CURDATE(), ?, ?, ?, ?, 'programado', ?)"
        )->execute([
            (int)$elementoId,
            (int)$usuarioId,
            $descripcion,
            (float)($datos['costo'] ?? 0),
            trim((string)($datos['proveedor'] ?? '')) ?: null,
            trim((string)($datos['tecnico'] ?? '')) ?: null,
            trim((string)($datos['observaciones'] ?? '')) ?: null,
        ]);
        $mtoId = (int)$conn->lastInsertId();

        $conn->prepare("UPDATE inventario_general SET situacion='en_mantenimiento' WHERE id=?")
            ->execute([(int)$elementoId]);

        registrarEventoHistorial(
            $conn, (int)$elementoId, 'mantenimiento_iniciado',
            'Enviado a mantenimiento',
            ['situacion' => $elemento['situacion'], 'estado' => $elemento['estado']],
            ['mantenimiento_id' => $mtoId, 'descripcion' => $descripcion, 'tecnico' => trim((string)($datos['tecnico'] ?? '')) ?: null],
            (int)$usuarioId
        );
        $conn->commit();
        return $mtoId;
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Finaliza un mantenimiento del módulo (elemento_id). Resultado según RESULTADOS_MANTENIMIENTO.
 */
function finalizarMantenimientoToma($conn, $mantenimientoId, $datos, $usuarioId) {
    $mto = $conn->prepare(
        "SELECT m.*, ig.id AS elem_id, ig.estado AS elem_estado, ig.situacion AS elem_situacion
         FROM mantenimiento m JOIN inventario_general ig ON ig.id = m.elemento_id
         WHERE m.id=? AND m.estado IN ('programado','en_proceso')"
    );
    $mto->execute([(int)$mantenimientoId]);
    $mant = $mto->fetch(PDO::FETCH_ASSOC);
    if (!$mant) {
        throw new RuntimeException('El mantenimiento no existe o ya fue finalizado');
    }
    $resultado = trim((string)($datos['resultado'] ?? ''));
    if (!in_array($resultado, RESULTADOS_MANTENIMIENTO, true)) {
        throw new RuntimeException('Resultado del mantenimiento inválido');
    }

    $conn->beginTransaction();
    try {
        $conn->prepare(
            "UPDATE mantenimiento SET estado='completado', fecha_fin=CURDATE(), resultado=?, costo=?, observaciones=?
             WHERE id=?"
        )->execute([
            $resultado,
            (float)($datos['costo'] ?? 0),
            trim((string)($datos['observaciones'] ?? '')) ?: null,
            (int)$mantenimientoId,
        ]);

        $nuevoEstado = $mant['elem_estado'];
        $nuevaSituacion = 'disponible';
        if ($resultado === 'Reparado') {
            $nuevoEstado = 'bueno';
        } elseif ($resultado === 'Reparado parcialmente') {
            $nuevoEstado = 'regular';
        } elseif ($resultado === 'No reparable') {
            $nuevoEstado = 'dañado';
            $nuevaSituacion = 'disponible';
        } else {
            $nuevaSituacion = 'en_reparacion';
        }
        $conn->prepare("UPDATE inventario_general SET estado=?, situacion=? WHERE id=?")
            ->execute([$nuevoEstado, $nuevaSituacion, (int)$mant['elem_id']]);

        registrarEventoHistorial(
            $conn, (int)$mant['elem_id'], 'mantenimiento_finalizado',
            'Mantenimiento finalizado: ' . $resultado,
            ['estado' => $mant['elem_estado'], 'situacion' => $mant['elem_situacion']],
            ['mantenimiento_id' => (int)$mantenimientoId, 'resultado' => $resultado, 'costo' => (float)($datos['costo'] ?? 0), 'estado' => $nuevoEstado, 'situacion' => $nuevaSituacion],
            (int)$usuarioId
        );
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
}

/* ============================ BAJAS ============================ */

/**
 * Solicita la baja de un activo (NO elimina el activo; requiere aprobación).
 */
function solicitarBaja($conn, $elementoId, $motivo, $fechaBaja, $descripcion, $usuarioId) {
    $el = $conn->prepare("SELECT * FROM inventario_general WHERE id=? AND activo=1");
    $el->execute([(int)$elementoId]);
    $elemento = $el->fetch(PDO::FETCH_ASSOC);
    if (!$elemento) {
        throw new RuntimeException('El activo no existe');
    }
    if ($elemento['situacion'] === 'dado_de_baja') {
        throw new RuntimeException('El activo ya fue dado de baja');
    }
    if (!in_array($motivo, MOTIVOS_BAJA, true)) {
        throw new RuntimeException('Motivo de baja inválido');
    }
    $pendiente = $conn->prepare("SELECT COUNT(*) FROM bajas WHERE elemento_id=? AND estado='solicitada'");
    $pendiente->execute([(int)$elementoId]);
    if ((int)$pendiente->fetchColumn() > 0) {
        throw new RuntimeException('Ya existe una solicitud de baja pendiente para este activo');
    }

    $conn->prepare(
        "INSERT INTO bajas (elemento_id, motivo, fecha_baja, descripcion, usuario_solicita, estado)
         VALUES (?, ?, ?, ?, ?, 'solicitada')"
    )->execute([(int)$elementoId, $motivo, $fechaBaja, trim((string)$descripcion) ?: null, (int)$usuarioId]);
    $bajaId = (int)$conn->lastInsertId();

    registrarEventoHistorial(
        $conn, (int)$elementoId, 'baja_solicitada',
        'Solicitud de baja: ' . $motivo,
        ['estado' => $elemento['estado'], 'situacion' => $elemento['situacion']],
        ['baja_id' => $bajaId, 'motivo' => $motivo, 'fecha_baja' => $fechaBaja],
        (int)$usuarioId
    );
    return $bajaId;
}

function aprobarBaja($conn, $bajaId, $usuarioId, $observacion = null) {
    $b = $conn->prepare("SELECT * FROM bajas WHERE id=? AND estado='solicitada'");
    $b->execute([(int)$bajaId]);
    $baja = $b->fetch(PDO::FETCH_ASSOC);
    if (!$baja) {
        throw new RuntimeException('La solicitud de baja no existe o ya fue resuelta');
    }

    $el = $conn->prepare("SELECT * FROM inventario_general WHERE id=?");
    $el->execute([(int)$baja['elemento_id']]);
    $elemento = $el->fetch(PDO::FETCH_ASSOC);

    $conn->beginTransaction();
    try {
        $conn->prepare(
            "UPDATE bajas SET estado='aprobada', aprobado_por=?, observacion_aprobacion=?, fecha_aprobacion=NOW()
             WHERE id=?"
        )->execute([(int)$usuarioId, trim((string)$observacion) ?: null, (int)$bajaId]);

        $conn->prepare("UPDATE inventario_general SET situacion='dado_de_baja' WHERE id=?")
            ->execute([(int)$baja['elemento_id']]);

        registrarEventoHistorial(
            $conn, (int)$baja['elemento_id'], 'baja_aprobada',
            'Baja aprobada: ' . $baja['motivo'],
            ['estado' => $elemento['estado'] ?? null, 'situacion' => $elemento['situacion'] ?? null],
            ['baja_id' => (int)$bajaId, 'motivo' => $baja['motivo'], 'situacion' => 'dado_de_baja'],
            (int)$usuarioId, trim((string)$observacion) ?: null
        );
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
}

function rechazarBaja($conn, $bajaId, $usuarioId, $observacion = null) {
    $b = $conn->prepare("SELECT * FROM bajas WHERE id=? AND estado='solicitada'");
    $b->execute([(int)$bajaId]);
    $baja = $b->fetch(PDO::FETCH_ASSOC);
    if (!$baja) {
        throw new RuntimeException('La solicitud de baja no existe o ya fue resuelta');
    }
    $conn->prepare(
        "UPDATE bajas SET estado='rechazada', aprobado_por=?, observacion_aprobacion=?, fecha_aprobacion=NOW()
         WHERE id=?"
    )->execute([(int)$usuarioId, trim((string)$observacion) ?: null, (int)$bajaId]);

    registrarEventoHistorial(
        $conn, (int)$baja['elemento_id'], 'baja_rechazada',
        'Solicitud de baja rechazada',
        null,
        ['baja_id' => (int)$bajaId, 'motivo' => $baja['motivo']],
        (int)$usuarioId, trim((string)$observacion) ?: null
    );
    return true;
}

/* ============================ EVIDENCIAS ============================ */

function validarEvidenciaSubida($archivo) {
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir el archivo'];
    }
    if ((int)$archivo['size'] <= 0) {
        return ['ok' => false, 'error' => 'El archivo está vacío'];
    }
    if ((int)$archivo['size'] > MAX_EVIDENCIA_SIZE) {
        return ['ok' => false, 'error' => 'El archivo supera el tamaño máximo permitido (8 MB)'];
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
        return ['ok' => false, 'error' => 'Solo se permiten archivos JPG, JPEG, PNG, WEBP o PDF'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    $permitidos = [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
    ];
    if (!in_array($mime, $permitidos, true)) {
        return ['ok' => false, 'error' => 'El tipo de archivo no es válido'];
    }
    return ['ok' => true, 'error' => '', 'ext' => $ext, 'mime' => $mime];
}

/**
 * Guarda una evidencia en uploads/evidencias con nombre único generado por el servidor.
 */
function guardarArchivoEvidencia($archivo) {
    if (empty($archivo['tmp_name'])) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/evidencias';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = isset($archivo['ext']) && $archivo['ext'] !== ''
        ? $archivo['ext']
        : strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
    $nombre = 'ev_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destino = "$dir/$nombre";
    if (is_uploaded_file($archivo['tmp_name'])) {
        if (move_uploaded_file($archivo['tmp_name'], $destino)) {
            return "evidencias/$nombre";
        }
    } elseif (copy($archivo['tmp_name'], $destino)) {
        return "evidencias/$nombre";
    }
    return null;
}

/**
 * Procesa $_FILES['evidencias'] (puede ser [] o []['archivo']).
 * Devuelve ['ok' => bool, 'guardadas' => int, 'errores' => string[]].
 */
function registrarEvidencias($conn, $archivos, $entidad, $entidadId, $usuarioId) {
    $guardadas = 0;
    $errores = [];
    if (!isset($archivos) || !is_array($archivos)) {
        return ['ok' => true, 'guardadas' => 0, 'errores' => []];
    }
    $items = [];
    if (isset($archivos['tmp_name']) && is_array($archivos['tmp_name'])) {
        foreach ($archivos['tmp_name'] as $i => $tmp) {
            if ($tmp === '') continue;
            $items[] = [
                'archivo' => [
                    'name' => $archivos['name'][$i] ?? '',
                    'tmp_name' => $tmp,
                    'size' => $archivos['size'][$i] ?? 0,
                    'error' => $archivos['error'][$i] ?? UPLOAD_ERR_OK,
                ],
                'tipo' => '',
            ];
        }
    } elseif (isset($archivos['tmp_name'])) {
        $items[] = ['archivo' => $archivos, 'tipo' => ''];
    } else {
        foreach ($archivos as $v) {
            if (is_array($v) && isset($v['archivo']) && is_array($v['archivo'])) {
                $items[] = [
                    'archivo' => $v['archivo'],
                    'tipo' => trim((string)($v['tipo'] ?? '')),
                ];
            }
        }
    }
    if (!$items) {
        return ['ok' => true, 'guardadas' => 0, 'errores' => []];
    }
    $ins = $conn->prepare(
        "INSERT INTO evidencias (entidad, entidad_id, tipo_evidencia, archivo, subida_por)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($items as $item) {
        $archivo = $item['archivo'];
        if (!is_array($archivo) || !isset($archivo['tmp_name']) || $archivo['tmp_name'] === '') {
            continue;
        }
        $val = validarEvidenciaSubida($archivo);
        if (!$val['ok']) {
            $errores[] = ($item['tipo'] ?: 'archivo') . ': ' . $val['error'];
            continue;
        }
        $ruta = guardarArchivoEvidencia($archivo);
        if ($ruta === null) {
            $errores[] = ($item['tipo'] ?: 'archivo') . ': no se pudo guardar';
            continue;
        }
        $ins->execute([$entidad, (int)$entidadId, $item['tipo'] ?: null, $ruta, (int)$usuarioId]);
        $guardadas++;
    }
    return ['ok' => empty($errores), 'guardadas' => $guardadas, 'errores' => $errores];
}

function eliminarEvidencia($conn, $evidenciaId) {
    $stmt = $conn->prepare("SELECT * FROM evidencias WHERE id=?");
    $stmt->execute([(int)$evidenciaId]);
    $ev = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ev) {
        eliminarArchivoDocumento($ev['archivo']);
        $conn->prepare("DELETE FROM evidencias WHERE id=?")->execute([(int)$evidenciaId]);
    }
    return true;
}

/* ============================ CATÁLOGOS (+ botones) ============================ */

function crearCategoria($conn, $nombre, $descripcion = null) {
    $nombre = trim((string)$nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre de la categoría es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre de la categoría no puede superar 50 caracteres');
    }
    $existe = $conn->prepare("SELECT id FROM categorias WHERE nombre=?");
    $existe->execute([$nombre]);
    if ($existe->fetchColumn()) {
        throw new RuntimeException('Ya existe una categoría con ese nombre');
    }
    $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)")
        ->execute([$nombre, trim((string)$descripcion) ?: null]);
    return (int)$conn->lastInsertId();
}

function crearTipo($conn, $nombre, $descripcion = null, $categoriaId = null) {
    $nombre = trim((string)$nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre del tipo es obligatorio');
    }
    $existe = $conn->prepare("SELECT id FROM tipo_equipo WHERE nombre_tipo=?");
    $existe->execute([$nombre]);
    if ($existe->fetchColumn()) {
        throw new RuntimeException('Ya existe un tipo con ese nombre');
    }
    if ($categoriaId !== null) {
        $cat = $conn->prepare("SELECT id FROM categorias WHERE id=?");
        $cat->execute([(int)$categoriaId]);
        if (!$cat->fetchColumn()) {
            throw new RuntimeException('La categoría seleccionada no existe');
        }
    }
    $conn->prepare("INSERT INTO tipo_equipo (nombre_tipo, descripcion, categoria_id) VALUES (?, ?, ?)")
        ->execute([$nombre, trim((string)$descripcion) ?: null, $categoriaId ? (int)$categoriaId : null]);
    return (int)$conn->lastInsertId();
}

function crearEstado($conn, $nombre, $descripcion = null) {
    $nombre = trim((string)$nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre del estado es obligatorio');
    }
    $existe = $conn->prepare("SELECT id FROM estados WHERE nombre=?");
    $existe->execute([$nombre]);
    if ($existe->fetchColumn()) {
        throw new RuntimeException('Ya existe un estado con ese nombre');
    }
    $conn->prepare("INSERT INTO estados (nombre, descripcion) VALUES (?, ?)")
        ->execute([$nombre, trim((string)$descripcion) ?: null]);
    return (int)$conn->lastInsertId();
}

function estadosDisponibles($conn) {
    return $conn->query("SELECT * FROM estados WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Catálogo categoría → tipos desde la base de datos (misma forma que
 * config/catalogos_inventario.php). Los tipos sin categoría van a "Otros".
 */
function obtenerCatalogoInventarioBD($conn) {
    $rows = $conn->query(
        "SELECT c.nombre AS categoria, t.nombre_tipo AS tipo
         FROM tipo_equipo t LEFT JOIN categorias c ON t.categoria_id = c.id
         ORDER BY c.nombre, t.nombre_tipo"
    )->fetchAll(PDO::FETCH_ASSOC);

    $catalogo = [];
    $tieneTipos = false;
    foreach ($rows as $r) {
        $cat = $r['categoria'] !== null && $r['categoria'] !== '' ? $r['categoria'] : 'Otros';
        if ($cat !== 'Otros') $tieneTipos = true;
        $catalogo[$cat][] = $r['tipo'];
    }
    if (!$tieneTipos) {
        $catalogo = require __DIR__ . '/../config/catalogos_inventario.php';
    }
    return $catalogo;
}

/* ============================ INFORME PDF ============================ */

/**
 * Construye el HTML del informe de toma física (logo, institución, sede,
 * ubicación, fecha, usuario, tabla de verificación, novedades y firmas).
 * Recibe: $toma (cabecera), $detalles, $novedades, $institucion, $logoPath.
 */
function construirInformeTomaHTML($toma, $detalles, $novedades, $institucion, $logoPath) {
    $encontrados = 0; $noEncontrados = 0; $pendientes = 0; $danados = 0;
    foreach ($detalles as $d) {
        if ((int)$d['encontrado'] === 1) {
            $encontrados++;
            if (in_array($d['estado_encontrado'], ['dañado', 'malo', 'fuera de servicio'], true)) $danados++;
        } elseif ($d['verificada_en'] !== null) {
            $noEncontrados++;
        } else {
            $pendientes++;
        }
    }
    $total = count($detalles);

    $html = '<html><head><meta charset="utf-8"><style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #1e293b; }
        .encabezado { width: 100%; border-bottom: 3px solid #1a237e; padding-bottom: 10px; margin-bottom: 14px; }
        .encabezado table { width: 100%; }
        .logo { width: 70px; }
        .inst-nombre { font-size: 14pt; font-weight: bold; color: #1a237e; }
        .inst-codigo { font-size: 9pt; color: #555; }
        h1.titulo { font-size: 13pt; text-align: center; color: #1a237e; margin: 16px 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        .subtitulo { text-align: center; font-size: 9pt; color: #555; margin-bottom: 16px; }
        .seccion { background: #1a237e; color: #fff; font-weight: bold; font-size: 10pt; padding: 4px 8px; margin: 14px 0 8px; }
        .datos td { padding: 2px 6px; vertical-align: top; }
        .etiqueta { color: #64748b; font-size: 8.5pt; width: 140px; }
        .resumen td { padding: 3px 8px; border: 1px solid #c7d2fe; background: #eef2ff; text-align: center; font-size: 9pt; }
        .resumen .num { font-size: 13pt; font-weight: bold; color: #1a237e; }
        table.activos { width: 100%; border-collapse: collapse; }
        table.activos th { background: #eef2ff; color: #1a237e; font-size: 8.5pt; padding: 5px 6px; border: 1px solid #c7d2fe; text-align: left; }
        table.activos td { font-size: 8.5pt; padding: 4px 6px; border: 1px solid #dde3f5; }
        table.activos tr:nth-child(even) td { background: #f8fafc; }
        .ok { color: #15803d; font-weight: bold; }
        .nok { color: #b91c1c; font-weight: bold; }
        .pend { color: #b45309; font-weight: bold; }
        .firmas { margin-top: 34px; width: 100%; }
        .firmas td { width: 50%; text-align: center; font-size: 9pt; }
        .firma-linea { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
        .footer { margin-top: 20px; font-size: 7.5pt; color: #94a3b8; text-align: center; }
    </style></head><body>';

    $html .= '<div class="encabezado"><table><tr>';
    if ($logoPath) {
        $html .= '<td style="width:80px;"><img class="logo" src="' . $logoPath . '"></td>';
    }
    $html .= '<td><div class="inst-nombre">' . htmlspecialchars($institucion['nombre']) . '</div>';
    $html .= '<div class="inst-codigo">Código de la institución: ' . htmlspecialchars($institucion['codigo']) . '</div></td>';
    $html .= '</tr></table></div>';

    $html .= '<h1 class="titulo">Informe de Toma Física e Inspección de Activos</h1>';
    $html .= '<div class="subtitulo">Documento generado por el Sistema de Inventario y Control (MIC) — ' . date('d/m/Y H:i') . '</div>';

    $html .= '<div class="seccion">DATOS GENERALES</div>';
    $html .= '<table class="datos">';
    $html .= '<tr><td class="etiqueta">Toma física N°:</td><td><strong>#' . (int)$toma['id'] . '</strong></td></tr>';
    $html .= '<tr><td class="etiqueta">Sede:</td><td>' . htmlspecialchars($toma['sede_nombre'] ?? 'No registrada') . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Ubicación:</td><td>' . htmlspecialchars($toma['ubicacion']) . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Fecha de la toma:</td><td>' . date('d/m/Y H:i', strtotime($toma['fecha_toma'])) . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Fecha de finalización:</td><td>' . ($toma['finalizada_en'] ? date('d/m/Y H:i', strtotime($toma['finalizada_en'])) : 'No finalizada') . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Responsable de la toma:</td><td>' . htmlspecialchars($toma['usuario_nombre'] ?? 'No registrado') . '</td></tr>';
    if (!empty($toma['observaciones'])) {
        $html .= '<tr><td class="etiqueta">Observaciones:</td><td>' . htmlspecialchars($toma['observaciones']) . '</td></tr>';
    }
    $html .= '</table>';

    $html .= '<div class="seccion">RESUMEN</div>';
    $html .= '<table class="resumen" style="width:100%;border-collapse:collapse;">';
    $html .= '<tr><td><div class="num">' . $total . '</div>Esperados</td>';
    $html .= '<td><div class="num">' . $encontrados . '</div>Encontrados</td>';
    $html .= '<td><div class="num">' . $noEncontrados . '</div>No encontrados</td>';
    $html .= '<td><div class="num">' . $danados . '</div>Dañados</td>';
    $html .= '<td><div class="num">' . count($novedades) . '</div>Novedades</td>';
    $html .= '</tr></table>';

    $html .= '<div class="seccion">DETALLE DE LA VERIFICACIÓN (' . $total . ' activos)</div>';
    $html .= '<table class="activos"><thead><tr><th>Código</th><th>Elemento</th><th>Responsable</th><th>Estado registrado</th><th>Estado encontrado</th><th>Coincidencias</th><th>Resultado</th></tr></thead><tbody>';
    foreach ($detalles as $d) {
        $coincidencias = 0;
        foreach (['coincide_codigo', 'coincide_sede', 'coincide_ubicacion', 'coincide_responsable'] as $c) {
            if ((int)$d[$c] === 1) $coincidencias++;
        }
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($d['codigo_interno'] ?? ('#' . $d['elemento_id'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($d['elemento_nombre']);
        if (!empty($d['marca'])) $html .= '<br><small style="color:#64748b;">' . htmlspecialchars($d['marca']) . '</small>';
        $html .= '</td>';
        $html .= '<td>' . htmlspecialchars(trim(($d['prof_nombre'] ?? '') . ' ' . ($d['prof_apellido'] ?? ''))) . '</td>';
        $html .= '<td>' . htmlspecialchars($d['estado_registrado'] ?: '—') . '</td>';
        $html .= '<td>' . ($d['estado_encontrado'] ? htmlspecialchars($d['estado_encontrado']) : '—') . '</td>';
        $html .= '<td>' . $coincidencias . '/4' . '</td>';
        if ((int)$d['encontrado'] === 1) {
            $html .= '<td class="ok">Encontrado</td>';
        } elseif ($d['verificada_en'] !== null) {
            $html .= '<td class="nok">No encontrado</td>';
        } else {
            $html .= '<td class="pend">Pendiente</td>';
        }
        if (!empty($d['observacion'])) {
            $html .= '</tr><tr><td></td><td colspan="6"><small style="color:#64748b;"><strong>Observación:</strong> ' . htmlspecialchars($d['observacion']) . '</small></td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    if (count($novedades) > 0) {
        $html .= '<div class="seccion">NOVEDADES (' . count($novedades) . ')</div>';
        $html .= '<table class="activos"><thead><tr><th>#</th><th>Activo</th><th>Tipo</th><th>Descripción</th></tr></thead><tbody>';
        foreach ($novedades as $n) {
            $html .= '<tr>';
            $html .= '<td>' . (int)$n['id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($n['codigo_interno'] ?? ('#' . $n['elemento_id'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($n['tipo']) . '</td>';
            $html .= '<td>' . htmlspecialchars($n['descripcion']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '<table class="firmas"><tr>';
    $html .= '<td><div class="firma-linea">Responsable de la toma física<br>' . htmlspecialchars($toma['usuario_nombre'] ?? '') . '</div></td>';
    $html .= '<td><div class="firma-linea">Administrador del inventario</div></td>';
    $html .= '</tr></table>';

    $html .= '<div class="footer">Sistema MIC — ' . htmlspecialchars($institucion['nombre']) . ' — Informe generado el ' . date('d/m/Y H:i:s') . '</div>';
    $html .= '</body></html>';
    return $html;
}

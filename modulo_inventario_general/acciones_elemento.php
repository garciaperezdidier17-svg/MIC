<?php
/**
 * Acciones administrativas sobre elementos del inventario:
 * - Reasignación de responsable
 * - Cambio de ubicación / sede
 * - Registro y finalización de mantenimiento
 *
 * Toda operación registra el movimiento en el historial (elemento_historial)
 * y actualiza el estado actual del elemento en una transacción.
 */
require_once '../config/conexion.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }
require_once __DIR__ . '/helpers_historial.php';
require_once __DIR__ . '/helpers_inventario.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

$catalogosUbicaciones = require __DIR__ . '/../config/ubicaciones.php';

function redirigirFicha($conn, $elementoId, $mensaje, $error = false) {
    $codigo = $conn->query("SELECT codigo_interno FROM inventario_general WHERE id=" . (int)$elementoId)->fetchColumn();
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['mensaje_error'] = $error ? 1 : 0;
    header('Location: ../ver_articulo.php?codigo=' . urlencode($codigo ?: ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../modulo_inventario_general/index.php');
    exit;
}

verificarCSRF();
$accion = $_POST['accion'] ?? '';

try {
    $conn->beginTransaction();

    if ($accion === 'reasignar') {
        $elementoId = (int)($_POST['elemento_id'] ?? 0);
        $nuevoProfesorId = (int)($_POST['profesor_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');

        if (!$elementoId || !$nuevoProfesorId) {
            throw new RuntimeException('Debe seleccionar elemento y nuevo responsable');
        }
        $el = $conn->prepare("SELECT ig.*, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as responsable_actual FROM inventario_general ig LEFT JOIN profesores p ON ig.profesor_id=p.id WHERE ig.id=? AND ig.activo=1");
        $el->execute([$elementoId]);
        $elemento = $el->fetch(PDO::FETCH_ASSOC);
        if (!$elemento) {
            throw new RuntimeException('El elemento no existe o está inactivo');
        }
        $prof = $conn->prepare("SELECT * FROM profesores WHERE id=? AND estado='Activo'");
        $prof->execute([$nuevoProfesorId]);
        $profesor = $prof->fetch(PDO::FETCH_ASSOC);
        if (!$profesor) {
            throw new RuntimeException('El responsable seleccionado no existe o está inactivo');
        }
        if ((int)$profesor['sede_id'] !== (int)$elemento['id_sede']) {
            throw new RuntimeException('El responsable seleccionado no pertenece a la sede del elemento');
        }
        if ((int)$elemento['profesor_id'] === $nuevoProfesorId) {
            throw new RuntimeException('El responsable seleccionado es el mismo que el actual');
        }

        registrarEventoHistorial(
            $conn, $elementoId, 'reasignacion',
            'Reasignación de responsable',
            ['responsable' => $elemento['responsable_actual'] ?: 'Sin asignar', 'responsable_id' => $elemento['profesor_id'] ? (int)$elemento['profesor_id'] : null],
            ['responsable' => trim($profesor['nombre'] . ' ' . $profesor['apellido']), 'responsable_id' => $nuevoProfesorId],
            (int)$_SESSION['user_id'],
            $motivo ?: null
        );
        registrarAuditoria(
            $conn, 'reasignar_activo', 'inventario', 'elemento', $elementoId,
            'Reasignación: ' . ($elemento['responsable_actual'] ?: 'Sin asignar') . ' → ' . trim($profesor['nombre'] . ' ' . $profesor['apellido']) . ($motivo ? ' (' . $motivo . ')' : ''),
            ['responsable' => $elemento['responsable_actual'] ?: 'Sin asignar', 'responsable_id' => $elemento['profesor_id'] ? (int)$elemento['profesor_id'] : null],
            ['responsable' => trim($profesor['nombre'] . ' ' . $profesor['apellido']), 'responsable_id' => $nuevoProfesorId, 'motivo' => $motivo ?: null]
        );
        $conn->prepare("UPDATE inventario_general SET profesor_id=? WHERE id=?")->execute([$nuevoProfesorId, $elementoId]);
        $conn->commit();
        redirigirFicha($conn, $elementoId, 'Responsable reasignado correctamente');
    }

    if ($accion === 'cambiar_ubicacion') {
        $elementoId = (int)($_POST['elemento_id'] ?? 0);
        $nuevaSedeId = (int)($_POST['id_sede'] ?? 0);
        $nuevaUbicacion = trim($_POST['ubicacion'] ?? '');
        $nuevoProfesorId = !empty($_POST['profesor_id']) ? (int)$_POST['profesor_id'] : 0;
        $motivo = trim($_POST['motivo'] ?? '');
        $observacion = trim($_POST['observacion'] ?? '');

        if (!$elementoId || !$nuevaSedeId || !$nuevaUbicacion) {
            throw new RuntimeException('Debe seleccionar sede y ubicación nueva');
        }
        $el = $conn->prepare("SELECT ig.*, s.nombre as sede_actual_nombre, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as responsable_actual FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id LEFT JOIN profesores p ON ig.profesor_id=p.id WHERE ig.id=? AND ig.activo=1");
        $el->execute([$elementoId]);
        $elemento = $el->fetch(PDO::FETCH_ASSOC);
        if (!$elemento) {
            throw new RuntimeException('El elemento no existe o está inactivo');
        }
        $sede = $conn->prepare("SELECT id, nombre, codigo FROM sedes WHERE id=? AND activo=1");
        $sede->execute([$nuevaSedeId]);
        $nuevaSede = $sede->fetch(PDO::FETCH_ASSOC);
        if (!$nuevaSede) {
            throw new RuntimeException('La sede seleccionada no existe o está inactiva');
        }
        if (!ubicacionValidaEnSede($nuevaSede['nombre'], $nuevaUbicacion)) {
            throw new RuntimeException('La ubicación seleccionada no pertenece a la sede elegida');
        }
        if ($nuevaSedeId === (int)$elemento['id_sede'] && $nuevaUbicacion === $elemento['ubicacion']) {
            throw new RuntimeException('El elemento ya se encuentra en esa sede y ubicación');
        }

        $cambioSede = $nuevaSedeId !== (int)$elemento['id_sede'];
        $nuevoCodigoUbic = '';
        foreach ($catalogosUbicaciones[$nuevaSede['nombre']]['ubicaciones'] ?? [] as $u) {
            if ($u['nombre'] === $nuevaUbicacion) { $nuevoCodigoUbic = $u['codigo']; break; }
        }

        $nuevoProfesorFinal = (int)$elemento['profesor_id'];
        $responsableNuevoNombre = $elemento['responsable_actual'];
        if ($cambioSede && $elemento['profesor_id']) {
            $profSede = $conn->query("SELECT sede_id FROM profesores WHERE id=" . (int)$elemento['profesor_id'])->fetchColumn();
            if ($profSede !== false && (int)$profSede !== $nuevaSedeId) {
                if (!$nuevoProfesorId) {
                    throw new RuntimeException('El responsable actual no pertenece a la nueva sede: seleccione un responsable de la sede ' . $nuevaSede['nombre'] . ' o déjelo sin responsable');
                }
                $prof = $conn->prepare("SELECT nombre, apellido, sede_id FROM profesores WHERE id=? AND estado='Activo'");
                $prof->execute([$nuevoProfesorId]);
                $nuevoProf = $prof->fetch(PDO::FETCH_ASSOC);
                if (!$nuevoProf || (int)$nuevoProf['sede_id'] !== $nuevaSedeId) {
                    throw new RuntimeException('El responsable seleccionado no pertenece a la nueva sede');
                }
                $nuevoProfesorFinal = $nuevoProfesorId;
                $responsableNuevoNombre = trim($nuevoProf['nombre'] . ' ' . $nuevoProf['apellido']);
            }
        }

        $tipoEvento = $cambioSede ? 'cambio_sede' : 'cambio_ubicacion';
        $datosAnterior = [
            'sede' => $elemento['sede_actual_nombre'] ?: '—',
            'ubicacion' => $elemento['ubicacion'] ?: '—',
        ];
        $datosNuevos = [
            'sede' => $nuevaSede['nombre'],
            'ubicacion' => $nuevaUbicacion,
        ];
        registrarEventoHistorial(
            $conn, $elementoId, $tipoEvento,
            $cambioSede ? 'Cambio de sede y ubicación' : 'Cambio de ubicación',
            $datosAnterior, $datosNuevos,
            (int)$_SESSION['user_id'],
            $motivo ?: ($observacion ?: null)
        );
        registrarAuditoria(
            $conn, $tipoEvento, 'inventario', 'elemento', $elementoId,
            ($cambioSede ? 'Cambio de sede y ubicación' : 'Cambio de ubicación') . ': ' . ($elemento['sede_actual_nombre'] ?: '—') . ' / ' . ($elemento['ubicacion'] ?: '—') . ' → ' . $nuevaSede['nombre'] . ' / ' . $nuevaUbicacion,
            $datosAnterior,
            array_merge($datosNuevos, ['motivo' => $motivo ?: ($observacion ?: null)])
        );
        if ($cambioSede && $elemento['profesor_id'] && $nuevoProfesorFinal !== (int)$elemento['profesor_id']) {
            registrarEventoHistorial(
                $conn, $elementoId, 'reasignacion',
                'Reasignación por cambio de sede',
                ['responsable' => $elemento['responsable_actual'] ?: 'Sin asignar', 'responsable_id' => (int)$elemento['profesor_id']],
                ['responsable' => $responsableNuevoNombre, 'responsable_id' => $nuevoProfesorFinal],
                (int)$_SESSION['user_id'],
                $motivo ?: null
            );
        }
        $conn->prepare("UPDATE inventario_general SET id_sede=?, ubicacion=?, codigo_ubicacion=?, profesor_id=? WHERE id=?")
            ->execute([$nuevaSedeId, $nuevaUbicacion, $nuevoCodigoUbic ?: null, $nuevoProfesorFinal ?: null, $elementoId]);
        $conn->commit();
        redirigirFicha($conn, $elementoId, $cambioSede ? 'Sede y ubicación actualizadas correctamente' : 'Ubicación actualizada correctamente');
    }

    if ($accion === 'registrar_mantenimiento') {
        $elementoId = (int)($_POST['elemento_id'] ?? 0);
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
        $descripcion = trim($_POST['descripcion_trabajo'] ?? '');
        $tecnico = trim($_POST['tecnico'] ?? '');
        $costo = ($_POST['costo'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['costo']) : null;
        $proveedor = trim($_POST['proveedor'] ?? '');
        $estadoMto = in_array($_POST['estado'] ?? '', ['programado', 'en_proceso'], true) ? $_POST['estado'] : 'programado';
        $observaciones = trim($_POST['observaciones'] ?? '');

        if (!$elementoId || !$fechaInicio || !$descripcion) {
            throw new RuntimeException('Fecha, descripción y elemento son obligatorios');
        }
        $el = $conn->prepare("SELECT ig.id, ig.numero_serie, ig.codigo_interno FROM inventario_general ig WHERE ig.id=? AND ig.activo=1");
        $el->execute([$elementoId]);
        $elemento = $el->fetch(PDO::FETCH_ASSOC);
        if (!$elemento || !$elemento['numero_serie']) {
            throw new RuntimeException('El elemento no existe o no tiene número de serie');
        }
        $eq = $conn->prepare("SELECT id FROM equipos WHERE numero_serie=? AND activo=1 ORDER BY id LIMIT 1");
        $eq->execute([$elemento['numero_serie']]);
        $equipoId = $eq->fetchColumn();
        if (!$equipoId) {
            throw new RuntimeException('Este elemento no tiene un equipo asociado para registrar mantenimiento');
        }
        $conn->prepare("INSERT INTO mantenimiento (id_equipo, id_usuario, fecha_inicio, descripcion_trabajo, costo, proveedor, tecnico, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$equipoId, (int)$_SESSION['user_id'], $fechaInicio, $descripcion, $costo, $proveedor ?: null, $tecnico ?: null, $estadoMto, $observaciones ?: null]);
        $mtoId = $conn->lastInsertId();
        $conn->prepare("UPDATE equipos SET estado='mantenimiento' WHERE id=?")->execute([$equipoId]);
        registrarEventoHistorial(
            $conn, $elementoId, 'mantenimiento_iniciado',
            'Mantenimiento registrado (' . $estadoMto . ')',
            null,
            ['mantenimiento_id' => $mtoId, 'descripcion' => $descripcion, 'tecnico' => $tecnico, 'estado' => $estadoMto],
            (int)$_SESSION['user_id'],
            $observaciones ?: null
        );
        registrarAuditoria(
            $conn, 'registrar_mantenimiento', 'inventario', 'elemento', $elementoId,
            'Mantenimiento registrado (' . $estadoMto . '): ' . mb_strimwidth($descripcion, 0, 80, '…'),
            ['estado' => $estadoMto],
            ['mantenimiento_id' => $mtoId, 'descripcion' => $descripcion, 'tecnico' => $tecnico, 'proveedor' => $proveedor ?: null, 'costo' => $costo]
        );
        $conn->commit();
        redirigirFicha($conn, $elementoId, 'Mantenimiento registrado correctamente');
    }

    if ($accion === 'finalizar_mantenimiento') {
        $mantenimientoId = (int)($_POST['mantenimiento_id'] ?? 0);
        $elementoId = (int)($_POST['elemento_id'] ?? 0);
        if (!$mantenimientoId || !$elementoId) {
            throw new RuntimeException('Datos incompletos para finalizar el mantenimiento');
        }
        $mto = $conn->prepare("SELECT m.*, e.numero_serie, e.nombre as equipo_nombre FROM mantenimiento m JOIN equipos e ON e.id=m.id_equipo WHERE m.id=? AND m.estado IN ('programado','en_proceso')");
        $mto->execute([$mantenimientoId]);
        $registro = $mto->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            throw new RuntimeException('El mantenimiento no existe o ya fue finalizado');
        }
        $el = $conn->prepare("SELECT id FROM inventario_general WHERE id=? AND numero_serie=? AND activo=1");
        $el->execute([$elementoId, $registro['numero_serie']]);
        if (!$el->fetchColumn()) {
            throw new RuntimeException('El mantenimiento no corresponde a este elemento');
        }
        $conn->prepare("UPDATE mantenimiento SET estado='completado', fecha_fin=CURDATE() WHERE id=?")->execute([$mantenimientoId]);
        $conn->prepare("UPDATE equipos SET estado='disponible' WHERE id=?")->execute([$registro['id_equipo']]);
        registrarEventoHistorial(
            $conn, $elementoId, 'mantenimiento_finalizado',
            'Mantenimiento finalizado',
            null,
            ['mantenimiento_id' => $mantenimientoId, 'descripcion' => $registro['descripcion_trabajo']],
            (int)$_SESSION['user_id']
        );
        registrarAuditoria(
            $conn, 'finalizar_mantenimiento', 'inventario', 'elemento', $elementoId,
            'Mantenimiento finalizado (#' . $mantenimientoId . ')',
            ['estado' => $registro['estado']],
            ['mantenimiento_id' => $mantenimientoId, 'fecha_fin' => date('Y-m-d')]
        );
        $conn->commit();
        redirigirFicha($conn, $elementoId, 'Mantenimiento finalizado correctamente');
    }

    if ($accion === 'alternar_prestamo') {
        $elementoId = (int)($_POST['elemento_id'] ?? 0);
        if (!$elementoId) {
            throw new RuntimeException('Datos incompletos para alternar la disponibilidad de préstamo');
        }
        $el = $conn->prepare("SELECT id, codigo_interno, disponible_para_prestamo FROM inventario_general WHERE id=? AND activo=1");
        $el->execute([$elementoId]);
        $elemento = $el->fetch(PDO::FETCH_ASSOC);
        if (!$elemento) {
            throw new RuntimeException('El elemento no existe');
        }
        $nuevoValor = (int)$elemento['disponible_para_prestamo'] === 1 ? 0 : 1;
        $conn->prepare("UPDATE inventario_general SET disponible_para_prestamo=? WHERE id=?")
            ->execute([$nuevoValor, $elementoId]);
        $texto = $nuevoValor === 1 ? 'habilitado' : 'inhabilitado';
        registrarEventoHistorial(
            $conn, $elementoId, 'disponibilidad_prestamo',
            'Elemento ' . $texto . ' para préstamo',
            ['disponible_para_prestamo' => (int)$elemento['disponible_para_prestamo']],
            ['disponible_para_prestamo' => $nuevoValor],
            (int)$_SESSION['user_id'],
            null
        );
        registrarAuditoria(
            $conn, 'alternar_prestamo', 'inventario', 'elemento', $elementoId,
            'Elemento ' . $texto . ' para préstamo (' . ($elemento['codigo_interno'] ?: '#' . $elementoId) . ')',
            ['disponible_para_prestamo' => (int)$elemento['disponible_para_prestamo']],
            ['disponible_para_prestamo' => $nuevoValor]
        );
        $conn->commit();
        redirigirFicha($conn, $elementoId, 'Elemento ' . $texto . ' para préstamo correctamente');
    }

    throw new RuntimeException('Acción no válida');
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    logError("Error en acciones_elemento ($accion): " . $e->getMessage());
    $_SESSION['mensaje'] = $e->getMessage();
    $_SESSION['mensaje_error'] = 1;
    $elementoId = (int)($_POST['elemento_id'] ?? 0);
    if ($elementoId) {
        redirigirFicha($conn, $elementoId, $e->getMessage(), true);
    }
    header('Location: ../modulo_inventario_general/index.php');
    exit;
}

<?php
/**
 * Backend del módulo Toma Física e Inspección de Activos.
 * Todas las acciones requieren sesión, validan CSRF y usan consultas preparadas.
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/helpers_toma_fisica.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

if (!estaLogueado()) {
    header('Location: ../modulo_login/index.php');
    exit;
}
if (!esAdmin()) {
    http_response_code(403);
    die('Acceso denegado');
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$usuarioId = (int)$_SESSION['user_id'];

function responderJson($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {

        /* ---------- Escaneo QR / búsqueda por código (AJAX) ---------- */
        case 'buscar_codigo': {
            verificarCSRF();
            $codigo = parsearCodigoQR($_GET['codigo'] ?? '');
            if ($codigo === null || $codigo === '') {
                responderJson(['ok' => false, 'error' => 'Código vacío'], 400);
            }
            $elemento = buscarElementoPorCodigo($conn, $codigo);
            if (!$elemento) {
                responderJson(['ok' => false, 'error' => 'No se encontró ningún activo con ese código']);
            }
            responderJson(['ok' => true, 'elemento' => $elemento]);
        }

        /* ---------- Verificación física ---------- */
        case 'verificar': {
            verificarCSRF();
            $detalleId = (int)($_POST['detalle_id'] ?? 0);
            $datos = [
                'encontrado' => !empty($_POST['encontrado']),
                'estado_encontrado' => $_POST['estado_encontrado'] ?? '',
                'coincide_codigo' => !empty($_POST['coincide_codigo']),
                'coincide_sede' => !empty($_POST['coincide_sede']),
                'coincide_ubicacion' => !empty($_POST['coincide_ubicacion']),
                'coincide_responsable' => !empty($_POST['coincide_responsable']),
                'cambiar_estado' => !empty($_POST['cambiar_estado']),
                'situacion_despues' => $_POST['situacion_despues'] ?? '',
                'observacion' => $_POST['observacion'] ?? '',
            ];
            verificarElementoEnToma($conn, $detalleId, $datos, $usuarioId);
            $detInfo = $conn->prepare("SELECT elemento_id FROM tomas_fisicas_detalle WHERE id=?");
            $detInfo->execute([$detalleId]);
            $detElemento = $detInfo->fetchColumn();
            registrarAuditoria(
                $conn, 'verificar_inspeccion', 'toma_fisica', 'elemento', $detElemento ? (int)$detElemento : null,
                ($datos['encontrado'] ? 'Inspección física: activo verificado' : 'Inspección física: elemento NO encontrado'),
                ['encontrado' => $datos['encontrado']],
                ['detalle_id' => $detalleId, 'estado_encontrado' => $datos['estado_encontrado'] ?: null, 'situacion_despues' => $datos['situacion_despues'] ?: null, 'observacion' => $datos['observacion'] ?: null]
            );
            $guardadas = 0;
            if (!empty($_FILES['evidencias'])) {
                $res = registrarEvidencias($conn, $_FILES['evidencias'], 'inspeccion', $detalleId, $usuarioId);
                $guardadas = $res['guardadas'];
            }
            responderJson(['ok' => true, 'mensaje' => 'Verificación registrada', 'evidencias' => $guardadas]);
        }

        /* ---------- Situación (no encontrado → en investigación → encontrado...) ---------- */
        case 'cambiar_situacion': {
            verificarCSRF();
            $elementoId = (int)($_POST['elemento_id'] ?? 0);
            $situacion = $_POST['situacion'] ?? '';
            cambiarSituacion($conn, $elementoId, $situacion, $usuarioId, $_POST['observacion'] ?? null);
            responderJson(['ok' => true, 'mensaje' => 'Situación actualizada']);
        }

        /* ---------- Toma física ---------- */
        case 'iniciar_toma': {
            verificarCSRF();
            $tomaActiva = obtenerTomaActiva($conn, $usuarioId);
            if ($tomaActiva) {
                responderJson(['ok' => false, 'error' => 'Ya tienes una toma física en progreso (#' . $tomaActiva['id'] . ')'], 400);
            }
            $sedeId = (int)($_POST['sede_id'] ?? 0);
            $ubicacion = trim((string)($_POST['ubicacion'] ?? ''));
            $tomaId = iniciarTomaFisica($conn, $sedeId, $ubicacion, $usuarioId);
            $tomaInfo = obtenerToma($conn, $tomaId);
            registrarAuditoria(
                $conn, 'iniciar_toma_fisica', 'toma_fisica', 'toma', $tomaId,
                'Toma física iniciada: ' . ($tomaInfo['sede_nombre'] ?? '') . ' / ' . $ubicacion,
                null,
                ['sede_id' => $sedeId, 'ubicacion' => $ubicacion, 'total_esperados' => $tomaInfo['total_esperados'] ?? null]
            );
            responderJson(['ok' => true, 'toma_id' => $tomaId, 'mensaje' => 'Toma física iniciada']);
        }

        case 'finalizar_toma': {
            verificarCSRF();
            $tomaId = (int)($_POST['toma_id'] ?? 0);
            $toma = obtenerToma($conn, $tomaId);
            if (!$toma || (int)$toma['usuario_id'] !== $usuarioId) {
                throw new RuntimeException('La toma física no existe o no es de este usuario');
            }
            finalizarTomaFisica($conn, $tomaId, $_POST['observaciones'] ?? null);
            $tomaFin = obtenerToma($conn, $tomaId);
            registrarAuditoria(
                $conn, 'finalizar_toma_fisica', 'toma_fisica', 'toma', $tomaId,
                'Toma física finalizada: ' . ($tomaFin['encontrados'] ?? 0) . '/' . ($tomaFin['total_esperados'] ?? 0) . ' activos encontrados',
                null,
                ['total_esperados' => $tomaFin['total_esperados'] ?? 0, 'encontrados' => $tomaFin['encontrados'] ?? 0, 'no_encontrados' => $tomaFin['no_encontrados'] ?? 0, 'novedades' => $tomaFin['con_novedades'] ?? 0]
            );
            responderJson(['ok' => true, 'mensaje' => 'Toma física finalizada', 'toma_id' => $tomaId]);
        }

        case 'cancelar_toma': {
            verificarCSRF();
            $tomaId = (int)($_POST['toma_id'] ?? 0);
            cancelarTomaFisica($conn, $tomaId, $usuarioId);
            registrarAuditoria($conn, 'cancelar_toma_fisica', 'toma_fisica', 'toma', $tomaId, 'Toma física cancelada', null, ['toma_id' => $tomaId]);
            responderJson(['ok' => true, 'mensaje' => 'Toma física cancelada']);
        }

        /* ---------- Novedades ---------- */
        case 'registrar_novedad': {
            verificarCSRF();
            $elementoId = (int)($_POST['elemento_id'] ?? 0);
            $tomaId = !empty($_POST['toma_fisica_id']) ? (int)$_POST['toma_fisica_id'] : null;
            $novedadId = registrarNovedad(
                $conn, $elementoId, $_POST['tipo'] ?? '', $_POST['descripcion'] ?? '',
                $usuarioId, $tomaId
            );
            registrarAuditoria(
                $conn, 'registrar_novedad', 'toma_fisica', 'elemento', $elementoId,
                'Novedad registrada: ' . ($_POST['tipo'] ?? ''),
                null,
                ['novedad_id' => $novedadId, 'tipo' => $_POST['tipo'] ?? '', 'descripcion' => $_POST['descripcion'] ?? '', 'toma_fisica_id' => $tomaId]
            );
            $guardadas = 0;
            if (!empty($_FILES['evidencias'])) {
                $res = registrarEvidencias($conn, $_FILES['evidencias'], 'novedad', $novedadId, $usuarioId);
                $guardadas = $res['guardadas'];
            }
            responderJson(['ok' => true, 'mensaje' => 'Novedad registrada', 'novedad_id' => $novedadId, 'evidencias' => $guardadas]);
        }

        /* ---------- Mantenimiento ---------- */
        case 'enviar_mantenimiento': {
            verificarCSRF();
            $elementoId = (int)($_POST['elemento_id'] ?? 0);
            $mtoId = enviarAMantenimiento($conn, $elementoId, $_POST, $usuarioId);
            registrarAuditoria(
                $conn, 'enviar_mantenimiento', 'toma_fisica', 'elemento', $elementoId,
                'Activo enviado a mantenimiento',
                null,
                ['mantenimiento_id' => $mtoId, 'descripcion' => $_POST['descripcion'] ?? '', 'tecnico' => $_POST['tecnico'] ?? '']
            );
            $guardadas = 0;
            if (!empty($_FILES['evidencias'])) {
                $res = registrarEvidencias($conn, $_FILES['evidencias'], 'inspeccion', $mtoId, $usuarioId);
                $guardadas = $res['guardadas'];
            }
            responderJson(['ok' => true, 'mensaje' => 'Activo enviado a mantenimiento', 'mantenimiento_id' => $mtoId, 'evidencias' => $guardadas]);
        }

        case 'finalizar_mantenimiento': {
            verificarCSRF();
            $mantenimientoId = (int)($_POST['mantenimiento_id'] ?? 0);
            finalizarMantenimientoToma($conn, $mantenimientoId, $_POST, $usuarioId);
            $mtoFin = $conn->prepare("SELECT elemento_id, resultado FROM mantenimiento WHERE id=?");
            $mtoFin->execute([$mantenimientoId]);
            $mtoFinData = $mtoFin->fetch(PDO::FETCH_ASSOC);
            registrarAuditoria(
                $conn, 'finalizar_mantenimiento', 'toma_fisica', 'elemento', $mtoFinData['elemento_id'] ?? null,
                'Mantenimiento finalizado: ' . ($_POST['resultado'] ?? ''),
                null,
                ['mantenimiento_id' => $mantenimientoId, 'resultado' => $_POST['resultado'] ?? '', 'costo' => $_POST['costo'] ?? 0]
            );
            $guardadas = 0;
            if (!empty($_FILES['evidencias'])) {
                $res = registrarEvidencias($conn, $_FILES['evidencias'], 'inspeccion', $mantenimientoId, $usuarioId);
                $guardadas = $res['guardadas'];
            }
            responderJson(['ok' => true, 'mensaje' => 'Mantenimiento finalizado', 'evidencias' => $guardadas]);
        }

        /* ---------- Bajas ---------- */
        case 'solicitar_baja': {
            verificarCSRF();
            $elementoId = (int)($_POST['elemento_id'] ?? 0);
            $fecha = $_POST['fecha_baja'] ?? '';
            if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                throw new RuntimeException('Fecha de baja inválida');
            }
            $bajaId = solicitarBaja(
                $conn, $elementoId, $_POST['motivo'] ?? '', $fecha,
                $_POST['descripcion'] ?? '', $usuarioId
            );
            registrarAuditoria(
                $conn, 'solicitar_baja', 'toma_fisica', 'elemento', $elementoId,
                'Solicitud de baja: ' . ($_POST['motivo'] ?? ''),
                null,
                ['baja_id' => $bajaId, 'motivo' => $_POST['motivo'] ?? '', 'fecha_baja' => $fecha]
            );
            $guardadas = 0;
            if (!empty($_FILES['evidencias'])) {
                $res = registrarEvidencias($conn, $_FILES['evidencias'], 'baja', $bajaId, $usuarioId);
                $guardadas = $res['guardadas'];
            }
            $docBaja = null;
            if (!empty($_FILES['documento_baja']) && isset($_FILES['documento_baja']['tmp_name']) && $_FILES['documento_baja']['tmp_name'] !== '') {
                $val = validarEvidenciaSubida($_FILES['documento_baja']);
                if ($val['ok']) {
                    $docBaja = guardarArchivoEvidencia($_FILES['documento_baja']);
                }
            }
            if ($docBaja) {
                $conn->prepare("UPDATE bajas SET documento_baja=? WHERE id=?")->execute([$docBaja, $bajaId]);
            }
            responderJson(['ok' => true, 'mensaje' => 'Solicitud de baja registrada', 'baja_id' => $bajaId, 'evidencias' => $guardadas]);
        }

        case 'aprobar_baja': {
            verificarCSRF();
            $bajaId = (int)($_POST['baja_id'] ?? 0);
            aprobarBaja($conn, $bajaId, $usuarioId, $_POST['observacion'] ?? null);
            $bajaInfo = $conn->prepare("SELECT elemento_id, motivo FROM bajas WHERE id=?");
            $bajaInfo->execute([$bajaId]);
            $bajaData = $bajaInfo->fetch(PDO::FETCH_ASSOC);
            registrarAuditoria(
                $conn, 'aprobar_baja', 'toma_fisica', 'elemento', $bajaData['elemento_id'] ?? null,
                'Baja aprobada: ' . ($bajaData['motivo'] ?? ''),
                null,
                ['baja_id' => $bajaId, 'motivo' => $bajaData['motivo'] ?? '', 'observacion' => $_POST['observacion'] ?? null]
            );
            responderJson(['ok' => true, 'mensaje' => 'Baja aprobada. El activo conserva su código, QR e historial.']);
        }

        case 'rechazar_baja': {
            verificarCSRF();
            $bajaId = (int)($_POST['baja_id'] ?? 0);
            rechazarBaja($conn, $bajaId, $usuarioId, $_POST['observacion'] ?? null);
            $bajaInfo = $conn->prepare("SELECT elemento_id, motivo FROM bajas WHERE id=?");
            $bajaInfo->execute([$bajaId]);
            $bajaData = $bajaInfo->fetch(PDO::FETCH_ASSOC);
            registrarAuditoria(
                $conn, 'rechazar_baja', 'toma_fisica', 'elemento', $bajaData['elemento_id'] ?? null,
                'Solicitud de baja rechazada',
                null,
                ['baja_id' => $bajaId, 'motivo' => $bajaData['motivo'] ?? '', 'observacion' => $_POST['observacion'] ?? null]
            );
            responderJson(['ok' => true, 'mensaje' => 'Solicitud de baja rechazada']);
        }

        /* ---------- Catálogos (botones "+") ---------- */
        case 'crear_categoria': {
            verificarCSRF();
            $id = crearCategoria($conn, $_POST['nombre'] ?? '', $_POST['descripcion'] ?? '');
            registrarAuditoria($conn, 'crear_categoria', 'sistema', 'categoria', $id, 'Categoría creada: ' . trim($_POST['nombre']), null, ['nombre' => trim($_POST['nombre']), 'descripcion' => trim($_POST['descripcion'] ?? '')]);
            responderJson(['ok' => true, 'mensaje' => 'Categoría creada', 'id' => $id, 'nombre' => trim($_POST['nombre'])]);
        }

        case 'crear_tipo': {
            verificarCSRF();
            $categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
            $id = crearTipo($conn, $_POST['nombre'] ?? '', $_POST['descripcion'] ?? '', $categoriaId);
            registrarAuditoria($conn, 'crear_tipo', 'sistema', 'tipo', $id, 'Tipo creado: ' . trim($_POST['nombre']), null, ['nombre' => trim($_POST['nombre']), 'categoria_id' => $categoriaId]);
            responderJson(['ok' => true, 'mensaje' => 'Tipo creado', 'id' => $id, 'nombre' => trim($_POST['nombre'])]);
        }

        case 'crear_estado': {
            verificarCSRF();
            $id = crearEstado($conn, $_POST['nombre'] ?? '', $_POST['descripcion'] ?? '');
            registrarAuditoria($conn, 'crear_estado', 'sistema', 'estado', $id, 'Estado creado: ' . trim($_POST['nombre']), null, ['nombre' => trim($_POST['nombre'])]);
            responderJson(['ok' => true, 'mensaje' => 'Estado creado', 'id' => $id, 'nombre' => trim($_POST['nombre'])]);
        }

        /* ---------- Evidencias ---------- */
        case 'eliminar_evidencia': {
            verificarCSRF();
            $id = (int)($_POST['id'] ?? 0);
            eliminarEvidencia($conn, $id);
            responderJson(['ok' => true, 'mensaje' => 'Evidencia eliminada']);
        }

        default:
            responderJson(['ok' => false, 'error' => 'Acción no válida: ' . htmlspecialchars($accion)], 400);
    }
} catch (Throwable $e) {
    logError("Toma física - error en acción '$accion': " . $e->getMessage());
    responderJson(['ok' => false, 'error' => $e->getMessage()], 400);
}

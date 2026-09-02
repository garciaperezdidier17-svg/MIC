<?php
require_once '../config/conexion.php';
require_once '../vendor/autoload.php';
require_once __DIR__ . '/helpers_historial.php';
if (!estaLogueado()) { header('Location: ../modulo_login/index.php'); exit; }
if (!esAdmin()) { header('Location: ../modulo_prestamos/solicitudes.php'); exit; }

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

$institucion = require __DIR__ . '/../config/institucion.php';
require_once __DIR__ . '/../config/helpers_catalogos.php';
$catalogos = catalogoMapaTiposPorCategoria($conn);
$categoriasCatalogo = catalogoCategorias($conn);
$categoriasCatalogoNombres = array_column($categoriasCatalogo, 'nombre');
$estadosFormulario = catalogoEstados($conn);
$catalogosUbicaciones = require __DIR__ . '/../config/ubicaciones.php';

require_once __DIR__ . '/helpers_inventario.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $categoria = trim($_POST['categoria'] ?? '');
    $tipo_raw = trim($_POST['tipo']);
    $tipo = ($tipo_raw === '__otro__') ? trim($_POST['tipo_otro']) : $tipo_raw;
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $ram = trim($_POST['ram'] ?? '');
    $almacenamiento = trim($_POST['almacenamiento'] ?? '');
    $accesorios = trim($_POST['accesorios'] ?? '');
    $estado = trim($_POST['estado']);
    $ubicacion = trim($_POST['ubicacion']);
    $id_sede = !empty($_POST['id_sede']) ? (int)$_POST['id_sede'] : null;
    $profesor_id = !empty($_POST['profesor_id']) ? (int)$_POST['profesor_id'] : null;
    $origen_bien = isset($_POST['origen_bien']) && origenValido($_POST['origen_bien']) ? $_POST['origen_bien'] : null;
    $documento_no_disponible = isset($_POST['documento_no_disponible']) ? 1 : 0;
    $proveedor_id = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;
    $numero_factura = trim($_POST['numero_factura'] ?? '');
    $fecha_compra = !empty($_POST['fecha_compra']) ? $_POST['fecha_compra'] : null;
    $valor_compra = (!empty($_POST['valor_compra'])) ? (float)str_replace(',', '', $_POST['valor_compra']) : null;
    $numero_orden_compra = trim($_POST['numero_orden_compra'] ?? '');
    $fecha_garantia = !empty($_POST['fecha_garantia']) ? $_POST['fecha_garantia'] : null;
    $donante_nombre = trim($_POST['donante_nombre'] ?? '');
    $fecha_donacion = !empty($_POST['fecha_donacion']) ? $_POST['fecha_donacion'] : null;
    $institucion_origen = trim($_POST['institucion_origen'] ?? '');
    $fecha_transferencia = !empty($_POST['fecha_transferencia']) ? $_POST['fecha_transferencia'] : null;
    $descripcion_origen = trim($_POST['descripcion_origen'] ?? '');
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
    $descripcion = trim($_POST['descripcion']);
    $observacion = trim($_POST['observacion'] ?? '');
    $vr_comercial = (!isset($_POST['donado']) && isset($_POST['vr_comercial']) && $_POST['vr_comercial'] !== '') ? (float)str_replace(',', '', $_POST['vr_comercial']) : null;
    $vida_util = (!isset($_POST['donado']) && isset($_POST['vida_util']) && $_POST['vida_util'] !== '') ? (int)$_POST['vida_util'] : null;
    $disponible_para_prestamo = isset($_POST['disponible_para_prestamo']) && $_POST['disponible_para_prestamo'] === '1' ? 1 : 0;
    
    if (!empty($nombre) && !empty($tipo)) {
        if ($origen_bien === 'Compra' && !$documento_no_disponible && !$proveedor_id) {
            $_SESSION['mensaje'] = 'Debe seleccionar un proveedor cuando el origen del bien es Compra';
            header('Location: index.php');
            exit;
        }
        if ($proveedor_id) {
            $provStmt = $conn->prepare("SELECT id FROM proveedores WHERE id=? AND estado='Activo'");
            $provStmt->execute([$proveedor_id]);
            if (!$provStmt->fetchColumn()) {
                $_SESSION['mensaje'] = 'El proveedor seleccionado no existe o está inactivo';
                header('Location: index.php');
                exit;
            }
        }
        if ($profesor_id && !profesorPerteneceSede($conn, $profesor_id, $id_sede)) {
            $_SESSION['mensaje'] = 'El responsable seleccionado no pertenece a la sede del elemento';
            header('Location: index.php');
            exit;
        }
        $sedeData = $conn->prepare("SELECT nombre, codigo FROM sedes WHERE id=?");
        $sedeData->execute([$id_sede]);
        $sedeInfo = $sedeData->fetch(PDO::FETCH_ASSOC);
        if ($sedeInfo && !ubicacionPerteneceSede($sedeInfo['nombre'], $ubicacion)) {
            $_SESSION['mensaje'] = 'La ubicación seleccionada no pertenece a la sede del elemento';
            header('Location: index.php');
            exit;
        }
        $ubicCodigo = '';
        $codigo_interno = '';
        if ($sedeInfo) {
            $ubicCodigo = obtenerCodigoUbicacion($sedeInfo['nombre'], $ubicacion);
            $codigo_interno = generarCodigoElemento($conn, $institucion['codigo'], $sedeInfo['nombre'], $sedeInfo['codigo'], $ubicCodigo);
        }
        $conn->prepare("INSERT INTO inventario_general (codigo_interno, nombre, categoria, tipo, marca, modelo, numero_serie, procesador, ram, almacenamiento, accesorios, estado, ubicacion, codigo_ubicacion, id_sede, profesor_id, origen_bien, documento_no_disponible, proveedor_id, numero_factura, fecha_compra, valor_compra, numero_orden_compra, fecha_garantia, donante_nombre, fecha_donacion, institucion_origen, fecha_transferencia, descripcion_origen, fecha_ingreso, descripcion, observacion, vr_comercial, vida_util, disponible_para_prestamo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$codigo_interno ?: null, $nombre, $categoria ?: null, $tipo, $marca ?: null, $modelo ?: null, $numero_serie ?: null, $procesador ?: null, $ram ?: null, $almacenamiento ?: null, $accesorios ?: null, $estado, $ubicacion, $ubicCodigo ?: null, $id_sede, $profesor_id, $origen_bien, $documento_no_disponible, $proveedor_id, $numero_factura ?: null, $fecha_compra, $valor_compra, $numero_orden_compra ?: null, $fecha_garantia, $donante_nombre ?: null, $fecha_donacion, $institucion_origen ?: null, $fecha_transferencia, $descripcion_origen ?: null, $fecha_ingreso, $descripcion ?: null, $observacion ?: null, $vr_comercial, $vida_util, $disponible_para_prestamo]);
        $nuevoId = $conn->lastInsertId();
        $docField = $documento_no_disponible ? null : campoDocumentoDe($origen_bien);
        if ($docField && isset($_FILES[$docField]) && $_FILES[$docField]['error'] === UPLOAD_ERR_OK) {
            $validacion = validarDocumentoSubido($_FILES[$docField]);
            if (!$validacion['ok']) {
                $_SESSION['mensaje'] = 'No se guardó el elemento: ' . $validacion['error'];
                $conn->prepare("UPDATE inventario_general SET activo=0 WHERE id=?")->execute([$nuevoId]);
                header('Location: index.php');
                exit;
            }
            $ruta = guardarDocumento($validacion, $nuevoId);
            if ($ruta) {
                $conn->prepare("UPDATE inventario_general SET documento_adquisicion=? WHERE id=?")->execute([$ruta, $nuevoId]);
                registrarEventoHistorial($conn, $nuevoId, 'documento_agregado', 'Documento de adquisición agregado', null, ['archivo' => $ruta], (int)$_SESSION['user_id']);
                registrarAuditoria($conn, 'subir_documento', 'inventario', 'elemento', $nuevoId, 'Documento de adquisición subido', null, ['archivo' => $ruta]);
            }
        }
        if ($codigo_interno) {
            $qrPath = generarQR($codigo_interno, $nuevoId);
            $conn->prepare("UPDATE inventario_general SET qr_path=? WHERE id=?")->execute([$qrPath, $nuevoId]);
        }
        $profesorNombre = $profesor_id ? $conn->query("SELECT CONCAT(nombre, ' ', apellido) FROM profesores WHERE id=$profesor_id")->fetchColumn() : null;
        registrarEventoHistorial(
            $conn, $nuevoId, 'registro',
            'Elemento registrado en el inventario',
            null,
            [
                'nombre' => $nombre, 'codigo' => $codigo_interno ?: null, 'tipo' => $tipo,
                'sede' => $sedeInfo['nombre'] ?? null, 'ubicacion' => $ubicacion ?: null,
                'responsable' => $profesorNombre ?: null, 'estado' => $estado,
                'valor_compra' => $valor_compra, 'origen' => $origen_bien,
            ],
            (int)$_SESSION['user_id']
        );
        registrarAuditoria(
            $conn, 'crear_activo', 'inventario', 'elemento', $nuevoId,
            'Activo creado: ' . ($codigo_interno ?: $nombre),
            null,
            [
                'nombre' => $nombre, 'codigo' => $codigo_interno ?: null, 'tipo' => $tipo,
                'categoria' => $categoria ?: null, 'estado' => $estado,
                'sede' => $sedeInfo['nombre'] ?? null, 'ubicacion' => $ubicacion ?: null,
                'responsable' => $profesorNombre ?: null, 'numero_serie' => $numero_serie ?: null,
                'marca' => $marca ?: null, 'modelo' => $modelo ?: null,
                'valor_compra' => $valor_compra, 'origen' => $origen_bien,
            ]
        );
        $_SESSION['mensaje'] = 'Elemento agregado correctamente. Código: ' . $codigo_interno;
    } else {
        $_SESSION['mensaje'] = 'El nombre y el tipo son obligatorios';
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $codigo_interno = trim($_POST['codigo_interno'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $tipo_raw = trim($_POST['tipo']);
    $tipo = ($tipo_raw === '__otro__') ? trim($_POST['tipo_otro']) : $tipo_raw;
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $ram = trim($_POST['ram'] ?? '');
    $almacenamiento = trim($_POST['almacenamiento'] ?? '');
    $accesorios = trim($_POST['accesorios'] ?? '');
    $estado = trim($_POST['estado']);
    $ubicacion = trim($_POST['ubicacion']);
    $id_sede = !empty($_POST['id_sede']) ? (int)$_POST['id_sede'] : null;
    $profesor_id = !empty($_POST['profesor_id']) ? (int)$_POST['profesor_id'] : null;
    $origen_bien = isset($_POST['origen_bien']) && origenValido($_POST['origen_bien']) ? $_POST['origen_bien'] : null;
    $documento_no_disponible = isset($_POST['documento_no_disponible']) ? 1 : 0;
    $proveedor_id = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;
    $numero_factura = trim($_POST['numero_factura'] ?? '');
    $fecha_compra = !empty($_POST['fecha_compra']) ? $_POST['fecha_compra'] : null;
    $valor_compra = (!empty($_POST['valor_compra'])) ? (float)str_replace(',', '', $_POST['valor_compra']) : null;
    $numero_orden_compra = trim($_POST['numero_orden_compra'] ?? '');
    $fecha_garantia = !empty($_POST['fecha_garantia']) ? $_POST['fecha_garantia'] : null;
    $donante_nombre = trim($_POST['donante_nombre'] ?? '');
    $fecha_donacion = !empty($_POST['fecha_donacion']) ? $_POST['fecha_donacion'] : null;
    $institucion_origen = trim($_POST['institucion_origen'] ?? '');
    $fecha_transferencia = !empty($_POST['fecha_transferencia']) ? $_POST['fecha_transferencia'] : null;
    $descripcion_origen = trim($_POST['descripcion_origen'] ?? '');
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
    $descripcion = trim($_POST['descripcion']);
    $observacion = trim($_POST['observacion'] ?? '');
    $vr_comercial = (!isset($_POST['donado']) && isset($_POST['vr_comercial']) && $_POST['vr_comercial'] !== '') ? (float)str_replace(',', '', $_POST['vr_comercial']) : null;
    $vida_util = (!isset($_POST['donado']) && isset($_POST['vida_util']) && $_POST['vida_util'] !== '') ? (int)$_POST['vida_util'] : null;
    if ($origen_bien === 'Compra' && !$documento_no_disponible && !$proveedor_id) {
        $_SESSION['mensaje'] = 'Debe seleccionar un proveedor cuando el origen del bien es Compra';
        header('Location: index.php');
        exit;
    }
    if ($proveedor_id) {
        $provStmt = $conn->prepare("SELECT id FROM proveedores WHERE id=? AND estado='Activo'");
        $provStmt->execute([$proveedor_id]);
        if (!$provStmt->fetchColumn()) {
            $_SESSION['mensaje'] = 'El proveedor seleccionado no existe o está inactivo';
            header('Location: index.php');
            exit;
        }
    }
    if ($profesor_id && !profesorPerteneceSede($conn, $profesor_id, $id_sede)) {
        $_SESSION['mensaje'] = 'El responsable seleccionado no pertenece a la sede del elemento';
        header('Location: index.php');
        exit;
    }
    $sedeInfo = $conn->prepare("SELECT nombre FROM sedes WHERE id=?");
    $sedeInfo->execute([$id_sede]);
    $sedeNombre = $sedeInfo->fetchColumn();
    if ($sedeNombre && !ubicacionPerteneceSede($sedeNombre, $ubicacion)) {
        $_SESSION['mensaje'] = 'La ubicación seleccionada no pertenece a la sede del elemento';
        header('Location: index.php');
        exit;
    }
    $ubicCodigo = obtenerCodigoUbicacion($sedeNombre ?: '', $ubicacion);
    $disponible_prestamo = isset($_POST['disponible_para_prestamo']) ? 1 : 0;
    $oldStmt = $conn->prepare("SELECT ig.*, s.nombre as sede_ant_nombre, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as resp_ant_nombre FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id LEFT JOIN profesores p ON ig.profesor_id=p.id WHERE ig.id=?");
    $oldStmt->execute([$id]);
    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $conn->prepare("UPDATE inventario_general SET codigo_interno=?, nombre=?, categoria=?, tipo=?, marca=?, modelo=?, numero_serie=?, procesador=?, ram=?, almacenamiento=?, accesorios=?, estado=?, ubicacion=?, codigo_ubicacion=?, id_sede=?, profesor_id=?, origen_bien=?, documento_no_disponible=?, proveedor_id=?, numero_factura=?, fecha_compra=?, valor_compra=?, numero_orden_compra=?, fecha_garantia=?, donante_nombre=?, fecha_donacion=?, institucion_origen=?, fecha_transferencia=?, descripcion_origen=?, fecha_ingreso=?, descripcion=?, observacion=?, vr_comercial=?, vida_util=?, disponible_para_prestamo=? WHERE id=?")
        ->execute([$codigo_interno ?: null, $nombre, $categoria ?: null, $tipo, $marca ?: null, $modelo ?: null, $numero_serie ?: null, $procesador ?: null, $ram ?: null, $almacenamiento ?: null, $accesorios ?: null, $estado, $ubicacion, $ubicCodigo ?: null, $id_sede, $profesor_id, $origen_bien, $documento_no_disponible, $proveedor_id, $numero_factura ?: null, $fecha_compra, $valor_compra, $numero_orden_compra ?: null, $fecha_garantia, $donante_nombre ?: null, $fecha_donacion, $institucion_origen ?: null, $fecha_transferencia, $descripcion_origen ?: null, $fecha_ingreso, $descripcion ?: null, $observacion ?: null, $vr_comercial, $vida_util, $disponible_prestamo, $id]);
    if ($old) {
        $nuevoProfNombre = $profesor_id ? $conn->query("SELECT CONCAT(nombre, ' ', apellido) FROM profesores WHERE id=$profesor_id")->fetchColumn() : null;
        if ((int)$old['id_sede'] !== $id_sede || $old['ubicacion'] !== $ubicacion) {
            registrarEventoHistorial(
                $conn, $id, ((int)$old['id_sede'] !== $id_sede) ? 'cambio_sede' : 'cambio_ubicacion',
                ((int)$old['id_sede'] !== $id_sede) ? 'Cambio de sede y ubicación' : 'Cambio de ubicación',
                ['sede' => $old['sede_ant_nombre'] ?: '—', 'ubicacion' => $old['ubicacion'] ?: '—'],
                ['sede' => $sedeNombre ?: '—', 'ubicacion' => $ubicacion ?: '—'],
                (int)$_SESSION['user_id']
            );
            registrarAuditoria(
                $conn, ((int)$old['id_sede'] !== $id_sede) ? 'cambio_sede' : 'cambio_ubicacion', 'inventario', 'elemento', $id,
                ((int)$old['id_sede'] !== $id_sede) ? 'Cambio de sede y ubicación' : 'Cambio de ubicación',
                ['sede' => $old['sede_ant_nombre'] ?: '—', 'ubicacion' => $old['ubicacion'] ?: '—'],
                ['sede' => $sedeNombre ?: '—', 'ubicacion' => $ubicacion ?: '—']
            );
        }
        if ((int)$old['profesor_id'] !== (int)$profesor_id) {
            registrarEventoHistorial(
                $conn, $id, 'reasignacion', 'Reasignación de responsable',
                ['responsable' => $old['resp_ant_nombre'] ?: 'Sin asignar', 'responsable_id' => $old['profesor_id'] ? (int)$old['profesor_id'] : null],
                ['responsable' => $nuevoProfNombre ?: 'Sin asignar', 'responsable_id' => $profesor_id ? (int)$profesor_id : null],
                (int)$_SESSION['user_id']
            );
            registrarAuditoria(
                $conn, 'reasignar_activo', 'inventario', 'elemento', $id,
                'Reasignación: ' . ($old['resp_ant_nombre'] ?: 'Sin asignar') . ' → ' . ($nuevoProfNombre ?: 'Sin asignar'),
                ['responsable' => $old['resp_ant_nombre'] ?: 'Sin asignar', 'responsable_id' => $old['profesor_id'] ? (int)$old['profesor_id'] : null],
                ['responsable' => $nuevoProfNombre ?: 'Sin asignar', 'responsable_id' => $profesor_id ? (int)$profesor_id : null]
            );
        }
        if ($old['estado'] !== $estado) {
            registrarEventoHistorial($conn, $id, 'cambio_estado', 'Cambio de estado', ['estado' => $old['estado']], ['estado' => $estado], (int)$_SESSION['user_id']);
            registrarAuditoria(
                $conn, 'cambio_estado', 'inventario', 'elemento', $id,
                'Cambio de estado: ' . $old['estado'] . ' → ' . $estado,
                ['estado' => $old['estado']], ['estado' => $estado]
            );
        }
        $datosNuevos = [
            'nombre' => $nombre, 'tipo' => $tipo, 'estado' => $estado,
            'sede' => $sedeNombre ?: null, 'ubicacion' => $ubicacion ?: null,
            'responsable' => $nuevoProfNombre ?: null, 'valor_compra' => $valor_compra,
            'vr_comercial' => $vr_comercial, 'vida_util' => $vida_util, 'origen' => $origen_bien,
            'disponible_para_prestamo' => (int)$disponible_prestamo,
        ];
        registrarEventoHistorial($conn, $id, 'modificacion', 'Elemento actualizado', ['estado' => $old['estado']], $datosNuevos, (int)$_SESSION['user_id']);
        registrarAuditoria(
            $conn, 'editar_activo', 'inventario', 'elemento', $id,
            'Activo editado: ' . ($old['codigo_interno'] ?: $nombre),
            [
                'nombre' => $old['nombre'], 'tipo' => $old['tipo'], 'estado' => $old['estado'],
                'sede' => $old['sede_ant_nombre'] ?: null, 'ubicacion' => $old['ubicacion'] ?: null,
                'responsable' => $old['resp_ant_nombre'] ?: null, 'numero_serie' => $old['numero_serie'] ?: null,
                'valor_compra' => $old['valor_compra'], 'vr_comercial' => $old['vr_comercial'], 'origen' => $old['origen_bien'],
            ],
            $datosNuevos
        );
    }
    $docField = $documento_no_disponible ? null : campoDocumentoDe($origen_bien);
    $quitarDoc = isset($_POST['quitar_documento']);
    if ($quitarDoc) {
        $docActual = $conn->prepare("SELECT documento_adquisicion FROM inventario_general WHERE id=?");
        $docActual->execute([$id]);
        $docRuta = $docActual->fetchColumn();
        eliminarArchivoDocumento($docRuta);
        $conn->prepare("UPDATE inventario_general SET documento_adquisicion=NULL WHERE id=?")->execute([$id]);
        if ($docRuta) {
            registrarEventoHistorial($conn, $id, 'documento_eliminado', 'Documento de adquisición eliminado', ['archivo' => $docRuta], null, (int)$_SESSION['user_id']);
            registrarAuditoria($conn, 'eliminar_documento', 'inventario', 'elemento', $id, 'Documento de adquisición eliminado', ['archivo' => $docRuta], null);
        }
    }
    if ($docField && isset($_FILES[$docField]) && $_FILES[$docField]['error'] === UPLOAD_ERR_OK) {
        $validacion = validarDocumentoSubido($_FILES[$docField]);
        if (!$validacion['ok']) {
            $_SESSION['mensaje'] = 'No se guardó el elemento: ' . $validacion['error'];
            header('Location: index.php');
            exit;
        }
        $docActual = $conn->prepare("SELECT documento_adquisicion FROM inventario_general WHERE id=?");
        $docActual->execute([$id]);
        $docRuta = $docActual->fetchColumn();
        eliminarArchivoDocumento($docRuta);
        $ruta = guardarDocumento($validacion, $id);
        if ($ruta) {
            $conn->prepare("UPDATE inventario_general SET documento_adquisicion=? WHERE id=?")->execute([$ruta, $id]);
            registrarEventoHistorial($conn, $id, 'documento_agregado', 'Documento de adquisición actualizado', ['archivo' => $docRuta], ['archivo' => $ruta], (int)$_SESSION['user_id']);
            registrarAuditoria($conn, 'subir_documento', 'inventario', 'elemento', $id, 'Documento de adquisición subido', ['archivo' => $docRuta], ['archivo' => $ruta]);
        }
    } elseif ($documento_no_disponible) {
        $docActual = $conn->prepare("SELECT documento_adquisicion FROM inventario_general WHERE id=?");
        $docActual->execute([$id]);
        $docRuta = $docActual->fetchColumn();
        eliminarArchivoDocumento($docRuta);
        $conn->prepare("UPDATE inventario_general SET documento_adquisicion=NULL WHERE id=?")->execute([$id]);
        if ($docRuta) {
            registrarEventoHistorial($conn, $id, 'documento_eliminado', 'Documento de adquisición eliminado', ['archivo' => $docRuta], null, (int)$_SESSION['user_id']);
            registrarAuditoria($conn, 'eliminar_documento', 'inventario', 'elemento', $id, 'Documento de adquisición eliminado', ['archivo' => $docRuta], null);
        }
    }
    $_SESSION['mensaje'] = 'Elemento actualizado correctamente';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['dar_de_baja'])) {
    $id = (int)$_POST['id'];
    $motivo = trim($_POST['motivo_baja']);
    $fecha = trim($_POST['fecha_baja']);
    $valor_residual = !empty($_POST['valor_residual']) ? (float)str_replace(',', '', $_POST['valor_residual']) : null;
    $obs = trim($_POST['observaciones_baja']);
    
    $elInfo = $conn->query("SELECT nombre, codigo_interno FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    if ($elInfo) {
        $rutaEvidencia = null;
        if (isset($_FILES['evidencia_baja']) && $_FILES['evidencia_baja']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['evidencia_baja']['name'], PATHINFO_EXTENSION));
            $rutaDir = dirname(__DIR__) . '/uploads/bajas/';
            if (!is_dir($rutaDir)) { @mkdir($rutaDir, 0775, true); }
            $nombreArchivo = 'baja_' . $id . '_' . time() . '.' . ($ext ?: 'jpg');
            if (move_uploaded_file($_FILES['evidencia_baja']['tmp_name'], $rutaDir . $nombreArchivo)) {
                $rutaEvidencia = 'uploads/bajas/' . $nombreArchivo;
            }
        }
        
        $datosNuevos = [
            'estado' => 'Dado de baja',
            'motivo' => $motivo,
            'fecha' => $fecha,
            'valor_residual' => $valor_residual,
            'observaciones' => $obs,
            'evidencia' => $rutaEvidencia
        ];
        
        registrarEventoHistorial($conn, $id, 'baja', 'Baja del activo: ' . $motivo, ['nombre' => $elInfo['nombre'], 'codigo' => $elInfo['codigo_interno']], $datosNuevos, (int)$_SESSION['user_id']);
        registrarAuditoria($conn, 'dar_baja_activo', 'inventario', 'elemento', $id, 'Activo dado de baja: ' . ($elInfo['codigo_interno'] ?: $elInfo['nombre']), ['nombre' => $elInfo['nombre'], 'codigo' => $elInfo['codigo_interno']], $datosNuevos);
        
        // Cambiar estado, situación y deshabilitar préstamo. No lo eliminamos con activo=0 para que siga su historial, pero cambiamos su estado.
        $conn->prepare("UPDATE inventario_general SET situacion='dado_de_baja', estado='Dado de baja', disponible_para_prestamo=0 WHERE id=?")->execute([$id]);
        
        $_SESSION['mensaje'] = 'Elemento dado de baja correctamente.';
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    $elId = (int)$_POST['id'];
    $elInfo = $conn->query("SELECT nombre, codigo_interno FROM inventario_general WHERE id=$elId")->fetch(PDO::FETCH_ASSOC);
    if ($elInfo) {
        registrarEventoHistorial($conn, $elId, 'baja', 'Baja del activo', ['nombre' => $elInfo['nombre'], 'codigo' => $elInfo['codigo_interno']], null, (int)$_SESSION['user_id']);
        registrarAuditoria($conn, 'eliminar_activo', 'inventario', 'elemento', $elId, 'Activo eliminado: ' . ($elInfo['codigo_interno'] ?: $elInfo['nombre']), ['nombre' => $elInfo['nombre'], 'codigo' => $elInfo['codigo_interno']], null);
    }
    $conn->prepare("UPDATE inventario_general SET activo=0 WHERE id=?")->execute([$elId]);
    $_SESSION['mensaje'] = 'Elemento eliminado correctamente';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_todos'])) {
    $total = (int)$conn->query("SELECT COUNT(*) FROM inventario_general WHERE activo=1")->fetchColumn();
    $conn->exec("DELETE FROM inventario_general");
    registrarAuditoria($conn, 'eliminar_todos', 'inventario', null, null, 'Se eliminaron todos los activos del inventario (' . $total . ' registros)', ['total' => $total], null);
    $_SESSION['mensaje'] = 'Todos los datos han sido eliminados';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['regenerar_qrs'])) {
    $items = $conn->query("SELECT id, codigo_interno FROM inventario_general WHERE activo=1 AND codigo_interno IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach ($items as $it) {
        $qrPath = generarQR($it['codigo_interno'], $it['id']);
        $conn->prepare("UPDATE inventario_general SET qr_path=? WHERE id=?")->execute([$qrPath, $it['id']]);
        $count++;
    }
    $_SESSION['mensaje'] = "QR regenerados para $count elementos";
    registrarAuditoria($conn, 'regenerar_qrs', 'inventario', null, null, "Se regeneraron los códigos QR de $count elementos", null, ['total' => $count]);
    header('Location: index.php');
    exit;
}

$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_responsable = isset($_GET['responsable']) ? (int)$_GET['responsable'] : 0;
$filtro_sede = isset($_GET['sede']) ? (int)$_GET['sede'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE ig.activo=1";
$params = [];
if ($filtro_tipo) { $where .= " AND ig.tipo=?"; $params[] = $filtro_tipo; }
if ($filtro_estado) { $where .= " AND ig.estado=?"; $params[] = $filtro_estado; }
if ($filtro_ubicacion) { $where .= " AND ig.ubicacion=?"; $params[] = $filtro_ubicacion; }
if ($filtro_categoria) { $where .= " AND ig.categoria=?"; $params[] = $filtro_categoria; }
if ($filtro_responsable > 0) { $where .= " AND ig.profesor_id=?"; $params[] = $filtro_responsable; }
if ($filtro_sede > 0) { $where .= " AND ig.id_sede=?"; $params[] = $filtro_sede; }
if ($search != '') { $where .= " AND (ig.nombre LIKE ? OR ig.marca LIKE ? OR ig.modelo LIKE ? OR ig.codigo_interno LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]); }

$stmt = $conn->prepare("SELECT ig.*, s.nombre as sede_nombre, p.nombre as prof_nombre, p.apellido as prof_apellido, prov.nombre as proveedor_nombre, prov.nit as proveedor_nit FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id LEFT JOIN profesores p ON ig.profesor_id=p.id LEFT JOIN proveedores prov ON ig.proveedor_id=prov.id $where ORDER BY ig.tipo, ig.nombre");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tipos = $conn->query("SELECT DISTINCT tipo FROM inventario_general WHERE activo=1 ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
$estados_lista = $conn->query("SELECT DISTINCT estado FROM inventario_general WHERE activo=1 ORDER BY estado")->fetchAll(PDO::FETCH_COLUMN);
$ubicaciones = $conn->query("SELECT DISTINCT ubicacion FROM inventario_general WHERE activo=1 AND ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion")->fetchAll(PDO::FETCH_COLUMN);
$categorias = $conn->query("SELECT DISTINCT categoria FROM inventario_general WHERE activo=1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);

$stats = $conn->query("SELECT COUNT(*) as total_registros FROM inventario_general WHERE activo=1")->fetch(PDO::FETCH_ASSOC);
$stats_estados = $conn->query("SELECT estado, COUNT(*) as total FROM inventario_general WHERE activo=1 GROUP BY estado")->fetchAll(PDO::FETCH_ASSOC);
$tipos_count = $conn->query("SELECT COUNT(DISTINCT tipo) FROM inventario_general WHERE activo=1")->fetchColumn();
$total_ubicaciones = count($ubicaciones);
$sedes = $conn->query("SELECT id, nombre FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$profesores = $conn->query("SELECT id, nombre, apellido, sede_id FROM profesores WHERE estado='Activo' ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC);
$proveedoresLista = $conn->query("SELECT id, nombre, nit FROM proveedores WHERE estado='Activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

function qrUrl($path) {
    return $path ? "../assets/$path" : '';
}

function docUrl($path) {
    return $path ? "../uploads/$path" : '';
}

$estados_todos = array_column($estadosFormulario, 'nombre');

function badgeEstado($estado) {
    $map = [
        'bueno' => 'badge-success', 'nuevo' => 'badge-info',
        'regular' => 'badge-warning', 'malo' => 'badge-danger'
    ];
    return $map[$estado] ?? 'badge-secondary';
}

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);
?>
<?php
$pageTitle = 'Inventario General - MIC';
require_once '../includes/head.php';
?>
</head>
<?php
$paginaActual = '../modulo_inventario_general/index.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-warehouse"></i> Inventario General</h2>
        <p>Equipos tecnológicos, muebles, enseres y equipamiento</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="fas fa-plus"></i> Agregar Elemento
        </button>
        <a href="plantilla.php" class="btn btn-outline btn-sm" title="Descargar plantilla de importación"><i class="fas fa-file-excel"></i> Plantilla</a>
        <a href="importar.php" class="btn btn-outline btn-sm"><i class="fas fa-file-import"></i> Importar Excel</a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('¿ESTÁS SEGURO? Esta acción eliminará TODOS los datos de forma permanente.')">
            <input type="hidden" name="eliminar_todos" value="1">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash-alt"></i> Eliminar Todos</button>
        </form>
        <form method="POST" style="display:inline;">
            <button type="submit" name="regenerar_qrs" value="1" class="btn btn-outline btn-sm" onclick="return confirm('¿Regenerar todos los códigos QR? Esto actualizará los QR existentes con enlaces URL.')"><i class="fas fa-qrcode"></i> Regenerar QRs</button>
        </form>
    </div>
</div>

<?php if(isset($mensaje) && $mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon purple-gradient"><i class="fas fa-warehouse"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $stats['total_registros'] ?? 0; ?></div>
            <div class="kpi-label">Total Registros</div>
        </div>
    </div>
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon blue-gradient"><i class="fas fa-tags"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $tipos_count; ?></div>
            <div class="kpi-label">Tipos Distintos</div>
        </div>
    </div>
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon green-gradient"><i class="fas fa-map-marked-alt"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php echo $total_ubicaciones; ?></div>
            <div class="kpi-label">Ubicaciones</div>
        </div>
    </div>
    <div class="glass-card kpi-card" style="margin-bottom:0;">
        <div class="kpi-icon yellow-gradient"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-content">
            <div class="kpi-value"><?php
                $buenos = 0;
                foreach($stats_estados as $se) if(in_array($se['estado'], ['bueno','nuevo','disponible'])) $buenos += $se['total'];
                echo $buenos;
            ?></div>
            <div class="kpi-label">En Buen Estado</div>
        </div>
    </div>
</div>

<div class="glass-card" style="padding:18px 22px;margin-bottom:24px;">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;flex:2;min-width:160px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Buscar</label>
            <input type="text" class="form-control" name="search" placeholder="Nombre, marca, modelo o código..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;"><i class="fas fa-tag"></i> Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos los tipos</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filtro_tipo === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($t)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:110px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Estado</label>
            <select name="estado" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($estados_lista as $e): ?>
                <option value="<?php echo htmlspecialchars($e); ?>" <?php echo $filtro_estado === $e ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($e)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Sede</label>
            <select name="sede" class="form-control">
                <option value="">Todas</option>
                <?php foreach($sedes as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $filtro_sede == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;"><i class="fas fa-layer-group"></i> Categoría</label>
            <select name="categoria" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filtro_categoria === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($c)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;">Ubicación</label>
            <select name="ubicacion" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($ubicaciones as $u): ?>
                <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $filtro_ubicacion === $u ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($u)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:150px;">
            <label style="font-size:0.72rem;font-weight:600;color:var(--gray);margin-bottom:4px;display:block;"><i class="fas fa-user-tie"></i> Responsable</label>
            <select name="responsable" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($profesores as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $filtro_responsable == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:40px;"><i class="fas fa-filter"></i> Filtrar</button>
        <?php if($filtro_tipo || $filtro_estado || $filtro_ubicacion || $filtro_categoria || $filtro_responsable || $filtro_sede || $search): ?>
        <a href="index.php" class="btn btn-outline" style="height:40px;"><i class="fas fa-times"></i> Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<?php if(count($items) == 0): ?>
<div class="glass-card" style="padding:60px 20px;text-align:center;">
    <i class="fas fa-warehouse" style="font-size:3rem;color:var(--gray-light);margin-bottom:16px;display:block;"></i>
    <h3 style="font-weight:600;margin-bottom:8px;">No hay elementos</h3>
    <p style="color:var(--gray);font-size:0.88rem;">No se encontraron elementos con los filtros actuales.</p>
</div>
<?php else: ?>
<div class="glass-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="premium-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Especificaciones</th>
                    <th>Estado</th>
                    <th>Ubicación/Sede</th>
                    <th>Responsable</th>
                    <th>VR Comercial</th>
                    <th>Doc</th>
                    <th>QR</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): ?>
                <tr>
                    <td><span class="text-muted" style="font-weight:600;font-size:0.78rem;"><?php echo htmlspecialchars($item['codigo_interno'] ?? '#' . $item['id']); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($item['tipo']); ?></span></td>
                    <td style="font-size:0.8rem;">
                        <?php
                        $specs = array_filter([$item['marca'], $item['modelo'], $item['procesador'], $item['ram'], $item['almacenamiento']]);
                        echo $specs ? htmlspecialchars(implode(' · ', $specs)) : '<span class="text-muted">—</span>';
                        ?>
                    </td>
                    <td><span class="badge <?php echo badgeEstado($item['estado']); ?>"><?php echo ucfirst($item['estado']); ?></span></td>
                    <td style="font-size:0.82rem;">
                        <?php if($item['ubicacion']): ?><i class="fas fa-map-marker-alt" style="color:var(--gray);width:14px;"></i> <?php echo htmlspecialchars($item['ubicacion']); ?><?php endif; ?>
                        <?php if($item['sede_nombre']): ?><br><small style="color:var(--gray);"><i class="fas fa-building"></i> <?php echo htmlspecialchars($item['sede_nombre']); ?></small><?php endif; ?>
                        <?php if(!$item['ubicacion'] && !$item['sede_nombre']): ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;">
                        <?php if($item['prof_nombre']): ?><i class="fas fa-user-graduate" style="color:var(--gray);width:14px;"></i> <?php echo htmlspecialchars(trim($item['prof_nombre'] . ' ' . $item['prof_apellido'])); ?><?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?php echo $item['vr_comercial'] ? '$' . number_format($item['vr_comercial'], 0) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <?php $doc_ruta = docUrl($item['documento_adquisicion']); ?>
                        <?php if ($doc_ruta): ?>
                        <a href="../ver_articulo.php?codigo=<?php echo urlencode($item['codigo_interno'] ?? ''); ?>#documentacion" class="btn-icon" title="Ver documento de adquisición" style="color:var(--green);"><i class="fas fa-file-alt"></i></a>
                        <?php elseif ($item['documento_no_disponible']): ?>
                        <span class="btn-icon" title="Documento de adquisición no disponible" style="color:var(--orange);cursor:default;"><i class="fas fa-exclamation-triangle"></i></span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:0.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons" style="gap:2px;">
                            <?php $qr_url = qrUrl($item['qr_path']); ?>
                            <?php if ($qr_url): ?>
                            <button class="btn-icon" onclick="verQRModal(<?php echo $item['id']; ?>)" title="Ver QR"><i class="fas fa-qrcode"></i></button>
                            <a href="<?php echo htmlspecialchars($qr_url); ?>" download class="btn-icon" title="Descargar QR" style="display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-download"></i></a>
                            <button class="btn-icon" onclick="imprimirQRDesdeURL('<?php echo htmlspecialchars($qr_url); ?>')" title="Imprimir QR"><i class="fas fa-print"></i></button>
                            <a href="etiqueta.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn-icon" title="Imprimir Etiqueta" style="display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-tag"></i></a>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:0.75rem;">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if($item['estado'] === 'Dado de baja'): ?>
                            <a href="generar_acta_baja.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn-icon" style="color:var(--primary);border:none;background:none;cursor:pointer;" title="Imprimir Acta de Baja">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <form method="POST" style="display:inline;" id="form_eliminar_<?php echo $item['id']; ?>">
                                <?php echo campoCSRF(); ?>
                                <input type="hidden" name="eliminar" value="1">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            </form>
                            <button type="button" class="btn-icon btn-delete" onclick="if(confirm('¿Eliminar definitivamente?')) { document.getElementById('form_eliminar_<?php echo $item['id']; ?>').submit(); }" title="Eliminar definitivamente"><i class="fas fa-trash-alt"></i></button>
                            <?php else: ?>
                            <button class="btn-icon" onclick="editar(<?php echo $item['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn-icon btn-delete" onclick="abrirModalBaja(<?php echo $item['id']; ?>, '<?php echo addslashes($item['nombre']); ?>')" title="Dar de Baja"><i class="fas fa-level-down-alt"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="modal" id="qrModal">
    <div class="modal-content glass-card" style="max-width:420px;">
        <div class="modal-header">
            <h3><i class="fas fa-qrcode"></i> Código QR</h3>
            <button class="modal-close" onclick="closeModal('qrModal')">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:24px;">
            <div id="qrCodeContainer" style="margin-bottom:16px;"></div>
            <p style="font-size:0.82rem;color:var(--gray);margin-bottom:16px;" id="qrCodeText"></p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-primary btn-sm" onclick="descargarQR()"><i class="fas fa-download"></i> Descargar</button>
                <button class="btn btn-outline btn-sm" onclick="imprimirQR()"><i class="fas fa-print"></i> Imprimir</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR -->
<div class="modal" id="addModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Agregar Elemento</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="agregar" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nombre" placeholder="Ej: Portátil Dell, Silla universitaria" required>
                    </div>
                    <div class="form-group">
                        <label>Código</label>
                        <input type="text" class="form-control" value="Se genera automáticamente" disabled style="color:var(--gray);font-style:italic;">
                        <small style="color:var(--gray);font-size:0.72rem;">Formato: INST-SEDE-UBICACIÓN-CONSECUTIVO</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <div style="display:flex;gap:6px;">
                            <select class="form-control" name="categoria" id="add_categoria" onchange="cambiarCategoria('add')" style="flex:1;">
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categoriasCatalogo as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['nombre']); ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline btn-plus" title="Agregar categoría" onclick="abrirModalCatalogo('categoria','add')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipo <span class="required">*</span></label>
                        <div id="add_tipo_container">
                            <div style="display:flex;gap:6px;">
                                <select class="form-control" name="tipo" id="add_tipo" onchange="toggleEspecs('add')" style="flex:1;">
                                    <option value="">Primero seleccione una categoría</option>
                                </select>
                                <button type="button" class="btn btn-outline btn-plus" title="Agregar tipo" onclick="abrirModalCatalogo('tipo','add')"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <input type="text" class="form-control" name="tipo_otro" id="add_tipo_otro" placeholder="Escriba el tipo personalizado" style="display:none;margin-top:8px;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Estado</label>
                        <div style="display:flex;gap:6px;">
                            <select class="form-control" name="estado" id="add_estado" style="flex:1;">
                                <?php foreach ($estadosFormulario as $est): ?>
                                <option value="<?php echo htmlspecialchars($est['nombre']); ?>" <?php echo $est['nombre']=='bueno'?'selected':''; ?>><?php echo ucfirst($est['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline btn-plus" title="Agregar estado" onclick="abrirModalCatalogo('estado','add')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:end;padding-bottom:6px;">
                        <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="donado" id="add_donado" onchange="toggleDonado('add')" value="1">
                            <span style="font-size:0.88rem;font-weight:500;">Donado / No aplica VR</span>
                        </label>
                    </div>
                </div>
                <div id="add_specs_section" style="display:none;">
                    <div class="form-separator"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-microchip"></i> Especificaciones Técnicas</h4></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" class="form-control" name="marca" placeholder="Ej: Dell, HP, Lenovo">
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" class="form-control" name="modelo" placeholder="Ej: Inspiron, ThinkPad">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>N° de Serie</label>
                            <input type="text" class="form-control" name="numero_serie" placeholder="Número de serie">
                        </div>
                        <div class="form-group">
                            <label>Procesador</label>
                            <input type="text" class="form-control" name="procesador" placeholder="Ej: Intel i5, AMD Ryzen 5">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>RAM</label>
                            <input type="text" class="form-control" name="ram" placeholder="Ej: 8GB, 16GB">
                        </div>
                        <div class="form-group">
                            <label>Almacenamiento</label>
                            <input type="text" class="form-control" name="almacenamiento" placeholder="Ej: 256GB SSD, 1TB HDD">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Accesorios</label>
                        <input type="text" class="form-control" name="accesorios" placeholder="Ej: Cargador, mouse, teclado">
                    </div>
                </div>
                <div class="form-separator"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-info-circle"></i> Información General</h4></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Sede <span class="required">*</span></label>
                        <select class="form-control" name="id_sede" id="add_id_sede" onchange="cargarUbicaciones('add'); cargarProfesores('add')">
                            <option value="">Seleccionar</option>
                            <?php foreach($sedes as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ubicación <span class="required">*</span></label>
                        <select class="form-control" name="ubicacion" id="add_ubicacion">
                            <option value="">Primero seleccione una sede</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Responsable (Profesor)</label>
                    <select class="form-control" name="profesor_id" id="add_profesor_id" disabled>
                        <option value="">Primero seleccione una sede</option>
                    </select>
                    <small style="color:var(--gray);font-size:0.72rem;">Solo se listan profesores activos de la sede seleccionada.</small>
                </div>
                <div class="form-separator"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-file-invoice"></i> Información de Adquisición</h4></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Origen del bien</label>
                        <select class="form-control" name="origen_bien" id="add_origen_bien" onchange="cambiarOrigen('add')">
                            <option value="">Seleccione el origen</option>
                            <option value="Compra">Compra</option>
                            <option value="Donación">Donación</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:end;padding-bottom:6px;">
                        <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="documento_no_disponible" id="add_documento_no_disponible" value="1" onchange="toggleNoDisponible('add')">
                            <span style="font-size:0.85rem;font-weight:500;">No se dispone del documento de adquisición</span>
                        </label>
                    </div>
                </div>
                <p style="font-size:0.75rem;color:var(--gray);margin:0 0 12px;"><i class="fas fa-info-circle"></i> Marque esta opción si el documento fue extraviado, no existe o no está disponible.</p>
                <div id="add_compra_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Proveedor <span class="required">*</span></label>
                            <select class="form-control" name="proveedor_id" id="add_proveedor_id">
                                <option value="">Seleccione proveedor</option>
                                <?php foreach($proveedoresLista as $pv): ?>
                                <option value="<?php echo $pv['id']; ?>"><?php echo htmlspecialchars($pv['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Número de factura</label>
                            <input type="text" class="form-control" name="numero_factura" id="add_numero_factura" placeholder="Ej: FV-00123">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de compra</label>
                            <input type="date" class="form-control" name="fecha_compra" id="add_fecha_compra">
                        </div>
                        <div class="form-group">
                            <label>Valor de compra</label>
                            <input type="text" class="form-control" name="valor_compra" id="add_valor_compra" placeholder="0.00" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>N° de orden de compra</label>
                            <input type="text" class="form-control" name="numero_orden_compra" id="add_numero_orden_compra">
                        </div>
                        <div class="form-group">
                            <label>Vencimiento de garantía</label>
                            <input type="date" class="form-control" name="fecha_garantia" id="add_fecha_garantia">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Documento de compra</label>
                        <input type="file" class="form-control" name="documento_compra" id="add_compra_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB.</small>
                    </div>
                </div>
                <div id="add_donacion_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre del donante</label>
                            <input type="text" class="form-control" name="donante_nombre" id="add_donante_nombre">
                        </div>
                        <div class="form-group">
                            <label>Fecha de donación</label>
                            <input type="date" class="form-control" name="fecha_donacion" id="add_fecha_donacion">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Documento de donación</label>
                        <input type="file" class="form-control" name="documento_donacion" id="add_donacion_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Opcional.</small>
                    </div>
                </div>
                <div id="add_transferencia_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Institución de origen</label>
                            <input type="text" class="form-control" name="institucion_origen" id="add_institucion_origen">
                        </div>
                        <div class="form-group">
                            <label>Fecha de transferencia</label>
                            <input type="date" class="form-control" name="fecha_transferencia" id="add_fecha_transferencia">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Documento de transferencia</label>
                        <input type="file" class="form-control" name="documento_transferencia" id="add_transferencia_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Opcional.</small>
                    </div>
                </div>
                <div id="add_otro_fields" style="display:none;">
                    <div class="form-group">
                        <label>Descripción del origen</label>
                        <textarea class="form-control" name="descripcion_origen" id="add_descripcion_origen" rows="2" placeholder="Ej: Elemento legado, no se conoce su procedencia"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Documento relacionado</label>
                        <input type="file" class="form-control" name="documento_origen" id="add_otro_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Opcional.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de Ingreso</label>
                        <input type="date" class="form-control" name="fecha_ingreso" id="add_fecha_ingreso">
                    </div>
                    <div class="form-group">
                        <label>Vida Útil (años)</label>
                        <input type="number" class="form-control" name="vida_util" id="add_vida_util" min="0" placeholder="Años">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 15px; padding: 10px; background-color: rgba(66, 133, 244, 0.05); border-radius: 8px; border: 1px solid rgba(66, 133, 244, 0.2);">
                    <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                        <input type="checkbox" name="disponible_para_prestamo" id="add_disponible_para_prestamo" value="1" checked>
                        <span style="font-weight:600; color: var(--primary);">Disponible para préstamo</span>
                    </label>
                    <small style="color:var(--gray);font-size:0.75rem;margin-left:22px;display:block;">Permitir que este elemento sea solicitado en el módulo de Préstamos.</small>
                </div>
                <div class="form-group">
                    <label>Descripción General</label>
                    <textarea class="form-control" name="descripcion" rows="2" placeholder="Características adicionales..."></textarea>
                </div>
                <div class="form-group">
                    <label>Observación</label>
                    <textarea class="form-control" name="observacion" id="add_observacion" rows="2" placeholder="Cualquier otra observación..."></textarea>
                </div>
                <div class="form-row" id="add_comercial_row">
                    <div class="form-group">
                        <label>VR Comercial</label>
                        <input type="text" class="form-control" name="vr_comercial" id="add_vr_comercial" placeholder="0.00" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Guardar</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<!-- MODAL DAR DE BAJA -->
<div class="modal" id="bajaModal">
    <div class="modal-content glass-card" style="max-width:500px;">
        <div class="modal-header">
            <h3><i class="fas fa-level-down-alt"></i> Dar de Baja Activo</h3>
            <button class="modal-close" onclick="closeModal('bajaModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="dar_de_baja" value="1">
                <input type="hidden" name="id" id="baja_id" value="">
                
                <div class="alert alert-warning" style="margin-bottom: 15px;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Atención:</strong> Vas a dar de baja el elemento: <br>
                    <span id="baja_nombre_activo" style="font-weight:700;font-size:1.1rem;"></span>
                </div>

                <div class="form-group">
                    <label>Motivo de Baja <span class="required">*</span></label>
                    <select class="form-control" name="motivo_baja" required>
                        <option value="">Seleccione un motivo</option>
                        <option value="Daño irreparable">Daño irreparable</option>
                        <option value="Obsolescencia">Obsolescencia</option>
                        <option value="Pérdida">Pérdida</option>
                        <option value="Hurto">Hurto</option>
                        <option value="Deterioro">Deterioro</option>
                        <option value="Fin de vida útil">Fin de vida útil</option>
                        <option value="Donación">Donación</option>
                        <option value="Baja administrativa">Baja administrativa</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fecha de Baja <span class="required">*</span></label>
                    <input type="date" class="form-control" name="fecha_baja" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Valor residual (Si aplica)</label>
                    <input type="text" class="form-control" name="valor_residual" placeholder="0.00" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea class="form-control" name="observaciones_baja" rows="3" placeholder="Detalles sobre la baja del activo..."></textarea>
                </div>

                <div class="form-group">
                    <label>Evidencia / Documento de Soporte</label>
                    <input type="file" class="form-control" name="evidencia_baja" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color:var(--gray);font-size:0.75rem;">Opcional. Actas de pérdida, fotos, etc.</small>
                </div>

                <div class="form-actions" style="margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('bajaModal')">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-check"></i> Confirmar Baja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Elemento</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="editar" value="1">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Código</label>
                        <input type="text" class="form-control" name="codigo_interno" id="edit_codigo_interno" readonly style="background:#f5f5f5;cursor:not-allowed;">
                        <small style="color:var(--gray);font-size:0.72rem;">El código se asigna al crear y no puede modificarse.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <div style="display:flex;gap:6px;">
                            <select class="form-control" name="categoria" id="edit_categoria" onchange="cambiarCategoria('edit')" style="flex:1;">
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categoriasCatalogo as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['nombre']); ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline btn-plus" title="Agregar categoría" onclick="abrirModalCatalogo('categoria','edit')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipo <span class="required">*</span></label>
                        <div id="edit_tipo_container">
                            <div style="display:flex;gap:6px;">
                                <select class="form-control" name="tipo" id="edit_tipo" onchange="toggleEspecs('edit')" style="flex:1;">
                                    <option value="">Primero seleccione una categoría</option>
                                </select>
                                <button type="button" class="btn btn-outline btn-plus" title="Agregar tipo" onclick="abrirModalCatalogo('tipo','edit')"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <input type="text" class="form-control" name="tipo_otro" id="edit_tipo_otro" placeholder="Escriba el tipo personalizado" style="display:none;margin-top:8px;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Estado</label>
                        <div style="display:flex;gap:6px;">
                            <select class="form-control" name="estado" id="edit_estado" style="flex:1;">
                                <?php foreach ($estadosFormulario as $est): ?>
                                <option value="<?php echo htmlspecialchars($est['nombre']); ?>"><?php echo ucfirst($est['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline btn-plus" title="Agregar estado" onclick="abrirModalCatalogo('estado','edit')"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:end;padding-bottom:6px;">
                        <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="donado" id="edit_donado" onchange="toggleDonado('edit')" value="1">
                            <span style="font-size:0.88rem;font-weight:500;">Donado / No aplica VR</span>
                        </label>
                    </div>
                </div>
                <div id="edit_specs_section" style="display:none;">
                    <div class="form-separator"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-microchip"></i> Especificaciones Técnicas</h4></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" class="form-control" name="marca" id="edit_marca">
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" class="form-control" name="modelo" id="edit_modelo">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>N° de Serie</label>
                            <input type="text" class="form-control" name="numero_serie" id="edit_numero_serie">
                        </div>
                        <div class="form-group">
                            <label>Procesador</label>
                            <input type="text" class="form-control" name="procesador" id="edit_procesador">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>RAM</label>
                            <input type="text" class="form-control" name="ram" id="edit_ram">
                        </div>
                        <div class="form-group">
                            <label>Almacenamiento</label>
                            <input type="text" class="form-control" name="almacenamiento" id="edit_almacenamiento">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Accesorios</label>
                        <input type="text" class="form-control" name="accesorios" id="edit_accesorios">
                    </div>
                </div>
                <div class="form-separator"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-info-circle"></i> Información General</h4></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Sede <span class="required">*</span></label>
                        <select class="form-control" name="id_sede" id="edit_id_sede" onchange="cargarUbicaciones('edit'); cargarProfesores('edit')">
                            <option value="">Seleccionar</option>
                            <?php foreach($sedes as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ubicación <span class="required">*</span></label>
                        <select class="form-control" name="ubicacion" id="edit_ubicacion">
                            <option value="">Primero seleccione una sede</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Responsable (Profesor)</label>
                    <select class="form-control" name="profesor_id" id="edit_profesor_id" disabled>
                        <option value="">Primero seleccione una sede</option>
                    </select>
                    <small style="color:var(--gray);font-size:0.72rem;">Solo se listan profesores activos de la sede seleccionada.</small>
                </div>
                <div class="form-separator"><h4 style="font-size:14px;font-weight:600;margin:0;"><i class="fas fa-file-invoice"></i> Información de Adquisición</h4></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Origen del bien</label>
                        <select class="form-control" name="origen_bien" id="edit_origen_bien" onchange="cambiarOrigen('edit')">
                            <option value="">Seleccione el origen</option>
                            <option value="Compra">Compra</option>
                            <option value="Donación">Donación</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:end;padding-bottom:6px;">
                        <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="documento_no_disponible" id="edit_documento_no_disponible" value="1" onchange="toggleNoDisponible('edit')">
                            <span style="font-size:0.85rem;font-weight:500;">No se dispone del documento de adquisición</span>
                        </label>
                    </div>
                    <div class="form-group" style="display:flex;align-items:end;padding-bottom:6px;">
                        <label class="checkbox-inline" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="disponible_para_prestamo" id="edit_disponible_para_prestamo" value="1">
                            <span style="font-size:0.85rem;font-weight:500;">Disponible para préstamo</span>
                        </label>
                    </div>
                </div>
                <p style="font-size:0.75rem;color:var(--gray);margin:0 0 12px;"><i class="fas fa-info-circle"></i> Marque esta opción si el documento fue extraviado, no existe o no está disponible.</p>
                <div class="form-group" id="edit_doc_existente" style="display:none;">
                    <label>Documento actual</label>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <a href="#" id="edit_doc_link" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-alt"></i> Ver documento actual</a>
                        <label class="checkbox-inline" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="quitar_documento" id="edit_quitar_documento" value="1">
                            <span style="font-size:0.8rem;">Quitar documento actual</span>
                        </label>
                    </div>
                </div>
                <div id="edit_compra_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Proveedor <span class="required">*</span></label>
                            <select class="form-control" name="proveedor_id" id="edit_proveedor_id">
                                <option value="">Seleccione proveedor</option>
                                <?php foreach($proveedoresLista as $pv): ?>
                                <option value="<?php echo $pv['id']; ?>"><?php echo htmlspecialchars($pv['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Número de factura</label>
                            <input type="text" class="form-control" name="numero_factura" id="edit_numero_factura" placeholder="Ej: FV-00123">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de compra</label>
                            <input type="date" class="form-control" name="fecha_compra" id="edit_fecha_compra">
                        </div>
                        <div class="form-group">
                            <label>Valor de compra</label>
                            <input type="text" class="form-control" name="valor_compra" id="edit_valor_compra" placeholder="0.00" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>N° de orden de compra</label>
                            <input type="text" class="form-control" name="numero_orden_compra" id="edit_numero_orden_compra">
                        </div>
                        <div class="form-group">
                            <label>Vencimiento de garantía</label>
                            <input type="date" class="form-control" name="fecha_garantia" id="edit_fecha_garantia">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Documento de compra</label>
                        <input type="file" class="form-control" name="documento_compra" id="edit_compra_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Si no selecciona archivo se conserva el actual.</small>
                    </div>
                </div>
                <div id="edit_donacion_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre del donante</label>
                            <input type="text" class="form-control" name="donante_nombre" id="edit_donante_nombre">
                        </div>
                        <div class="form-group">
                            <label>Fecha de donación</label>
                            <input type="date" class="form-control" name="fecha_donacion" id="edit_fecha_donacion">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Documento de donación</label>
                        <input type="file" class="form-control" name="documento_donacion" id="edit_donacion_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Si no selecciona archivo se conserva el actual.</small>
                    </div>
                </div>
                <div id="edit_transferencia_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Institución de origen</label>
                            <input type="text" class="form-control" name="institucion_origen" id="edit_institucion_origen">
                        </div>
                        <div class="form-group">
                            <label>Fecha de transferencia</label>
                            <input type="date" class="form-control" name="fecha_transferencia" id="edit_fecha_transferencia">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Documento de transferencia</label>
                        <input type="file" class="form-control" name="documento_transferencia" id="edit_transferencia_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Si no selecciona archivo se conserva el actual.</small>
                    </div>
                </div>
                <div id="edit_otro_fields" style="display:none;">
                    <div class="form-group">
                        <label>Descripción del origen</label>
                        <textarea class="form-control" name="descripcion_origen" id="edit_descripcion_origen" rows="2" placeholder="Ej: Elemento legado, no se conoce su procedencia"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Documento relacionado</label>
                        <input type="file" class="form-control" name="documento_origen" id="edit_otro_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color:var(--gray);font-size:0.72rem;">PDF, JPG, JPEG o PNG. Máximo 5 MB. Si no selecciona archivo se conserva el actual.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de Ingreso</label>
                        <input type="date" class="form-control" name="fecha_ingreso" id="edit_fecha_ingreso">
                    </div>
                    <div class="form-group">
                        <label>Vida Útil (años)</label>
                        <input type="number" class="form-control" name="vida_util" id="edit_vida_util" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Observación</label>
                    <textarea class="form-control" name="observacion" id="edit_observacion" rows="2"></textarea>
                </div>
                <div class="form-row" id="edit_comercial_row">
                    <div class="form-group">
                        <label>VR Comercial</label>
                        <input type="text" class="form-control" name="vr_comercial" id="edit_vr_comercial" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CREAR CATÁLOGO (botones +) -->
<div class="modal" id="catalogModal">
    <div class="modal-content glass-card">
        <div class="modal-header">
            <h3 id="cat_modal_titulo"><i class="fas fa-plus-circle"></i> Agregar</h3>
            <button class="modal-close" onclick="closeModal('catalogModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group" id="cat_modal_categoria_field" style="display:none;">
                <label>Categoría <span class="required">*</span></label>
                <select class="form-control" id="cat_modal_categoria_id"></select>
            </div>
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" class="form-control" id="cat_modal_nombre" maxlength="50" placeholder="Ej: Equipos de Cómputo">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea class="form-control" id="cat_modal_descripcion" rows="2" placeholder="Información adicional..."></textarea>
            </div>
            <button type="button" class="btn btn-primary btn-block" onclick="guardarCatalogoNuevo()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<script>
var tiposPorCategoria = <?php echo json_encode($catalogos, JSON_UNESCAPED_UNICODE); ?>;
var CSRF_CATALOGO = <?php echo json_encode(generarTokenCSRF()); ?>;
var categoriasCatalogoJs = <?php echo json_encode($categoriasCatalogoNombres, JSON_UNESCAPED_UNICODE); ?>;
var modalCatalogoTipo = '';
var modalCatalogoPrefix = '';

function asegurarOpcion(select, valor) {
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].value === valor) return;
    }
    var o = document.createElement('option');
    o.value = valor;
    o.textContent = valor;
    select.appendChild(o);
}

function abrirModalCatalogo(tipo, prefix) {
    modalCatalogoTipo = tipo;
    modalCatalogoPrefix = prefix;
    document.getElementById('cat_modal_titulo').textContent = tipo === 'categoria' ? 'Agregar Categoría' : (tipo === 'tipo' ? 'Agregar Tipo' : 'Agregar Estado');
    document.getElementById('cat_modal_nombre').value = '';
    document.getElementById('cat_modal_descripcion').value = '';
    var catField = document.getElementById('cat_modal_categoria_field');
    if (tipo === 'tipo') {
        catField.style.display = 'block';
        var sel = document.getElementById('cat_modal_categoria_id');
        var actual = document.getElementById(prefix + '_categoria').value;
        sel.innerHTML = '';
        categoriasCatalogoJs.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            sel.appendChild(opt);
        });
        sel.value = actual;
        if (!sel.value && sel.options.length > 0) sel.selectedIndex = 0;
    } else {
        catField.style.display = 'none';
    }
    openModal('catalogModal');
    document.getElementById('cat_modal_nombre').focus();
}

function guardarCatalogoNuevo() {
    var tipo = modalCatalogoTipo;
    var nombre = document.getElementById('cat_modal_nombre').value.trim();
    var descripcion = document.getElementById('cat_modal_descripcion').value.trim();
    if (!nombre) { alert('El nombre es obligatorio'); return; }
    if (tipo === 'tipo' && !document.getElementById('cat_modal_categoria_id').value) {
        alert('Seleccione una categoría para el tipo');
        return;
    }
    var fd = new FormData();
    fd.append('accion', 'crear_' + tipo);
    fd.append('_csrf_token', CSRF_CATALOGO);
    fd.append('nombre', nombre);
    fd.append('descripcion', descripcion);
    if (tipo === 'tipo') fd.append('categoria_id', document.getElementById('cat_modal_categoria_id').value);
    fetch('acciones_catalogos.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.ok) { alert(res.error); return; }
            aplicarCatalogoNuevo(res);
        })
        .catch(function () { alert('Error de conexión'); });
}

function aplicarCatalogoNuevo(res) {
    closeModal('catalogModal');
    if (res.tipo === 'categoria') {
        ['add', 'edit'].forEach(function (p) {
            var s = document.getElementById(p + '_categoria');
            asegurarOpcion(s, res.nombre);
            s.value = res.nombre;
            cambiarCategoria(p);
        });
    } else if (res.tipo === 'tipo') {
        ['add', 'edit'].forEach(function (p) {
            var s = document.getElementById(p + '_tipo');
            if (document.getElementById(p + '_categoria').value === res.categoria_nombre) {
                asegurarOpcion(s, res.nombre);
                s.value = res.nombre;
            }
        });
    } else if (res.tipo === 'estado') {
        ['add', 'edit'].forEach(function (p) {
            var s = document.getElementById(p + '_estado');
            asegurarOpcion(s, res.nombre);
            s.value = res.nombre;
        });
    }
}
var ubicacionesPorSede = <?php
    $ubicacionesMap = [];
    foreach ($sedes as $s) {
        $nombres = [];
        if (isset($catalogosUbicaciones[$s['nombre']])) {
            foreach ($catalogosUbicaciones[$s['nombre']]['ubicaciones'] as $u) {
                $nombres[] = $u['nombre'];
            }
        }
        $ubicacionesMap[$s['id']] = $nombres;
    }
    echo json_encode($ubicacionesMap, JSON_UNESCAPED_UNICODE);
?>;
var profesoresPorSede = <?php
    $profesoresMap = [];
    foreach ($sedes as $s) {
        $profesoresMap[$s['id']] = [];
    }
    foreach ($profesores as $p) {
        $profesoresMap[$p['sede_id']][] = [
            'id' => $p['id'],
            'nombre' => trim($p['nombre'] . ' ' . $p['apellido'])
        ];
    }
    echo json_encode($profesoresMap, JSON_UNESCAPED_UNICODE);
?>;

function popularTipos(prefix, categoria) {
    var select = document.getElementById(prefix + '_tipo');
    var container = document.getElementById(prefix + '_tipo_container');
    var specsSection = document.getElementById(prefix + '_specs_section');
    select.innerHTML = '<option value="">Seleccione un tipo</option>';
    if (categoria && tiposPorCategoria[categoria]) {
        tiposPorCategoria[categoria].forEach(function(t) {
            var opt = document.createElement('option');
            opt.value = t;
            opt.textContent = t.charAt(0).toUpperCase() + t.slice(1);
            select.appendChild(opt);
        });
        var opt = document.createElement('option');
        opt.value = '__otro__';
        opt.textContent = 'Otro (especifique)';
        select.appendChild(opt);
    }
    specsSection.style.display = 'none';
    var otro = document.getElementById(prefix + '_tipo_otro');
    otro.style.display = 'none';
    mostrarOtroTipo(prefix + '_tipo', prefix + '_tipo_otro');
}

function cambiarCategoria(prefix) {
    var cat = document.getElementById(prefix + '_categoria').value;
    popularTipos(prefix, cat);
}

function cargarUbicaciones(prefix) {
    var sedeSelect = document.getElementById(prefix + '_id_sede');
    var ubicacionSelect = document.getElementById(prefix + '_ubicacion');
    var sedeId = sedeSelect.value;
    ubicacionSelect.innerHTML = '';
    if (sedeId && ubicacionesPorSede[sedeId] && ubicacionesPorSede[sedeId].length > 0) {
        ubicacionesPorSede[sedeId].forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u;
            opt.textContent = u;
            ubicacionSelect.appendChild(opt);
        });
    } else if (sedeId) {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No hay ubicaciones disponibles';
        opt.disabled = true;
        ubicacionSelect.appendChild(opt);
    } else {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Primero seleccione una sede';
        opt.disabled = true;
        ubicacionSelect.appendChild(opt);
    }
}

function cargarProfesores(prefix) {
    var sedeId = document.getElementById(prefix + '_id_sede').value;
    var profSelect = document.getElementById(prefix + '_profesor_id');
    profSelect.innerHTML = '';
    if (!sedeId) {
        profSelect.innerHTML = '<option value="">Primero seleccione una sede</option>';
        profSelect.disabled = true;
        return;
    }
    var list = (profesoresPorSede[sedeId] || []);
    if (list.length === 0) {
        profSelect.innerHTML = '<option value="">No hay profesores activos en esta sede</option>';
        profSelect.disabled = true;
        return;
    }
    list.forEach(function(p) {
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        profSelect.appendChild(opt);
    });
    profSelect.disabled = false;
}

function toggleDonado(prefix) {
    var checked = document.getElementById(prefix + '_donado').checked;
    var comercialRow = document.getElementById(prefix + '_comercial_row');
    var vidaUtil = document.getElementById(prefix + '_vida_util');
    if (checked) {
        comercialRow.style.display = 'none';
        vidaUtil.closest('.form-group').style.display = 'none';
    } else {
        comercialRow.style.display = 'flex';
        vidaUtil.closest('.form-group').style.display = 'block';
    }
}

var origenesMap = { 'Compra': 'compra', 'Donación': 'donacion', 'Transferencia': 'transferencia', 'Otro': 'otro' };

function cambiarOrigen(prefix) {
    var origen = document.getElementById(prefix + '_origen_bien').value;
    var key = origenesMap[origen] || null;
    ['compra', 'donacion', 'transferencia', 'otro'].forEach(function(s) {
        var el = document.getElementById(prefix + '_' + s + '_fields');
        if (el) el.style.display = (s === key) ? 'block' : 'none';
    });
    toggleNoDisponible(prefix);
}

function toggleNoDisponible(prefix) {
    var noDisp = document.getElementById(prefix + '_documento_no_disponible').checked;
    var origen = document.getElementById(prefix + '_origen_bien').value;
    var key = origenesMap[origen] || null;
    if (key) {
        var docInput = document.getElementById(prefix + '_' + key + '_documento');
        if (docInput) {
            docInput.disabled = noDisp;
            if (noDisp) docInput.value = '';
        }
    }
    var prov = document.getElementById(prefix + '_proveedor_id');
    if (prov) prov.required = (origen === 'Compra' && !noDisp);
}

function mostrarOtroTipo(selectId, otroId) {
    var select = document.getElementById(selectId);
    var otro = document.getElementById(otroId);
    if (select.value === '__otro__') {
        otro.style.display = 'block';
        otro.required = true;
        otro.focus();
    } else {
        otro.style.display = 'none';
        otro.required = false;
    }
}

function toggleEspecs(prefix) {
    var tipo = document.getElementById(prefix + '_tipo').value;
    var section = document.getElementById(prefix + '_specs_section');
    section.style.display = (tipo === 'Computador de escritorio' || tipo === 'Portátil') ? 'block' : 'none';
}

function determinarCategoria(tipo) {
    for (var cat in tiposPorCategoria) {
        if (tiposPorCategoria[cat].indexOf(tipo) !== -1) return cat;
    }
    return 'Otros';
}

function editar(id) {
    var d = itemsData[id];
    if (!d) { alert('Elemento no encontrado'); return; }
    document.getElementById('edit_id').value = d.id;
    document.getElementById('edit_codigo_interno').value = d.codigo_interno || '';
    document.getElementById('edit_nombre').value = d.nombre;
    var categoria = d.categoria || determinarCategoria(d.tipo);
    var catSel = document.getElementById('edit_categoria');
    asegurarOpcion(catSel, categoria);
    catSel.value = categoria;
    popularTipos('edit', categoria);
    var select = document.getElementById('edit_tipo');
    var otro = document.getElementById('edit_tipo_otro');
    if (select.querySelector('option[value="' + d.tipo.replace(/['"]/g, '') + '"]')) {
        select.value = d.tipo;
        otro.style.display = 'none';
        otro.required = false;
    } else {
        select.value = '__otro__';
        otro.style.display = 'block';
        otro.value = d.tipo;
    }
    toggleEspecs('edit');
    document.getElementById('edit_marca').value = d.marca || '';
    document.getElementById('edit_modelo').value = d.modelo || '';
    document.getElementById('edit_numero_serie').value = d.numero_serie || '';
    document.getElementById('edit_procesador').value = d.procesador || '';
    document.getElementById('edit_ram').value = d.ram || '';
    document.getElementById('edit_almacenamiento').value = d.almacenamiento || '';
    document.getElementById('edit_accesorios').value = d.accesorios || '';
    if (d.estado) {
        asegurarOpcion(document.getElementById('edit_estado'), d.estado);
        document.getElementById('edit_estado').value = d.estado;
    } else {
        document.getElementById('edit_estado').value = '';
    }
    document.getElementById('edit_id_sede').value = d.id_sede || '';
    cargarUbicaciones('edit');
    document.getElementById('edit_ubicacion').value = d.ubicacion || '';
    cargarProfesores('edit');
    document.getElementById('edit_profesor_id').value = d.profesor_id || '';
    document.getElementById('edit_fecha_ingreso').value = d.fecha_ingreso || '';
    document.getElementById('edit_descripcion').value = d.descripcion || '';
    document.getElementById('edit_observacion').value = d.observacion || '';
    document.getElementById('edit_vr_comercial').value = d.vr_comercial || '';
    document.getElementById('edit_vida_util').value = d.vida_util || '';
    var donadoChk = document.getElementById('edit_donado');
    donadoChk.checked = (!d.vr_comercial || d.vr_comercial == 0);
    toggleDonado('edit');
    document.getElementById('edit_origen_bien').value = d.origen_bien || '';
    document.getElementById('edit_documento_no_disponible').checked = (d.documento_no_disponible == 1);
    document.getElementById('edit_disponible_para_prestamo').checked = (d.disponible_para_prestamo == 1);
    document.getElementById('edit_proveedor_id').value = d.proveedor_id || '';
    document.getElementById('edit_numero_factura').value = d.numero_factura || '';
    document.getElementById('edit_fecha_compra').value = d.fecha_compra || '';
    document.getElementById('edit_valor_compra').value = d.valor_compra || '';
    document.getElementById('edit_numero_orden_compra').value = d.numero_orden_compra || '';
    document.getElementById('edit_fecha_garantia').value = d.fecha_garantia || '';
    document.getElementById('edit_donante_nombre').value = d.donante_nombre || '';
    document.getElementById('edit_fecha_donacion').value = d.fecha_donacion || '';
    document.getElementById('edit_institucion_origen').value = d.institucion_origen || '';
    document.getElementById('edit_fecha_transferencia').value = d.fecha_transferencia || '';
    document.getElementById('edit_descripcion_origen').value = d.descripcion_origen || '';
    cambiarOrigen('edit');
    toggleNoDisponible('edit');
    var docBlock = document.getElementById('edit_doc_existente');
    var docLink = document.getElementById('edit_doc_link');
    if (d.documento_adquisicion) {
        docLink.href = '../ver_archivo.php?ruta=' + encodeURIComponent(d.documento_adquisicion);
        docBlock.style.display = 'block';
    } else {
        docLink.href = '#';
        docBlock.style.display = 'none';
    }
    document.getElementById('edit_quitar_documento').checked = false;
    openModal('editModal');
}

var itemsData = <?php
    $itemsJs = [];
    foreach ($items as $it) {
        $itemsJs[$it['id']] = [
            'id' => $it['id'],
            'codigo_interno' => $it['codigo_interno'],
            'nombre' => $it['nombre'],
            'tipo' => $it['tipo'],
            'categoria' => $it['categoria'],
            'marca' => $it['marca'],
            'modelo' => $it['modelo'],
            'numero_serie' => $it['numero_serie'],
            'procesador' => $it['procesador'],
            'ram' => $it['ram'],
            'almacenamiento' => $it['almacenamiento'],
            'accesorios' => $it['accesorios'],
            'estado' => $it['estado'],
            'ubicacion' => $it['ubicacion'],
            'id_sede' => $it['id_sede'],
            'profesor_id' => $it['profesor_id'],
            'fecha_ingreso' => $it['fecha_ingreso'],
            'descripcion' => $it['descripcion'],
            'observacion' => $it['observacion'],
            'vr_comercial' => $it['vr_comercial'],
            'vida_util' => $it['vida_util'],
            'origen_bien' => $it['origen_bien'],
            'documento_no_disponible' => $it['documento_no_disponible'],
            'disponible_para_prestamo' => $it['disponible_para_prestamo'],
            'proveedor_id' => $it['proveedor_id'],
            'numero_factura' => $it['numero_factura'],
            'fecha_compra' => $it['fecha_compra'],
            'valor_compra' => $it['valor_compra'],
            'numero_orden_compra' => $it['numero_orden_compra'],
            'fecha_garantia' => $it['fecha_garantia'],
            'donante_nombre' => $it['donante_nombre'],
            'fecha_donacion' => $it['fecha_donacion'],
            'institucion_origen' => $it['institucion_origen'],
            'fecha_transferencia' => $it['fecha_transferencia'],
            'descripcion_origen' => $it['descripcion_origen'],
            'documento_adquisicion' => $it['documento_adquisicion'],
        ];
    }
    echo json_encode($itemsJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

var qrData = <?php
    $qrMap = [];
    foreach ($items as $it) {
        $qr = qrUrl($it['qr_path']);
        if ($qr) {
            $qrMap[$it['id']] = ['path' => $qr, 'codigo' => $it['codigo_interno']];
        }
    }
    echo json_encode($qrMap, JSON_UNESCAPED_UNICODE);
?>;

function abrirModalBaja(id, nombre) {
    document.getElementById('baja_id').value = id;
    document.getElementById('baja_nombre_activo').textContent = nombre;
    openModal('bajaModal');
}

function verQRModal(id) {
    var data = qrData[id];
    if (!data) { alert('QR no disponible'); return; }
    var container = document.getElementById('qrCodeContainer');
    container.innerHTML = '<img src="' + data.path + '" style="width:200px;height:200px;border-radius:8px;">';
    document.getElementById('qrCodeText').textContent = data.codigo;
    openModal('qrModal');
}

function descargarQR() {
    var img = document.querySelector('#qrCodeContainer img');
    if (!img) return;
    var a = document.createElement('a');
    a.download = 'qr.png';
    a.href = img.src;
    a.click();
}

function imprimirQR() {
    var img = document.querySelector('#qrCodeContainer img');
    if (!img) return;
    var w = window.open('');
    w.document.write('<img src="' + img.src + '" style="width:300px;">');
    w.document.close();
    w.focus();
    w.print();
}

function imprimirQRDesdeURL(url) {
    var w = window.open('');
    w.document.write('<img src="' + url + '" style="width:300px;">');
    w.document.close();
    w.focus();
    w.print();
}
</script>

<?php require_once '../includes/footer.php'; ?>

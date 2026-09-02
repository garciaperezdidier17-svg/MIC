<?php
/**
 * Script de generación de datos de prueba DEMO para MIC - Inventario Escolar.
 * Creado dinámicamente según requerimientos de prueba.
 * 
 * Ejecución: php database/seed_datos_prueba.php
 */

if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la linea de comandos (CLI).\n");
}

chdir(__DIR__);

// Inicializar sesión por si los helpers lo requieren (cli no tiene session, mockeamos)
if (session_status() === PHP_SESSION_NONE) {
    $_SESSION['user_id'] = 8; // ID de un admin real existente
    $_SESSION['user_rol'] = 'admin';
    $_SESSION['user_nombre'] = 'Admin Pruebas';
}

require_once '../config/conexion.php';
require_once '../config/helpers_auditoria.php';
require_once '../modulo_inventario_general/helpers_inventario.php';
require_once '../modulo_inventario_general/helpers_historial.php';
require_once '../modulo_prestamos/helpers_prestamos.php';

echo "========================================\n";
echo " MIC - CARGA DE DATOS DE PRUEBA (DEMO)\n";
echo "========================================\n";

$adminId = 8; // Usar id de admin (verificado que existe)

// ---------------------------------------------------------
// 1. UTILIDADES DE GENERACIÓN DE ARCHIVOS FALSOS
// ---------------------------------------------------------
$demoDir = __DIR__ . '/../uploads/demo';
if (!is_dir($demoDir)) {
    mkdir($demoDir, 0755, true);
}

function generarPdfDummy($nombreArchivo) {
    global $demoDir;
    $ruta = $demoDir . '/' . $nombreArchivo;
    $contenido = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n5 0 obj\n<< /Length 61 >>\nstream\nBT\n/F1 24 Tf\n100 700 Td\n(DOCUMENTO DE PRUEBA - DEMO - SIN VALIDEZ COMERCIAL) Tj\nET\nendstream\nendobj\nxref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000223 00000 n \n0000000311 00000 n \ntrailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n423\n%%EOF";
    file_put_contents($ruta, $contenido);
    return "demo/$nombreArchivo";
}

function generarJpgDummy($nombreArchivo) {
    global $demoDir;
    $ruta = $demoDir . '/' . $nombreArchivo;
    if (function_exists('imagecreate')) {
        $im = imagecreate(400, 300);
        $fondo = imagecolorallocate($im, 240, 240, 240);
        $texto = imagecolorallocate($im, 255, 0, 0);
        imagestring($im, 5, 50, 140, "EVIDENCIA DEMO - PRUEBA", $texto);
        imagejpeg($im, $ruta);
        imagedestroy($im);
    } else {
        // Fallback si no hay gd
        file_put_contents($ruta, "DUMMY IMAGE");
    }
    return "demo/$nombreArchivo";
}

// ---------------------------------------------------------
// 2. CREACIÓN DE PROVEEDORES
// ---------------------------------------------------------
$proveedores = [
    ['nombre' => 'Proveedor Escolar Demo S.A.S.', 'nit' => 'DEMO-900100100-1', 'telefono' => '3000000001', 'correo' => 'ventas1@demo.com'],
    ['nombre' => 'Tecnología Educativa Demo S.A.S.', 'nit' => 'DEMO-900200200-2', 'telefono' => '3000000002', 'correo' => 'ventas2@demo.com'],
    ['nombre' => 'Mobiliario Escolar Demo S.A.S.', 'nit' => 'DEMO-900300300-3', 'telefono' => '3000000003', 'correo' => 'ventas3@demo.com']
];

$proveedoresIds = [];
$stats = ['proveedores' => 0, 'elementos' => 0, 'documentos' => 0, 'prestamos' => 0, 'tomas' => 0, 'bajas' => 0];

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SCRIPT_NAME'] = '/MIC/index.php';

try {
    foreach ($proveedores as $p) {
        $stmt = $conn->prepare("SELECT id FROM proveedores WHERE nit = ?");
        $stmt->execute([$p['nit']]);
        if ($id = $stmt->fetchColumn()) {
            $proveedoresIds[] = $id;
        } else {
            $ins = $conn->prepare("INSERT INTO proveedores (nombre, nit, telefono, correo) VALUES (?, ?, ?, ?)");
            $ins->execute([$p['nombre'], $p['nit'], $p['telefono'], $p['correo']]);
            $proveedoresIds[] = $conn->lastInsertId();
            $stats['proveedores']++;
        }
    }
    
    // Obtener sedes y profesores reales
    $sedes = $conn->query("SELECT id, nombre, codigo FROM sedes WHERE activo=1")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($sedes)) throw new Exception("No hay sedes en la BD para relacionar.");
    $sede1 = $sedes[0];
    
    $profesores = $conn->prepare("SELECT id FROM profesores WHERE sede_id = ? AND estado = 'Activo'");
    $profesores->execute([$sede1['id']]);
    $profs = $profesores->fetchAll(PDO::FETCH_COLUMN);
    $prof1 = !empty($profs) ? $profs[0] : null;

    // Obtener categorías reales (si no hay, insertamos una para poder avanzar)
    $catId = $conn->query("SELECT nombre FROM categorias LIMIT 1")->fetchColumn();
    if (!$catId) $catId = 'Tecnología';

    $elementosPrueba = [
        [
            'codigo_interno' => 'DEMO-INV-001',
            'nombre' => 'Computador de escritorio DEMO',
            'tipo' => 'Equipo', 'categoria' => $catId, 'marca' => 'HP', 'modelo' => 'ProDesk',
            'numero_serie' => 'SN-DEMO-001', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Sala Informática',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 1
        ],
        [
            'codigo_interno' => 'DEMO-INV-002',
            'nombre' => 'Computador portátil DEMO',
            'tipo' => 'Equipo', 'categoria' => $catId, 'marca' => 'Dell', 'modelo' => 'Latitude',
            'numero_serie' => 'SN-DEMO-002', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Biblioteca',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 1
        ],
        [
            'codigo_interno' => 'DEMO-INV-003',
            'nombre' => 'Video beam/proyector DEMO',
            'tipo' => 'Audiovisual', 'categoria' => $catId, 'marca' => 'Epson', 'modelo' => 'X39',
            'numero_serie' => 'SN-DEMO-003', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Salón 1',
            'profesor_id' => null, 'disponible_para_prestamo' => 1
        ],
        [
            'codigo_interno' => 'DEMO-INV-004',
            'nombre' => 'Impresora DEMO',
            'tipo' => 'Periférico', 'categoria' => $catId, 'marca' => 'Kyocera', 'modelo' => 'M2040',
            'numero_serie' => 'SN-DEMO-004', 'estado' => 'regular', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Administración',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-005',
            'nombre' => 'Televisor 55 Pulgadas DEMO',
            'tipo' => 'Audiovisual', 'categoria' => $catId, 'marca' => 'Samsung', 'modelo' => 'Crystal',
            'numero_serie' => 'SN-DEMO-005', 'estado' => 'malo', 'situacion' => 'disponible', // En mantenimiento
            'id_sede' => $sede1['id'], 'ubicacion' => 'Sala Profesores',
            'profesor_id' => null, 'disponible_para_prestamo' => 1
        ],
        [
            'codigo_interno' => 'DEMO-INV-006',
            'nombre' => 'Tablet DEMO',
            'tipo' => 'Equipo', 'categoria' => $catId, 'marca' => 'Lenovo', 'modelo' => 'Tab M10',
            'numero_serie' => 'SN-DEMO-006', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Almacén',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 1
        ],
        [
            'codigo_interno' => 'DEMO-INV-007',
            'nombre' => 'Escritorio Docente DEMO',
            'tipo' => 'Mobiliario', 'categoria' => 'Mobiliario', 'marca' => 'N/A', 'modelo' => 'N/A',
            'numero_serie' => 'SN-DEMO-007', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Salón 2',
            'profesor_id' => null, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-008',
            'nombre' => 'Silla Universitaria DEMO',
            'tipo' => 'Mobiliario', 'categoria' => 'Mobiliario', 'marca' => 'N/A', 'modelo' => 'N/A',
            'numero_serie' => 'SN-DEMO-008', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Salón 2',
            'profesor_id' => null, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-009',
            'nombre' => 'Archivador DEMO',
            'tipo' => 'Mobiliario', 'categoria' => 'Mobiliario', 'marca' => 'N/A', 'modelo' => 'N/A',
            'numero_serie' => 'SN-DEMO-009', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Archivo',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-010',
            'nombre' => 'Tablero Acrílico DEMO',
            'tipo' => 'Mobiliario', 'categoria' => 'Mobiliario', 'marca' => 'N/A', 'modelo' => 'N/A',
            'numero_serie' => 'SN-DEMO-010', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Salón 1',
            'profesor_id' => null, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-011',
            'nombre' => 'Ventilador DEMO',
            'tipo' => 'Electrodoméstico', 'categoria' => 'Equipos', 'marca' => 'Samurai', 'modelo' => 'Turbo',
            'numero_serie' => 'SN-DEMO-011', 'estado' => 'regular', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Salón 3',
            'profesor_id' => null, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-012',
            'nombre' => 'Estante Metálico DEMO',
            'tipo' => 'Mobiliario', 'categoria' => 'Mobiliario', 'marca' => 'N/A', 'modelo' => 'N/A',
            'numero_serie' => 'SN-DEMO-012', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Biblioteca',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-013',
            'nombre' => 'Mesa de Juntas DEMO',
            'tipo' => 'Mobiliario', 'categoria' => 'Mobiliario', 'marca' => 'N/A', 'modelo' => 'N/A',
            'numero_serie' => 'SN-DEMO-013', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Sala Juntas',
            'profesor_id' => null, 'disponible_para_prestamo' => 0
        ],
        [
            'codigo_interno' => 'DEMO-INV-014',
            'nombre' => 'Cámara Documentadora DEMO',
            'tipo' => 'Audiovisual', 'categoria' => $catId, 'marca' => 'Lumens', 'modelo' => 'DC193',
            'numero_serie' => 'SN-DEMO-014', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Laboratorio',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 1
        ],
        [
            'codigo_interno' => 'DEMO-INV-015',
            'nombre' => 'Equipo de Sonido DEMO',
            'tipo' => 'Audiovisual', 'categoria' => $catId, 'marca' => 'Sony', 'modelo' => 'V13',
            'numero_serie' => 'SN-DEMO-015', 'estado' => 'bueno', 'situacion' => 'disponible',
            'id_sede' => $sede1['id'], 'ubicacion' => 'Auditorio',
            'profesor_id' => $prof1, 'disponible_para_prestamo' => 1
        ]
    ];

    $elementosInsertadosIds = [];

    // Limpiar los elementos DEMO para reiniciar y no duplicar infinitamente si se corre múltiples veces
    $conn->exec("DELETE pe FROM prestamo_elementos pe JOIN inventario_general ig ON pe.id_elemento=ig.id WHERE ig.codigo_interno LIKE 'DEMO-INV-%'");
    $conn->exec("DELETE p FROM prestamos p JOIN prestamo_elementos pe ON p.id=pe.id_prestamo JOIN inventario_general ig ON pe.id_elemento=ig.id WHERE ig.codigo_interno LIKE 'DEMO-INV-%'");
    $conn->exec("DELETE eh FROM elemento_historial eh JOIN inventario_general ig ON eh.elemento_id=ig.id WHERE ig.codigo_interno LIKE 'DEMO-INV-%'");
    $conn->exec("DELETE b FROM bajas b JOIN inventario_general ig ON b.elemento_id=ig.id WHERE ig.codigo_interno LIKE 'DEMO-INV-%'");
    $conn->exec("DELETE tfd FROM tomas_fisicas_detalle tfd JOIN inventario_general ig ON tfd.elemento_id=ig.id WHERE ig.codigo_interno LIKE 'DEMO-INV-%'");
    $conn->exec("DELETE FROM inventario_general WHERE codigo_interno LIKE 'DEMO-INV-%'");

    foreach ($elementosPrueba as $idx => $e) {
        $pdfPath = generarPdfDummy("FACTURA_DEMO_" . ($idx+1) . ".pdf");
        $stats['documentos']++;

        $ins = $conn->prepare(
            "INSERT INTO inventario_general 
            (codigo_interno, nombre, tipo, categoria, marca, modelo, numero_serie, estado, situacion, 
            id_sede, ubicacion, profesor_id, disponible_para_prestamo, origen_bien, proveedor_id, numero_factura, 
            fecha_compra, valor_compra, documento_adquisicion, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Compra', ?, ?, CURDATE(), 1000000.00, ?, 1)"
        );
        $ins->execute([
            $e['codigo_interno'], $e['nombre'], $e['tipo'], $e['categoria'], $e['marca'], $e['modelo'],
            $e['numero_serie'], $e['estado'], $e['situacion'], $e['id_sede'], $e['ubicacion'], $e['profesor_id'],
            $e['disponible_para_prestamo'], $proveedoresIds[array_rand($proveedoresIds)], 
            'FAC-DEMO-' . rand(1000, 9999), $pdfPath
        ]);
        
        $elemId = $conn->lastInsertId();
        $elementosInsertadosIds[$e['codigo_interno']] = $elemId;
        $stats['elementos']++;

        // Generar QR
        $qrRelPath = generarQR($e['codigo_interno'], $elemId);
        if ($qrRelPath) {
            $conn->prepare("UPDATE inventario_general SET qr_path = ? WHERE id = ?")->execute([$qrRelPath, $elemId]);
        }

        // Registrar en historial
        registrarEventoHistorial($conn, $elemId, 'creacion', 'Elemento de prueba DEMO registrado.', null, 
            ['codigo_interno' => $e['codigo_interno'], 'situacion' => $e['situacion']], $adminId);
    }

    // ---------------------------------------------------------
    // 3. PRÉSTAMOS
    // ---------------------------------------------------------
    if ($prof1 && isset($elementosInsertadosIds['DEMO-INV-001'])) {
        // Préstamo 1: Entregado y Activo
        $sol = crearSolicitud($conn, [
            'usuario_id' => $adminId, 'id_profesor' => $prof1, 'id_sede' => $sede1['id'],
            'motivo' => 'Préstamo de prueba DEMO', 'fecha_prestamo' => date('Y-m-d'), 'hora_prestamo' => '08:00',
            'fecha_devolucion_esperada' => date('Y-m-d', strtotime('+5 days')), 'hora_devolucion_esperada' => '17:00',
            'observaciones' => 'Prueba DEMO',
            'items' => [['elemento_id' => $elementosInsertadosIds['DEMO-INV-001'], 'cantidad' => 1, 'tipo_prestamo' => 'individual']]
        ]);
        
        if ($sol['ok']) {
            aprobarSolicitud($conn, $adminId, $sol['solicitud_id']);
            $stats['prestamos']++;
        }

        // Préstamo 2: Vencido y Devuelto con daño
        $sol2 = crearSolicitud($conn, [
            'usuario_id' => $adminId, 'id_profesor' => $prof1, 'id_sede' => $sede1['id'],
            'motivo' => 'Préstamo a devolver con daño DEMO', 'fecha_prestamo' => date('Y-m-d', strtotime('-10 days')),
            'fecha_devolucion_esperada' => date('Y-m-d', strtotime('-5 days')),
            'items' => [['elemento_id' => $elementosInsertadosIds['DEMO-INV-002'], 'cantidad' => 1, 'tipo_prestamo' => 'individual']]
        ]);
        if ($sol2['ok']) {
            $ap2 = aprobarSolicitud($conn, $adminId, $sol2['solicitud_id']);
            if ($ap2['ok']) {
                // Forzar fecha en db para vencimiento real
                $conn->exec("UPDATE prestamos SET fecha_devolucion_esperada = DATE_SUB(CURDATE(), INTERVAL 5 DAY) WHERE id = " . $ap2['prestamo_id']);
                detectarVencidos($conn);
                
                // Adjuntar evidencia dummy para daño
                $evidenciaDano = generarJpgDummy('EVIDENCIA_DEMO_001.jpg');
                $stats['documentos']++;
                
                // Obtener el ID del renglon de prestamo
                $renglonId = $conn->query("SELECT id FROM prestamo_elementos WHERE id_prestamo = " . $ap2['prestamo_id'])->fetchColumn();
                
                registrarDevolucion($conn, $adminId, $ap2['prestamo_id'], [
                    $renglonId => ['estado' => 'Dañado', 'observaciones' => 'Pantalla rota (Prueba)', 'evidencia' => $evidenciaDano]
                ]);
                $stats['prestamos']++;
            }
        }
    }

    // ---------------------------------------------------------
    // 4. BAJA DE ELEMENTO
    // ---------------------------------------------------------
    if (isset($elementosInsertadosIds['DEMO-INV-008'])) {
        $idBajaEl = $elementosInsertadosIds['DEMO-INV-008'];
        $docBaja = generarPdfDummy('ACTA_BAJA_DEMO_001.pdf');
        $stats['documentos']++;
        
        $conn->prepare("INSERT INTO bajas (elemento_id, motivo, fecha_baja, descripcion, documento_baja, usuario_solicita, estado, aprobado_por, fecha_aprobacion) VALUES (?, ?, CURDATE(), ?, ?, ?, 'aprobada', ?, NOW())")
             ->execute([$idBajaEl, 'Obsolescencia', 'Elemento dado de baja de prueba DEMO', $docBaja, $adminId, $adminId]);
        
        $conn->prepare("UPDATE inventario_general SET activo=0, estado='baja' WHERE id=?")->execute([$idBajaEl]);
        registrarEventoHistorial($conn, $idBajaEl, 'baja', 'Elemento dado de baja definitivamente (DEMO)', null, ['estado' => 'baja', 'activo' => 0], $adminId);
        $stats['bajas']++;
    }

    // ---------------------------------------------------------
    // 5. TOMA FÍSICA
    // ---------------------------------------------------------
    $conn->prepare("INSERT INTO tomas_fisicas (sede_id, ubicacion, usuario_id, estado, total_esperados, encontrados, en_buen_estado) VALUES (?, 'Biblioteca', ?, 'finalizada', 2, 2, 2)")
         ->execute([$sede1['id'], $adminId]);
    $idToma = $conn->lastInsertId();
    $stats['tomas']++;

    if (isset($elementosInsertadosIds['DEMO-INV-002'])) {
        $conn->prepare("INSERT INTO tomas_fisicas_detalle (toma_fisica_id, elemento_id, encontrado, estado_encontrado, coincide_codigo, coincide_sede, coincide_ubicacion) VALUES (?, ?, 1, 'bueno', 1, 1, 1)")
             ->execute([$idToma, $elementosInsertadosIds['DEMO-INV-002']]);
    }
    
    echo "Carga DEMO completada correctamente.\n";
    echo "========================================\n";
    echo " Proveedores creados/revisados: {$stats['proveedores']}\n";
    echo " Elementos creados: {$stats['elementos']}\n";
    echo " Documentos DEMO generados: {$stats['documentos']}\n";
    echo " Préstamos simulados: {$stats['prestamos']}\n";
    echo " Bajas registradas: {$stats['bajas']}\n";
    echo " Tomas físicas creadas: {$stats['tomas']}\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "Error ejecutando el script DEMO: " . $e->getMessage() . "\n";
    exit(1);
}

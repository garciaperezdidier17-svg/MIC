<?php
/**
 * Importación masiva de inventario desde Excel.
 * Reutiliza: generarCodigoElemento, generarQR, obtenerCodigoUbicacion,
 * registrarEventoHistorial, catálogos de configuración y la BD real.
 *
 * Seguridad:
 * - Validación de extensión, MIME y tamaño del archivo.
 * - Lectura con PhpSpreadsheet en modo "read data only"
 *   (no se evalúan fórmulas ni macros).
 * - Consultas preparadas, transacción + bloqueo de aplicación (GET_LOCK)
 *   para evitar códigos duplicados en importaciones concurrentes.
 */

require_once __DIR__ . '/helpers_inventario.php';
require_once __DIR__ . '/helpers_historial.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const MAX_EXCEL_IMPORT_SIZE = 5 * 1024 * 1024;
const MAX_EXCEL_FILAS = 2000;

const COLUMNAS_IMPORTACION = [
    'nombre'         => 'Nombre',
    'categoria'      => 'Categoría',
    'tipo'           => 'Tipo',
    'sede'           => 'Sede',
    'ubicacion'      => 'Ubicación',
    'responsable'    => 'Responsable',
    'estado'         => 'Estado',
    'marca'          => 'Marca',
    'modelo'         => 'Modelo',
    'numero_serie'   => 'Serial',
    'vida_util'      => 'Vida útil',
    'valor_comercial'=> 'Valor comercial',
    'valor_compra'   => 'Valor de compra',
    'descripcion'    => 'Descripción',
];

const ESTADOS_VALIDOS_IMPORT = ['bueno', 'regular', 'malo', 'nuevo', 'dañado', 'fuera de servicio', 'disponible'];

/**
 * Valida el archivo Excel subido (extensión, MIME y tamaño).
 */
function validarArchivoExcelSubido($archivo) {
    if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir el archivo'];
    }
    if ((int)$archivo['size'] <= 0) {
        return ['ok' => false, 'error' => 'El archivo está vacío'];
    }
    if ((int)$archivo['size'] > MAX_EXCEL_IMPORT_SIZE) {
        return ['ok' => false, 'error' => 'El archivo supera el tamaño máximo permitido (5 MB)'];
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        return ['ok' => false, 'error' => 'Solo se permiten archivos Excel (.xlsx o .xls)'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    $permitidos = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.ms-office',
        'application/zip',
        'application/octet-stream',
    ];
    if (!in_array($mime, $permitidos, true)) {
        return ['ok' => false, 'error' => 'El tipo de archivo no es un Excel válido'];
    }
    return ['ok' => true, 'error' => '', 'ext' => $ext];
}

/**
 * Lee las filas del Excel y las devuelve asociativas por nombre de columna
 * según COLUMNAS_IMPORTACION. Solo lectura de datos (sin fórmulas/macros).
 */
function leerFilasExcel($rutaTmp) {
    $reader = IOFactory::createReaderForFile($rutaTmp);
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $spreadsheet = $reader->load($rutaTmp);
    $sheets = $spreadsheet->getSheetNames();
    $hojaDatos = $sheets[0] ?? null;
    if (!$hojaDatos) {
        throw new RuntimeException('El archivo Excel no contiene hojas de datos');
    }
    $filasBrutas = $spreadsheet->getSheetByName($hojaDatos)->toArray(null, true, false, false);

    $encabezados = null;
    $filas = [];
    foreach ($filasBrutas as $indice => $fila) {
        if (!is_array($fila) || count(array_filter($fila, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        if ($encabezados === null) {
            $encabezados = array_map(fn($v) => mb_strtolower(trim((string)$v)), $fila);
            continue;
        }
        $filaAsoc = [];
        foreach (COLUMNAS_IMPORTACION as $campo => $etiqueta) {
            $pos = array_search(mb_strtolower($etiqueta), $encabezados, true);
            $filaAsoc[$campo] = $pos !== false ? trim((string)($fila[$pos] ?? '')) : '';
        }
        $filaAsoc['_fila_excel'] = $indice + 1;
        $filas[] = $filaAsoc;
    }
    return $filas;
}

/**
 * Contexto de validación: catálogos, sedes, ubicaciones, profesores y estados.
 */
function contextoImportacion($conn) {
    global $catalogosUbicaciones;
    $sedes = [];
    foreach ($conn->query("SELECT id, nombre, codigo FROM sedes WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $sedes[mb_strtolower(trim($s['nombre']))] = $s;
    }
    $profesores = [];
    foreach ($conn->query("SELECT id, nombre, apellido, sede_id FROM profesores WHERE estado='Activo' ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $nombreCompleto = mb_strtolower(trim($p['nombre'] . ' ' . $p['apellido']));
        $profesores[$nombreCompleto] = $p;
    }
    $catalogo = obtenerCatalogoInventarioBD($conn);
    $ubicaciones = $catalogosUbicaciones ?? [];
    return [
        'sedes' => $sedes,
        'profesores' => $profesores,
        'catalogo' => $catalogo,
        'ubicaciones' => $ubicaciones,
    ];
}

/**
 * Normaliza un número escrito en Excel sin importar el formato regional:
 * - "1.200.000,50" (puntos de miles y coma decimal) → 1200000.5
 * - "1200000,5" (coma decimal) → 1200000.5
 * - "1200000.5" (punto decimal) → 1200000.5
 * - "1.200.000" (puntos de miles) → 1200000
 * Devuelve null si no es un número válido o es negativo.
 */
function normalizarNumeroImportacion($v) {
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    $tieneComa = strpos($v, ',') !== false;
    $tienePunto = strpos($v, '.') !== false;
    $limpio = $v;
    if ($tieneComa && $tienePunto) {
        $limpio = str_replace(',', '.', str_replace('.', '', $v));
    } elseif ($tieneComa) {
        $limpio = str_replace(',', '.', $v);
    } elseif ($tienePunto) {
        $partes = explode('.', $v);
        if (count($partes) > 2 || strlen((string)end($partes)) > 2) {
            $limpio = str_replace('.', '', $v);
        }
    }
    if (!is_numeric($limpio) || (float)$limpio < 0) {
        return null;
    }
    return (float)$limpio;
}

/**
 * Valida una fila completa contra la BD y los catálogos.
 * Devuelve ['ok', 'errores' => [...], 'datos' => fila limpia listo para insertar].
 */
function validarFilaImportacion($conn, array $fila, array $ctx, array &$serialsVistos) {
    $errores = [];
    $d = $fila;

    if ($d['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio';
    } elseif (mb_strlen($d['nombre']) > 200) {
        $errores[] = 'El nombre no puede superar 200 caracteres';
    }

    if ($d['sede'] === '') {
        $errores[] = 'La sede es obligatoria';
    }
    $sede = null;
    if ($d['sede'] !== '') {
        $sede = $ctx['sedes'][mb_strtolower($d['sede'])] ?? null;
        if (!$sede) {
            $errores[] = 'La sede "' . $d['sede'] . '" no existe en el sistema';
        }
    }

    if ($d['tipo'] === '') {
        $errores[] = 'El tipo es obligatorio';
    } elseif ($sede) {
        $tipoOK = false;
        if ($d['categoria'] !== '') {
            $tiposCat = $ctx['catalogo'][$d['categoria']] ?? null;
            if ($tiposCat === null) {
                $errores[] = 'La categoría "' . $d['categoria'] . '" no existe en el catálogo';
            } elseif (!in_array($d['tipo'], $tiposCat, true)) {
                $errores[] = 'El tipo "' . $d['tipo'] . '" no pertenece a la categoría "' . $d['categoria'] . '"';
            } else {
                $tipoOK = true;
            }
        } else {
            foreach ($ctx['catalogo'] as $tipos) {
                if (in_array($d['tipo'], $tipos, true)) {
                    $tipoOK = true;
                    break;
                }
            }
            if (!$tipoOK) {
                $errores[] = 'El tipo "' . $d['tipo'] . '" no existe en el catálogo';
            }
        }
        if ($tipoOK && $d['categoria'] === '') {
            foreach ($ctx['catalogo'] as $cat => $tipos) {
                if (in_array($d['tipo'], $tipos, true)) {
                    $d['categoria'] = $cat;
                    break;
                }
            }
        }
    }

    $ubicCodigo = '';
    if ($d['ubicacion'] === '') {
        $errores[] = 'La ubicación es obligatoria';
    } elseif ($sede) {
        $data = !empty($ctx['ubicaciones']) ? ($ctx['ubicaciones'][$sede['nombre']] ?? null) : null;
        $encontrada = false;
        if ($data) {
            foreach ($data['ubicaciones'] as $u) {
                if ($u['nombre'] === $d['ubicacion']) {
                    $encontrada = true;
                    $ubicCodigo = $u['codigo'];
                    break;
                }
            }
        }
        if (!$encontrada) {
            $errores[] = 'La ubicación "' . $d['ubicacion'] . '" no pertenece a la sede "' . $sede['nombre'] . '"';
        }
    }

    $profesorId = null;
    if ($d['responsable'] !== '') {
        $prof = $ctx['profesores'][mb_strtolower($d['responsable'])] ?? null;
        if (!$prof) {
            $errores[] = 'El profesor "' . $d['responsable'] . '" no existe o está inactivo';
        } elseif (!$sede || (int)$prof['sede_id'] !== (int)$sede['id']) {
            $errores[] = 'El profesor "' . $d['responsable'] . '" no pertenece a la sede "' . ($sede['nombre'] ?? $d['sede']) . '"';
        } else {
            $profesorId = (int)$prof['id'];
        }
    }

    if ($d['estado'] !== '' && !in_array(mb_strtolower($d['estado']), ESTADOS_VALIDOS_IMPORT, true)) {
        $errores[] = 'El estado "' . $d['estado'] . '" no es válido (use: ' . implode(', ', ESTADOS_VALIDOS_IMPORT) . ')';
    }
    if ($d['estado'] === '') {
        $d['estado'] = 'bueno';
    }

    if ($d['numero_serie'] !== '') {
        $serial = $d['numero_serie'];
        if (strlen($serial) > 50) {
            $errores[] = 'El serial no puede superar 50 caracteres';
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM inventario_general WHERE numero_serie=? AND activo=1");
            $stmt->execute([$serial]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errores[] = 'El serial ' . $serial . ' ya existe en el inventario';
            } elseif (isset($serialsVistos[mb_strtolower($serial)])) {
                $errores[] = 'El serial ' . $serial . ' está duplicado dentro del archivo (fila ' . $serialsVistos[mb_strtolower($serial)] . ')';
            } else {
                $serialsVistos[mb_strtolower($serial)] = $d['_fila_excel'];
            }
        }
    }

    foreach (['vida_util', 'valor_comercial', 'valor_compra'] as $campoNum) {
        $v = $d[$campoNum];
        if (trim((string)$v) === '') {
            $d[$campoNum] = null;
            continue;
        }
        $num = normalizarNumeroImportacion($v);
        if ($num === null) {
            $errores[] = 'El valor de "' . COLUMNAS_IMPORTACION[$campoNum] . '" debe ser un número válido';
        } else {
            $d[$campoNum] = $campoNum === 'vida_util' ? (int)round($num) : $num;
        }
    }

    if (mb_strlen($d['descripcion']) > 5000) {
        $errores[] = 'La descripción no puede superar 5000 caracteres';
    }

    $d['sede_id'] = $sede ? (int)$sede['id'] : null;
    $d['sede_codigo'] = $sede ? $sede['codigo'] : '';
    $d['ubic_codigo'] = $ubicCodigo;
    $d['profesor_id'] = $profesorId;

    return ['ok' => empty($errores), 'errores' => $errores, 'datos' => $d];
}

/**
 * Valida todas las filas y devuelve ['validas' => [...], 'invalidas' => [...]].
 */
function validarFilasImportacion($conn, array $filas) {
    $ctx = contextoImportacion($conn);
    $serialsVistos = [];
    $validas = [];
    $invalidas = [];
    foreach ($filas as $fila) {
        $res = validarFilaImportacion($conn, $fila, $ctx, $serialsVistos);
        if ($res['ok']) {
            $validas[] = $res['datos'];
        } else {
            $invalidas[] = ['fila' => $fila, 'errores' => $res['errores']];
        }
    }
    return ['validas' => $validas, 'invalidas' => $invalidas];
}

/**
 * Crea los activos en una sola transacción. Genera códigos internos
 * consecutivos (INST-SEDE-UBICACIÓN-NNN), QR y eventos de historial.
 * Usa GET_LOCK para evitar duplicados en importaciones concurrentes.
 */
function importarFilasValidas($conn, array $filasValidas, $usuarioId) {
    if (!$filasValidas) {
        return ['creados' => 0, 'ids' => []];
    }
    global $institucion, $catalogosUbicaciones;

    $bloqueado = $conn->query("SELECT GET_LOCK('mic_importar_excel', 15)")->fetchColumn();
    if (!$bloqueado) {
        throw new RuntimeException('La importación está ocupada por otro proceso. Intente nuevamente.');
    }

    try {
        $conn->beginTransaction();
        $creados = 0;
        $ids = [];

        $insert = $conn->prepare(
            "INSERT INTO inventario_general
             (codigo_interno, nombre, categoria, tipo, marca, modelo, numero_serie, estado,
              ubicacion, codigo_ubicacion, id_sede, profesor_id, descripcion, vida_util,
              vr_comercial, valor_compra, fecha_ingreso, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)"
        );

        foreach ($filasValidas as $fila) {
            $sedeNombre = '';
            foreach ($catalogosUbicaciones as $nombre => $data) {
                if (($data['codigo'] ?? '') === $fila['sede_codigo']) {
                    $sedeNombre = $nombre;
                    break;
                }
            }
            $codigo_interno = '';
            if ($fila['sede_codigo'] !== '' && $fila['ubic_codigo'] !== '') {
                $codigo_interno = generarCodigoElemento(
                    $conn,
                    $institucion['codigo'] ?? '20J',
                    $sedeNombre,
                    $fila['sede_codigo'],
                    $fila['ubic_codigo']
                );
            }

            $insert->execute([
                $codigo_interno !== '' ? $codigo_interno : null,
                $fila['nombre'],
                $fila['categoria'] !== '' ? $fila['categoria'] : null,
                $fila['tipo'],
                $fila['marca'] !== '' ? $fila['marca'] : null,
                $fila['modelo'] !== '' ? $fila['modelo'] : null,
                $fila['numero_serie'] !== '' ? $fila['numero_serie'] : null,
                mb_strtolower($fila['estado']),
                $fila['ubicacion'],
                $fila['ubic_codigo'] !== '' ? $fila['ubic_codigo'] : null,
                $fila['sede_id'],
                $fila['profesor_id'],
                $fila['descripcion'] !== '' ? $fila['descripcion'] : null,
                $fila['vida_util'],
                $fila['valor_comercial'],
                $fila['valor_compra'],
            ]);
            $nuevoId = (int)$conn->lastInsertId();
            $ids[] = $nuevoId;

            if ($codigo_interno !== '') {
                $qrPath = generarQR($codigo_interno, $nuevoId);
                if ($qrPath) {
                    $conn->prepare("UPDATE inventario_general SET qr_path=? WHERE id=?")->execute([$qrPath, $nuevoId]);
                }
            }

            registrarEventoHistorial(
                $conn, $nuevoId, 'registro',
                'Elemento registrado por importación masiva',
                null,
                [
                    'nombre' => $fila['nombre'], 'codigo' => $codigo_interno !== '' ? $codigo_interno : null,
                    'tipo' => $fila['tipo'], 'categoria' => $fila['categoria'] ?: null,
                    'sede' => $fila['sede_id'] ? $sedeNombre : null, 'ubicacion' => $fila['ubicacion'],
                    'responsable' => $fila['profesor_id'] ? (string)$fila['profesor_id'] : null,
                    'estado' => mb_strtolower($fila['estado']), 'numero_serie' => $fila['numero_serie'] ?: null,
                ],
                (int)$usuarioId
            );
            $creados++;
        }

        $conn->commit();
        return ['creados' => $creados, 'ids' => $ids];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    } finally {
        if ($bloqueado) {
            $conn->query("SELECT RELEASE_LOCK('mic_importar_excel')")->fetchColumn();
        }
    }
}

/**
 * Construye el libro de la plantilla oficial de importación.
 * El código interno NO aparece: el sistema lo genera automáticamente.
 */
function construirPlantillaImportacion() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Importación');

    $col = 'A';
    foreach (COLUMNAS_IMPORTACION as $etiqueta) {
        $sheet->setCellValue($col . '1', $etiqueta);
        $sheet->getColumnDimension($col)->setWidth($etiqueta === 'Descripción' ? 40 : 22);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }

    $ejemplo = [
        'Computador de escritorio HP',
        'Equipos de Cómputo',
        'Computador de escritorio',
        'El Porvenir',
        'Aula de Informática',
        'Carlos Pérez',
        'bueno',
        'HP',
        'ProDesk 400',
        'SN-IMPORT-001',
        '5',
        '1200000',
        '1500000',
        'Computador de escritorio importado masivamente',
    ];
    $col = 'A';
    foreach ($ejemplo as $v) {
        $sheet->setCellValue($col . '2', $v);
        $col++;
    }

    $instrucciones = $spreadsheet->createSheet();
    $instrucciones->setTitle('Instrucciones');
    $instrucciones->setCellValue('A1', 'Instrucciones para importar inventario');
    $instrucciones->getStyle('A1')->getFont()->setBold(true);
    $lineas = [
        '1. Complete una fila por activo. No modifique los encabezados de la primera fila.',
        '2. El código interno y el código QR se generan automáticamente: NO los escriba.',
        '3. "Categoría" y "Tipo" deben pertenecer al catálogo del sistema. Si deja la categoría vacía, el tipo debe existir en el catálogo.',
        '4. "Sede" debe coincidir exactamente con una sede registrada (ej: Sede Principal, El Porvenir, El Progreso, Los Comodatos, La Paz).',
        '5. "Ubicación" debe pertenecer a la sede indicada (ej: Aula de Informática, Salón 03, Biblioteca).',
        '6. "Responsable" debe ser un profesor ACTIVO de la sede indicada (Nombre y Apellido).',
        '7. "Estado" válido: bueno, regular, malo, nuevo, dañado, fuera de servicio o disponible.',
        '8. "Serial" no debe repetirse dentro del archivo ni existir ya en el inventario.',
        '9. "Vida útil", "Valor comercial" y "Valor de compra" deben ser números (use punto decimal).',
        '10. Solo se importan las filas válidas; las filas con errores se rechazan y se muestran en la vista previa.',
        '11. Máximo 5 MB y 2000 filas por archivo (.xlsx o .xls).',
    ];
    foreach ($lineas as $i => $linea) {
        $instrucciones->setCellValue('A' . ($i + 3), $linea);
    }
    $instrucciones->getColumnDimension('A')->setWidth(110);

    return $spreadsheet;
}

/**
 * Descarga la plantilla oficial de importación como archivo .xlsx.
 */
function generarPlantillaImportacionDescargar() {
    $spreadsheet = construirPlantillaImportacion();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="mic_plantilla_importacion.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
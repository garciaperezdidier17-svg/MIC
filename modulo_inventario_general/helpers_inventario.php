<?php
/**
 * Helpers del inventario general (lógica reutilizable).
 * Extraído de modulo_inventario_general/index.php para permitir
 * pruebas unitarias sin ejecutar la página completa.
 * NO modifica el comportamiento original de la aplicación.
 */

if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

function obtenerCodigoUbicacion($sedeNombre, $ubicacionNombre) {
    global $catalogosUbicaciones;
    $data = $catalogosUbicaciones[$sedeNombre] ?? null;
    if (!$data) return '';
    foreach ($data['ubicaciones'] as $u) {
        if ($u['nombre'] === $ubicacionNombre) return $u['codigo'];
    }
    return '';
}

function generarCodigoElemento($conn, $instCodigo, $sedeNombre, $sedeCodigo, $ubicCodigo) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventario_general WHERE codigo_ubicacion=? AND activo=1");
    $stmt->execute([$ubicCodigo]);
    $consecutivo = (int)$stmt->fetchColumn() + 1;
    return sprintf('%s-%s-%s-%03d', $instCodigo, $sedeCodigo, $ubicCodigo, $consecutivo);
}

function profesorPerteneceSede($conn, $profesor_id, $id_sede) {
    $stmt = $conn->prepare("SELECT sede_id FROM profesores WHERE id=? AND estado='Activo'");
    $stmt->execute([(int)$profesor_id]);
    $sede = $stmt->fetchColumn();
    return $sede !== false && (int)$sede === (int)$id_sede;
}

function ubicacionPerteneceSede($sedeNombre, $ubicacion) {
    global $catalogosUbicaciones;
    if (!$ubicacion) return true;
    $data = $catalogosUbicaciones[$sedeNombre] ?? null;
    if (!$data) return true;
    foreach ($data['ubicaciones'] as $u) {
        if ($u['nombre'] === $ubicacion) return true;
    }
    return false;
}

function ubicacionValidaEnSede($sedeNombre, $ubicacion) {
    global $catalogosUbicaciones;
    if (!$ubicacion) return false;
    $data = $catalogosUbicaciones[$sedeNombre] ?? null;
    if (!$data) return true;
    foreach ($data['ubicaciones'] as $u) {
        if ($u['nombre'] === $ubicacion) return true;
    }
    return false;
}

const ORIGENES_VALIDOS = ['Compra', 'Donación', 'Transferencia', 'Otro'];
const MAX_DOC_SIZE = 5 * 1024 * 1024;

function origenValido($origen) {
    return in_array($origen, ORIGENES_VALIDOS, true);
}

function campoDocumentoDe($origen) {
    return match ($origen) {
        'Compra' => 'documento_compra',
        'Donación' => 'documento_donacion',
        'Transferencia' => 'documento_transferencia',
        'Otro' => 'documento_origen',
        default => null,
    };
}

function validarDocumentoSubido($archivo) {
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir el archivo'];
    }
    if ($archivo['size'] <= 0) {
        return ['ok' => false, 'error' => 'El archivo está vacío'];
    }
    if ($archivo['size'] > MAX_DOC_SIZE) {
        return ['ok' => false, 'error' => 'El archivo supera el tamaño máximo permitido (5 MB)'];
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
        return ['ok' => false, 'error' => 'Solo se permiten archivos PDF, JPG, JPEG o PNG'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'])) {
        return ['ok' => false, 'error' => 'El tipo de archivo no es válido'];
    }
    return ['ok' => true, 'error' => '', 'ext' => $ext];
}

function guardarDocumento($archivo, $elementoId) {
    if (empty($archivo['tmp_name'])) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/documentos';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = isset($archivo['ext']) && $archivo['ext'] !== ''
        ? $archivo['ext']
        : strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
    $nombre = 'doc_' . $elementoId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destino = "$dir/$nombre";
    if (is_uploaded_file($archivo['tmp_name'])) {
        if (move_uploaded_file($archivo['tmp_name'], $destino)) {
            return "documentos/$nombre";
        }
    } elseif (copy($archivo['tmp_name'], $destino)) {
        return "documentos/$nombre";
    }
    return null;
}

function eliminarArchivoDocumento($ruta) {
    if (!$ruta) return;
    $archivo = __DIR__ . '/../uploads/' . $ruta;
    if (is_file($archivo)) {
        @unlink($archivo);
    }
}


function urlBase() {
    global $institucion;
    if (!empty($institucion['url'])) {
        return rtrim($institucion['url'], '/') . '/';
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    $base = rtrim(dirname($dir), '/') . '/';
    return "$protocol://$host$base";
}

function generarQR($codigo, $id) {
    $dir = __DIR__ . '/../assets/qr';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = "qr_$id.png";
    $archivo = "$dir/$path";

    try {
        $builder = new Builder(
            writer: new PngWriter(),
            data: urlBase() . "ver_articulo.php?codigo=" . urlencode($codigo),
            size: 300,
            margin: 10,
        );

        $result = $builder->build();
        $result->saveToFile($archivo);

        return "qr/$path";
    } catch (Throwable $e) {
        error_log("Error generando QR: " . $e->getMessage());
        return null;
    }
}

/**
 * URL pública de la ficha de un elemento (usada por el QR).
 */
function urlFichaElemento($codigo) {
    return urlBase() . "ver_articulo.php?codigo=" . urlencode($codigo);
}

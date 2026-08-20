<?php
/**
 * Backend AJAX de los botones "+" del formulario de inventario.
 * Crea categorías, tipos y estados desde el formulario "Agregar Elemento".
 * Solo administradores; toda operación verifica CSRF y queda en auditoría.
 * No es una página independiente: solo responde JSON a las peticiones del modal.
 */
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/helpers_catalogos.php';
require_once __DIR__ . '/../config/helpers_auditoria.php';

if (!estaLogueado() || !esAdmin()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado: se requieren permisos de administrador'], JSON_UNESCAPED_UNICODE);
    exit;
}

verificarCSRF();

$accion = $_POST['accion'] ?? '';

function responderJsonCatalogo(array $datos, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'crear_categoria': {
            $nombre = trim($_POST['nombre'] ?? '');
            $id = crearCategoriaCatalogo($conn, $nombre, $_POST['descripcion'] ?? null);
            registrarAuditoria(
                $conn, 'crear_categoria', 'sistema', 'categoria', $id,
                'Categoría creada: ' . $nombre,
                null,
                ['nombre' => $nombre, 'descripcion' => trim($_POST['descripcion'] ?? '')]
            );
            responderJsonCatalogo(['ok' => true, 'tipo' => 'categoria', 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Categoría creada']);
        }

        case 'crear_tipo': {
            $nombre = trim($_POST['nombre'] ?? '');
            $categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
            $id = crearTipoCatalogo($conn, $nombre, $categoriaId, $_POST['descripcion'] ?? null);
            $catNombre = $categoriaId ? nombreDeCategoria($conn, $categoriaId) : null;
            registrarAuditoria(
                $conn, 'crear_tipo', 'sistema', 'tipo', $id,
                'Tipo creado: ' . $nombre . ($catNombre ? ' (' . $catNombre . ')' : ''),
                null,
                ['nombre' => $nombre, 'categoria_id' => $categoriaId, 'categoria' => $catNombre]
            );
            responderJsonCatalogo(['ok' => true, 'tipo' => 'tipo', 'id' => $id, 'nombre' => $nombre, 'categoria_id' => $categoriaId, 'categoria_nombre' => $catNombre, 'mensaje' => 'Tipo creado']);
        }

        case 'crear_estado': {
            $nombre = trim($_POST['nombre'] ?? '');
            $id = crearEstadoCatalogo($conn, $nombre, $_POST['descripcion'] ?? null);
            registrarAuditoria(
                $conn, 'crear_estado', 'sistema', 'estado', $id,
                'Estado creado: ' . $nombre,
                null,
                ['nombre' => $nombre, 'descripcion' => trim($_POST['descripcion'] ?? '')]
            );
            responderJsonCatalogo(['ok' => true, 'tipo' => 'estado', 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Estado creado']);
        }

        default:
            responderJsonCatalogo(['ok' => false, 'error' => 'Acción no válida: ' . htmlspecialchars($accion)], 400);
    }
} catch (Throwable $e) {
    logError("Catálogos - error en acción '$accion': " . $e->getMessage());
    responderJsonCatalogo(['ok' => false, 'error' => $e->getMessage()], 400);
}
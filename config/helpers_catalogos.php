<?php
/**
 * Catálogos administrables del sistema MIC: categorías, tipos y estados.
 *
 * Reemplaza progresivamente a config/catalogos_inventario.php:
 * - Lecturas: se consultan las tablas categorias, tipo_equipo y estados.
 *   Si la tabla de categorías está vacía se usa el archivo estático como
 *   respaldo (config/catalogos_inventario.php).
 * - Escrituras: crear/editar/activar/desactivar (nunca se elimina
 *   físicamente un registro que pueda estar referenciado).
 *
 * Los elementos de inventario_general guardan los valores por TEXTO
 * (categoria, tipo, estado), por lo que no se requirió migrar datos.
 */

function catalogoTieneDatos(PDO $conn): bool {
    return (int)$conn->query("SELECT COUNT(*) FROM categorias")->fetchColumn() > 0;
}

/**
 * Categorías de la BD (filas completas).
 */
function catalogoCategorias(PDO $conn, bool $soloActivas = true): array {
    $sql = "SELECT id, nombre, descripcion, activo, created_at, updated_at FROM categorias";
    if ($soloActivas) {
        $sql .= " WHERE activo=1";
    }
    $sql .= " ORDER BY nombre";
    return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Tipos de la BD, opcionalmente filtrados por categoría.
 */
function catalogoTipos(PDO $conn, ?int $categoriaId = null, bool $soloActivas = true): array {
    $sql = "SELECT id, nombre_tipo, descripcion, categoria_id, activo, created_at, updated_at FROM tipo_equipo";
    $where = [];
    $params = [];
    if ($soloActivas) {
        $where[] = "activo=1";
    }
    if ($categoriaId !== null) {
        $where[] = "categoria_id=?";
        $params[] = $categoriaId;
    }
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY nombre_tipo";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Estados de la BD (filas completas).
 */
function catalogoEstados(PDO $conn, bool $soloActivas = true): array {
    $sql = "SELECT id, nombre, descripcion, activo, created_at, updated_at FROM estados";
    if ($soloActivas) {
        $sql .= " WHERE activo=1";
    }
    $sql .= " ORDER BY nombre";
    return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mapa categoría → [tipos] con la misma forma que config/catalogos_inventario.php.
 * Los tipos sin categoría van a "Otros". Si la BD no tiene categorías usa el
 * archivo estático como respaldo.
 */
function catalogoMapaTiposPorCategoria(PDO $conn): array {
    if (!catalogoTieneDatos($conn)) {
        return require __DIR__ . '/catalogos_inventario.php';
    }
    $filas = $conn->query(
        "SELECT c.nombre AS categoria, t.nombre_tipo AS tipo
         FROM tipo_equipo t
         LEFT JOIN categorias c ON t.categoria_id = c.id
         WHERE t.activo=1 AND (c.activo=1 OR c.activo IS NULL)
         ORDER BY c.nombre, t.nombre_tipo"
    )->fetchAll(PDO::FETCH_ASSOC);

    $mapa = [];
    foreach ($filas as $f) {
        $cat = ($f['categoria'] !== null && $f['categoria'] !== '') ? $f['categoria'] : 'Otros';
        $mapa[$cat][] = $f['tipo'];
    }
    return $mapa;
}

function nombreDeCategoria(PDO $conn, int $id): ?string {
    $stmt = $conn->prepare("SELECT nombre FROM categorias WHERE id=?");
    $stmt->execute([$id]);
    $nombre = $stmt->fetchColumn();
    return $nombre !== false ? (string)$nombre : null;
}

/* ====================== CREAR ====================== */

function crearCategoriaCatalogo(PDO $conn, string $nombre, ?string $descripcion = null): int {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre de la categoría es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre de la categoría no puede superar 50 caracteres');
    }
    $stmt = $conn->prepare("SELECT id, activo FROM categorias WHERE nombre=?");
    $stmt->execute([$nombre]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existe) {
        if ((int)$existe['activo'] === 0) {
            $conn->prepare("UPDATE categorias SET activo=1, descripcion=? WHERE id=?")
                ->execute([trim((string)$descripcion) ?: null, (int)$existe['id']]);
            return (int)$existe['id'];
        }
        throw new RuntimeException('Ya existe una categoría con ese nombre');
    }
    $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)")
        ->execute([$nombre, trim((string)$descripcion) ?: null]);
    return (int)$conn->lastInsertId();
}

function crearTipoCatalogo(PDO $conn, string $nombre, ?int $categoriaId = null, ?string $descripcion = null): int {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre del tipo es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre del tipo no puede superar 50 caracteres');
    }
    if ($categoriaId !== null) {
        $cat = $conn->prepare("SELECT id FROM categorias WHERE id=?");
        $cat->execute([$categoriaId]);
        if (!$cat->fetchColumn()) {
            throw new RuntimeException('La categoría seleccionada no existe');
        }
    }
    $stmt = $conn->prepare("SELECT id, activo FROM tipo_equipo WHERE nombre_tipo=?");
    $stmt->execute([$nombre]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existe) {
        if ((int)$existe['activo'] === 0) {
            $conn->prepare("UPDATE tipo_equipo SET activo=1, categoria_id=?, descripcion=? WHERE id=?")
                ->execute([$categoriaId !== null ? $categoriaId : null, trim((string)$descripcion) ?: null, (int)$existe['id']]);
            return (int)$existe['id'];
        }
        throw new RuntimeException('Ya existe un tipo con ese nombre');
    }
    $conn->prepare("INSERT INTO tipo_equipo (nombre_tipo, descripcion, categoria_id) VALUES (?, ?, ?)")
        ->execute([$nombre, trim((string)$descripcion) ?: null, $categoriaId !== null ? $categoriaId : null]);
    return (int)$conn->lastInsertId();
}

function crearEstadoCatalogo(PDO $conn, string $nombre, ?string $descripcion = null): int {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre del estado es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre del estado no puede superar 50 caracteres');
    }
    $stmt = $conn->prepare("SELECT id, activo FROM estados WHERE nombre=?");
    $stmt->execute([$nombre]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existe) {
        if ((int)$existe['activo'] === 0) {
            $conn->prepare("UPDATE estados SET activo=1, descripcion=? WHERE id=?")
                ->execute([trim((string)$descripcion) ?: null, (int)$existe['id']]);
            return (int)$existe['id'];
        }
        throw new RuntimeException('Ya existe un estado con ese nombre');
    }
    $conn->prepare("INSERT INTO estados (nombre, descripcion) VALUES (?, ?)")
        ->execute([$nombre, trim((string)$descripcion) ?: null]);
    return (int)$conn->lastInsertId();
}

/* ====================== EDITAR ====================== */

function editarCategoriaCatalogo(PDO $conn, int $id, string $nombre, ?string $descripcion = null): void {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre de la categoría es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre de la categoría no puede superar 50 caracteres');
    }
    $chk = $conn->prepare("SELECT id FROM categorias WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException('La categoría no existe');
    }
    $dup = $conn->prepare("SELECT id FROM categorias WHERE nombre=? AND id<>?");
    $dup->execute([$nombre, $id]);
    if ($dup->fetchColumn()) {
        throw new RuntimeException('Ya existe otra categoría con ese nombre');
    }
    $conn->prepare("UPDATE categorias SET nombre=?, descripcion=? WHERE id=?")
        ->execute([$nombre, trim((string)$descripcion) ?: null, $id]);
}

function editarTipoCatalogo(PDO $conn, int $id, string $nombre, ?int $categoriaId = null, ?string $descripcion = null): void {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre del tipo es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre del tipo no puede superar 50 caracteres');
    }
    $chk = $conn->prepare("SELECT id FROM tipo_equipo WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException('El tipo no existe');
    }
    if ($categoriaId !== null) {
        $cat = $conn->prepare("SELECT id FROM categorias WHERE id=?");
        $cat->execute([$categoriaId]);
        if (!$cat->fetchColumn()) {
            throw new RuntimeException('La categoría seleccionada no existe');
        }
    }
    $dup = $conn->prepare("SELECT id FROM tipo_equipo WHERE nombre_tipo=? AND id<>?");
    $dup->execute([$nombre, $id]);
    if ($dup->fetchColumn()) {
        throw new RuntimeException('Ya existe otro tipo con ese nombre');
    }
    $conn->prepare("UPDATE tipo_equipo SET nombre_tipo=?, categoria_id=?, descripcion=? WHERE id=?")
        ->execute([$nombre, $categoriaId !== null ? $categoriaId : null, trim((string)$descripcion) ?: null, $id]);
}

function editarEstadoCatalogo(PDO $conn, int $id, string $nombre, ?string $descripcion = null): void {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre del estado es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new RuntimeException('El nombre del estado no puede superar 50 caracteres');
    }
    $chk = $conn->prepare("SELECT id FROM estados WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException('El estado no existe');
    }
    $dup = $conn->prepare("SELECT id FROM estados WHERE nombre=? AND id<>?");
    $dup->execute([$nombre, $id]);
    if ($dup->fetchColumn()) {
        throw new RuntimeException('Ya existe otro estado con ese nombre');
    }
    $conn->prepare("UPDATE estados SET nombre=?, descripcion=? WHERE id=?")
        ->execute([$nombre, trim((string)$descripcion) ?: null, $id]);
}

/* ====================== ACTIVAR / DESACTIVAR ====================== */

function toggleCategoriaCatalogo(PDO $conn, int $id, bool $activo): void {
    $chk = $conn->prepare("SELECT id FROM categorias WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException('La categoría no existe');
    }
    $conn->prepare("UPDATE categorias SET activo=? WHERE id=?")->execute([$activo ? 1 : 0, $id]);
}

function toggleTipoCatalogo(PDO $conn, int $id, bool $activo): void {
    $chk = $conn->prepare("SELECT id FROM tipo_equipo WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException('El tipo no existe');
    }
    $conn->prepare("UPDATE tipo_equipo SET activo=? WHERE id=?")->execute([$activo ? 1 : 0, $id]);
}

function toggleEstadoCatalogo(PDO $conn, int $id, bool $activo): void {
    $chk = $conn->prepare("SELECT id FROM estados WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException('El estado no existe');
    }
    $conn->prepare("UPDATE estados SET activo=? WHERE id=?")->execute([$activo ? 1 : 0, $id]);
}
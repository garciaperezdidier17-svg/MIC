<?php
/**
 * Alertas automáticas del inventario.
 * Todas las cantidades se calculan desde la base de datos (nunca manuales).
 * Reutilizable en: Dashboard, Centro de alertas y Ficha del elemento.
 */

function diasAlertaGarantia($conn) {
    $stmt = $conn->query("SELECT valor FROM configuracion WHERE clave='dias_alerta_garantia'");
    $v = $stmt->fetchColumn();
    return $v !== false && (int)$v > 0 ? (int)$v : 30;
}

/**
 * Construye WHERE/params para filtrar inventario_general (aliases: ig).
 * Filtros: sede, categoria, tipo, estado, responsable, desde, hasta.
 */
function filtrosInventario($filtros = []) {
    $where = 'ig.activo=1';
    $params = [];
    if (!empty($filtros['sede'])) { $where .= ' AND ig.id_sede=?'; $params[] = (int)$filtros['sede']; }
    if (!empty($filtros['categoria'])) { $where .= ' AND ig.categoria=?'; $params[] = $filtros['categoria']; }
    if (!empty($filtros['tipo'])) { $where .= ' AND ig.tipo=?'; $params[] = $filtros['tipo']; }
    if (!empty($filtros['estado'])) { $where .= ' AND ig.estado=?'; $params[] = $filtros['estado']; }
    if (!empty($filtros['responsable'])) { $where .= ' AND ig.profesor_id=?'; $params[] = (int)$filtros['responsable']; }
    if (!empty($filtros['desde'])) { $where .= ' AND DATE(ig.creado_en)>=?'; $params[] = $filtros['desde']; }
    if (!empty($filtros['hasta'])) { $where .= ' AND DATE(ig.creado_en)<=?'; $params[] = $filtros['hasta']; }
    return [$where, $params];
}

/**
 * Calcula todas las alertas del inventario con los elementos afectados.
 * Devuelve arreglo de alertas: clave, prioridad, icono, titulo, cantidad,
 * descripcion, columnas extra y elementos.
 */
function calcularAlertas($conn, $filtros = []) {
    [$where, $params] = filtrosInventario($filtros);
    $diasGarantia = diasAlertaGarantia($conn);

    $base = "SELECT ig.id, ig.codigo_interno, ig.nombre, ig.tipo, ig.estado, ig.ubicacion, ig.fecha_compra, ig.fecha_ingreso, ig.fecha_garantia, ig.vida_util, ig.creado_en,
                    s.nombre as sede_nombre, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as responsable,
                    prov.nombre as proveedor_nombre
             FROM inventario_general ig
             LEFT JOIN sedes s ON ig.id_sede=s.id
             LEFT JOIN profesores p ON ig.profesor_id=p.id
             LEFT JOIN proveedores prov ON ig.proveedor_id=prov.id";

    $alertas = [];

    $stmt = $conn->prepare("$base WHERE $where AND ig.estado='regular' ORDER BY ig.nombre");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in = implode(',', $ids);
        $ult = $conn->query("SELECT ig2.id, MAX(m.fecha_inicio) as ultimo FROM inventario_general ig2 LEFT JOIN equipos e ON e.numero_serie COLLATE utf8mb4_unicode_ci = ig2.numero_serie LEFT JOIN mantenimiento m ON m.id_equipo=e.id WHERE ig2.id IN ($in) GROUP BY ig2.id")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as &$r) {
            $r['fecha_ultimo_mantenimiento'] = !empty($ult[$r['id']]) ? $ult[$r['id']] : '—';
            $r['proximo_mantenimiento'] = '—';
        }
        $alertas[] = [
            'clave' => 'mantenimiento', 'prioridad' => 'advertencia',
            'icono' => 'fas fa-tools', 'titulo' => 'Elementos que necesitan mantenimiento',
            'cantidad' => count($rows), 'descripcion' => 'Activos en estado regular que requieren mantenimiento.',
            'columnas' => ['Fecha último mantenimiento', 'Próximo mantenimiento'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->prepare("$base WHERE $where AND ig.estado='malo' ORDER BY ig.nombre");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $alertas[] = [
            'clave' => 'danados', 'prioridad' => 'critica',
            'icono' => 'fas fa-exclamation-triangle', 'titulo' => 'Elementos dañados',
            'cantidad' => count($rows), 'descripcion' => 'Activos registrados con estado dañado.',
            'columnas' => [], 'elementos' => $rows,
        ];
    }

    $stmt = $conn->prepare("$base WHERE $where AND ig.fecha_garantia IS NOT NULL AND ig.fecha_garantia < CURDATE() ORDER BY ig.fecha_garantia");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['dias_restantes'] = -(int)((time() - strtotime($r['fecha_garantia'])) / 86400); }
    if ($rows) {
        $alertas[] = [
            'clave' => 'garantias_vencidas', 'prioridad' => 'critica',
            'icono' => 'fas fa-shield-alt', 'titulo' => 'Garantías vencidas',
            'cantidad' => count($rows), 'descripcion' => 'Garantías cuyo vencimiento ya pasó.',
            'columnas' => ['Proveedor', 'Fecha compra', 'Vencimiento', 'Días vencido'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->prepare("$base WHERE $where AND ig.fecha_garantia IS NOT NULL AND ig.fecha_garantia >= CURDATE() AND ig.fecha_garantia <= DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY ig.fecha_garantia");
    $stmt->execute(array_merge($params, [$diasGarantia]));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['dias_restantes'] = (int)((strtotime($r['fecha_garantia']) - time()) / 86400); }
    if ($rows) {
        $alertas[] = [
            'clave' => 'garantias_proximas', 'prioridad' => 'advertencia',
            'icono' => 'fas fa-hourglass-half', 'titulo' => 'Garantías próximas a vencer',
            'cantidad' => count($rows), 'descripcion' => "Garantías que vencen en los próximos $diasGarantia días.",
            'columnas' => ['Proveedor', 'Fecha compra', 'Vencimiento', 'Días restantes'],
            'elementos' => $rows,
        ];
    }

    $baseVida = "SELECT ig.id, ig.codigo_interno, ig.nombre, ig.tipo, ig.estado, ig.ubicacion, ig.vida_util, ig.fecha_ingreso, ig.fecha_compra, ig.creado_en,
                    COALESCE(ig.fecha_ingreso, ig.fecha_compra, DATE(ig.creado_en)) as fecha_base,
                    DATE_ADD(COALESCE(ig.fecha_ingreso, ig.fecha_compra, DATE(ig.creado_en)), INTERVAL ig.vida_util YEAR) as fecha_fin_vida,
                    s.nombre as sede_nombre, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as responsable, prov.nombre as proveedor_nombre
             FROM inventario_general ig
             LEFT JOIN sedes s ON ig.id_sede=s.id
             LEFT JOIN profesores p ON ig.profesor_id=p.id
             LEFT JOIN proveedores prov ON ig.proveedor_id=prov.id";

    $stmt = $conn->prepare("$baseVida WHERE $where AND ig.vida_util IS NOT NULL AND ig.vida_util > 0 AND DATE_ADD(COALESCE(ig.fecha_ingreso, ig.fecha_compra, DATE(ig.creado_en)), INTERVAL ig.vida_util YEAR) < CURDATE() ORDER BY fecha_fin_vida");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['dias_restantes'] = -(int)((time() - strtotime($r['fecha_fin_vida'])) / 86400); }
    if ($rows) {
        $alertas[] = [
            'clave' => 'vida_util_vencida', 'prioridad' => 'critica',
            'icono' => 'fas fa-hourglass-end', 'titulo' => 'Elementos con vida útil vencida',
            'cantidad' => count($rows), 'descripcion' => 'Activos que superaron su vida útil definida.',
            'columnas' => ['Vida útil', 'Fecha base', 'Fin de vida útil'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->prepare("$baseVida WHERE $where AND ig.vida_util IS NOT NULL AND ig.vida_util > 0 AND DATE_ADD(COALESCE(ig.fecha_ingreso, ig.fecha_compra, DATE(ig.creado_en)), INTERVAL ig.vida_util YEAR) >= CURDATE() AND DATE_ADD(COALESCE(ig.fecha_ingreso, ig.fecha_compra, DATE(ig.creado_en)), INTERVAL ig.vida_util YEAR) <= DATE_ADD(CURDATE(), INTERVAL 365 DAY) ORDER BY fecha_fin_vida");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['dias_restantes'] = (int)((strtotime($r['fecha_fin_vida']) - time()) / 86400); }
    if ($rows) {
        $alertas[] = [
            'clave' => 'vida_util_proxima', 'prioridad' => 'advertencia',
            'icono' => 'fas fa-hourglass-start', 'titulo' => 'Elementos próximos al fin de vida útil',
            'cantidad' => count($rows), 'descripcion' => 'Activos cuya vida útil vence en el próximo año.',
            'columnas' => ['Vida útil', 'Fin de vida útil', 'Días restantes'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->prepare("$base WHERE $where AND ig.documento_no_disponible=1 ORDER BY ig.nombre");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $alertas[] = [
            'clave' => 'sin_documento', 'prioridad' => 'advertencia',
            'icono' => 'fas fa-file-excel', 'titulo' => 'Elementos sin documento de adquisición',
            'cantidad' => count($rows), 'descripcion' => 'Elementos marcados sin documentación disponible.',
            'columnas' => [], 'elementos' => $rows,
        ];
    }

    $stmt = $conn->prepare("$base WHERE $where AND MONTH(ig.creado_en)=MONTH(CURDATE()) AND YEAR(ig.creado_en)=YEAR(CURDATE()) ORDER BY ig.creado_en DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $alertas[] = [
            'clave' => 'registrados_mes', 'prioridad' => 'informacion',
            'icono' => 'fas fa-calendar-plus', 'titulo' => 'Elementos registrados este mes',
            'cantidad' => count($rows), 'descripcion' => 'Activos ingresados al sistema durante el mes actual.',
            'columnas' => [], 'elementos' => $rows,
        ];
    }

    usort($alertas, fn($a, $b) => array_search($a['prioridad'], ['critica', 'advertencia', 'informacion']) <=> array_search($b['prioridad'], ['critica', 'advertencia', 'informacion']));
    return $alertas;
}

/**
 * Alertas puntuales de un solo elemento (para la ficha).
 * Recibe el registro completo de inventario_general (con proveedor_nombre opcional).
 */
function alertasDeElemento($conn, $item) {
    $alertas = [];
    $diasGarantia = diasAlertaGarantia($conn);
    if ($item['estado'] === 'malo') {
        $alertas[] = ['icono' => 'fas fa-exclamation-triangle', 'texto' => 'Elemento dañado', 'clase' => 'badge-danger'];
    }
    if (!empty($item['fecha_garantia'])) {
        $restan = (strtotime($item['fecha_garantia']) - time()) / 86400;
        if ($restan < 0) {
            $alertas[] = ['icono' => 'fas fa-shield-alt', 'texto' => 'Garantía vencida hace ' . abs((int)$restan) . ' días', 'clase' => 'badge-danger'];
        } elseif ($restan <= $diasGarantia) {
            $alertas[] = ['icono' => 'fas fa-hourglass-half', 'texto' => 'Garantía vence en ' . (int)$restan . ' días', 'clase' => 'badge-warning'];
        }
    }
    if (!empty($item['vida_util']) && $item['vida_util'] > 0) {
        $base = $item['fecha_ingreso'] ?: ($item['fecha_compra'] ?: date('Y-m-d', strtotime($item['creado_en'] ?? 'now')));
        $fin = strtotime("+{$item['vida_util']} years", strtotime($base));
        $restan = ($fin - time()) / 86400;
        if ($restan < 0) {
            $alertas[] = ['icono' => 'fas fa-hourglass-end', 'texto' => 'Vida útil vencida', 'clase' => 'badge-danger'];
        } elseif ($restan <= 365) {
            $alertas[] = ['icono' => 'fas fa-hourglass-start', 'texto' => 'Fin de vida útil en ' . (int)$restan . ' días', 'clase' => 'badge-warning'];
        }
    }
    if ((int)$item['documento_no_disponible'] === 1) {
        $alertas[] = ['icono' => 'fas fa-file-excel', 'texto' => 'Sin documento de adquisición', 'clase' => 'badge-warning'];
    }
    return $alertas;
}

/**
 * Alertas del módulo de préstamos (misma estructura que calcularAlertas).
 * Se integra en el Centro de Alertas y el Dashboard, sin alterar
 * calcularAlertas (que está cubierta por tests).
 */
function calcularAlertasPrestamos($conn) {
    $alertas = [];

    $base = "SELECT p.id, p.estado, p.fecha_prestamo, p.fecha_devolucion_esperada, p.fecha_devolucion_real, p.estado_devolucion,
                    s.nombre as sede_nombre,
                    CONCAT(COALESCE(pr.nombre,''),' ',COALESCE(pr.apellido,'')) as responsable,
                    COALESCE(GROUP_CONCAT(DISTINCT ig.nombre ORDER BY ig.nombre SEPARATOR ', '), '') as elementos
             FROM prestamos p
             LEFT JOIN sedes s ON p.id_sede = s.id
             LEFT JOIN profesores pr ON p.id_profesor = pr.id
             LEFT JOIN prestamo_elementos pe ON pe.id_prestamo = p.id
             LEFT JOIN inventario_general ig ON pe.id_elemento = ig.id
             GROUP BY p.id";

    $armarFilas = function ($filas) {
        $salida = [];
        foreach ($filas as $f) {
            $salida[] = [
                'codigo_interno' => '#' . $f['id'],
                'nombre' => $f['elementos'] ?: '—',
                'sede_nombre' => $f['sede_nombre'] ?? '—',
                'ubicacion' => '',
                'responsable' => $f['responsable'] ?: '—',
                'estado' => $f['estado'],
                'elementos' => $f['elementos'] ?: '—',
                'fecha_devolucion_esperada' => $f['fecha_devolucion_esperada'] ?: '',
                'dias_restantes' => $f['fecha_devolucion_esperada']
                    ? (int)ceil((strtotime($f['fecha_devolucion_esperada']) - time()) / 86400)
                    : 0,
                'prestamo_id' => (int)$f['id'],
            ];
        }
        return $salida;
    };

    $stmt = $conn->query("$base HAVING p.estado='vencido' ORDER BY p.fecha_devolucion_esperada ASC");
    $rows = $armarFilas($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($rows) {
        $alertas[] = [
            'clave' => 'prestamos_vencidos', 'prioridad' => 'critica',
            'icono' => 'fas fa-hourglass-end', 'titulo' => 'Préstamos vencidos sin devolver',
            'cantidad' => count($rows), 'descripcion' => 'Préstamos cuya fecha de devolución ya pasó y siguen sin devolverse.',
            'columnas' => ['Devolución esperada', 'Días restantes'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->query("$base HAVING p.estado IN ('activo', 'parcialmente devuelto') AND p.fecha_devolucion_esperada = CURDATE() ORDER BY p.id");
    $rows = $armarFilas($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($rows) {
        $alertas[] = [
            'clave' => 'prestamos_vence_hoy', 'prioridad' => 'advertencia',
            'icono' => 'fas fa-clock', 'titulo' => 'Préstamos que vencen hoy',
            'cantidad' => count($rows), 'descripcion' => 'Préstamos activos que deben devolverse hoy.',
            'columnas' => ['Devolución esperada', 'Días restantes'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->query("$base HAVING p.estado IN ('activo', 'parcialmente devuelto') AND p.fecha_devolucion_esperada > CURDATE() AND p.fecha_devolucion_esperada <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) ORDER BY p.fecha_devolucion_esperada ASC");
    $rows = $armarFilas($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($rows) {
        $alertas[] = [
            'clave' => 'prestamos_proximos', 'prioridad' => 'informacion',
            'icono' => 'fas fa-hourglass-start', 'titulo' => 'Préstamos próximos a vencer',
            'cantidad' => count($rows), 'descripcion' => 'Préstamos activos que vencen en los próximos 3 días.',
            'columnas' => ['Devolución esperada', 'Días restantes'],
            'elementos' => $rows,
        ];
    }

    $stmt = $conn->query("$base HAVING p.estado IN ('devuelto', 'parcialmente devuelto') AND p.estado_devolucion IN ('Dañado', 'Perdido') AND p.fecha_devolucion_real >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY p.fecha_devolucion_real DESC");
    $rows = $armarFilas($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($rows) {
        $alertas[] = [
            'clave' => 'devoluciones_con_dano', 'prioridad' => 'advertencia',
            'icono' => 'fas fa-exclamation-triangle', 'titulo' => 'Devoluciones con daño (7 días)',
            'cantidad' => count($rows), 'descripcion' => 'Devoluciones recientes en las que el elemento se registró como Dañado.',
            'columnas' => ['Devolución esperada', 'Días restantes'],
            'elementos' => $rows,
        ];
    }

    return $alertas;
}

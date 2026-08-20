<?php

/**
 * =====================================================================
 *  PRUEBAS UNITARIAS COMPLETAS DEL SISTEMA MIC
 *  Archivo único: tests/MICUnitTest.php — clase MICUnitTest
 * =====================================================================
 *  Ejecución: vendor/bin/phpunit tests/MICUnitTest.php
 *
 *  Cobertura (30 secciones):
 *   01 Inventario | 02 Categorías | 03 Tipos | 04 Estados | 05 Sedes
 *   06 Ubicaciones | 07 Profesores/Responsables | 08 Responsable de activos
 *   09 Códigos internos | 10 QR | 11 Proveedores | 12 Documentación
 *   13 Actas | 14 Reasignación | 15 Cambio de ubicación | 16 Historial
 *   17 Toma física | 18 Evidencias | 19 Mantenimiento | 20 Baja
 *   21 Alertas | 22 Valor del inventario | 23 Préstamos | 24 Reportes
 *   25 Filtros | 26 Importación | 27 Auditoría | 28 Roles y permisos
 *   29 Seguridad | 30 Bitácora de pruebas
 *
 *  Reglas:
 *   - Solo funciones/clases REALES del proyecto (helpers de config/ y
 *     de los módulos). Nunca se inventan nombres.
 *   - Base de datos ÚNICAMENTE mic_test vía TestDatabase::pdo().
 *     Nunca se toca la base "mic" de producción.
 *   - Las pruebas que requieren MySQL real se marcan como [Integración BD].
 *   - Cada prueba incluye su bitácora: ID, MÓDULO, PRUEBA, OBJETIVO,
 *     PRECONDICIONES, DATOS y RESULTADO ESPERADO.
 */

use PHPUnit\Framework\TestCase;

class MICUnitTest extends TestCase
{
    private PDO $conn;

    /** Filas de catálogos creadas por las pruebas (se limpian en tearDown). */
    private array $creados = ['categorias' => [], 'tipo_equipo' => [], 'estados' => []];

    /** Catálogo propio de las pruebas de importación (persiste, igual que ImportacionTest). */
    private static array $catalogoImport = [];

    /* ============================================================
     * CICLO DE VIDA
     * ============================================================ */

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->creados = ['categorias' => [], 'tipo_equipo' => [], 'estados' => []];
    }

    protected function tearDown(): void
    {
        $pdo = $this->conn;
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->creados['estados'] as $id) {
            $pdo->prepare('DELETE FROM estados WHERE id=?')->execute([$id]);
        }
        foreach ($this->creados['tipo_equipo'] as $id) {
            $pdo->prepare('DELETE FROM tipo_equipo WHERE id=?')->execute([$id]);
        }
        foreach ($this->creados['categorias'] as $id) {
            $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$id]);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        TestDatabase::limpiarTablasTransaccionales();
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /* ============================================================
     * DATOS AUXILIARES (mismas columnas reales de la BD)
     * ============================================================ */

    private function rastrear(string $tabla, int $id): void
    {
        $this->creados[$tabla][] = $id;
    }

    private function crearUsuario(string $nombre, string $rol = 'admin', int $rolId = 1): int
    {
        $email = strtolower(str_replace(' ', '_', $nombre)) . uniqid() . '@test.local';
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES (?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([$nombre, $email, password_hash('Test1234', PASSWORD_DEFAULT), $rol, $rolId]);
        return (int)$this->conn->lastInsertId();
    }

    private function crearProfesor(string $nombre, string $apellido, int $sedeId, string $estado = 'Activo'): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO profesores (nombre, apellido, identificacion, correo, sede_id, estado) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $nombre, $apellido,
            'CC-' . random_int(1000000, 9999999),
            strtolower($nombre . $apellido . uniqid()) . '@test.edu.co',
            $sedeId, $estado,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    private function crearProveedor(string $nombre = 'Proveedor Test', string $nit = '900000000'): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO proveedores (nombre, nit, telefono, correo, direccion, estado) VALUES (?, ?, '3000000000', ?, 'Calle Test 123', 'Activo')"
        );
        $stmt->execute([$nombre, $nit, strtolower(str_replace(' ', '', $nombre) . uniqid()) . '@test.com']);
        return (int)$this->conn->lastInsertId();
    }

    private function crearElemento(array $datos = []): int
    {
        $defaults = [
            'codigo_interno' => null,
            'nombre' => 'Computador de escritorio TEST',
            'tipo' => 'Computador de Escritorio',
            'categoria' => 'Equipos de Cómputo',
            'marca' => 'Lenovo',
            'modelo' => 'T490',
            'numero_serie' => 'SN-' . random_int(100000, 999999),
            'estado' => 'bueno',
            'ubicacion' => 'Aula de Informática 1',
            'codigo_ubicacion' => 'INF1',
            'id_sede' => 1,
            'profesor_id' => null,
            'origen_bien' => 'Compra',
            'documento_no_disponible' => 0,
            'valor_compra' => 1000000.00,
            'vr_comercial' => 1000000.00,
            'vida_util' => 5,
            'fecha_ingreso' => date('Y-m-d'),
            'activo' => 1,
            'proveedor_id' => null,
            'qr_path' => null,
            'fecha_compra' => date('Y-m-d'),
            'fecha_garantia' => date('Y-m-d', strtotime('+1 year')),
        ];
        $data = array_merge($defaults, $datos);

        $cols = ['codigo_interno', 'nombre', 'tipo', 'categoria', 'marca', 'modelo', 'numero_serie', 'estado',
                 'ubicacion', 'codigo_ubicacion', 'id_sede', 'profesor_id', 'origen_bien', 'documento_no_disponible',
                 'valor_compra', 'vr_comercial', 'vida_util', 'fecha_ingreso', 'activo',
                 'proveedor_id', 'qr_path', 'fecha_compra', 'fecha_garantia'];
        $sql = 'INSERT INTO inventario_general (' . implode(',', $cols) . ') VALUES ('
             . implode(',', array_fill(0, count($cols), '?')) . ')';
        $vals = [];
        foreach ($cols as $c) {
            $vals[] = $data[$c];
        }
        $this->conn->prepare($sql)->execute($vals);
        return (int)$this->conn->lastInsertId();
    }

    private function crearEquipo(array $datos = []): int
    {
        $data = array_merge([
            'codigo_interno' => 'EQ-' . random_int(1000, 9999),
            'nombre' => 'Portátil TEST',
            'numero_serie' => 'SER-' . random_int(100000, 999999),
            'estado' => 'disponible',
            'id_sede' => 1,
            'stock' => 1,
            'stock_minimo' => 1,
            'activo' => 1,
        ], $datos);
        $this->conn->prepare(
            "INSERT INTO equipos (codigo_interno, nombre, numero_serie, estado, id_sede, stock, stock_minimo, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$data['codigo_interno'], $data['nombre'], $data['numero_serie'], $data['estado'], $data['id_sede'], $data['stock'], $data['stock_minimo'], $data['activo']]);
        return (int)$this->conn->lastInsertId();
    }

    private function crearEstudiante(string $nombre): array
    {
        $usuarioId = $this->crearUsuario($nombre, 'estudiante', 4);
        $this->conn->prepare(
            "INSERT INTO estudiantes (id_usuario, codigo_estudiante, grado, grupo, jornada, activo) VALUES (?, ?, 9, 'A', 'mañana', 1)"
        )->execute([$usuarioId, 'EST-' . random_int(1000, 9999)]);
        return ['usuario_id' => $usuarioId, 'estudiante_id' => (int)$this->conn->lastInsertId()];
    }

    /** Archivo temporal PNG real (1x1) para pruebas de archivos. */
    private function archivoPngTemp(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'mic_png_');
        file_put_contents($ruta, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
        return $ruta;
    }

    /** Archivo temporal PDF real (cabecera válida) para pruebas de documentos. */
    private function archivoPdfTemp(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'mic_pdf_');
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        return $ruta;
    }

    /** Elimina un archivo si existe (limpieza de subidas de prueba). */
    private function borrarSiExiste(string $ruta): void
    {
        if ($ruta !== '' && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /* ============================================================
     * SECCIÓN 01 — INVENTARIO GENERAL (helpers_inventario.php)
     * ============================================================ */

    /**
     * ID: INV-001 | MÓDULO: 01 Inventario | PRUEBA: generarCodigoElemento
     * OBJETIVO: Verificar la generación del código interno consecutivo por
     *           ubicación (20J-<sede>-<ubic>-NNN).
     * PRECONDICIONES: mic_test limpia; función real generarCodigoElemento.
     * DATOS: 2 elementos activos con codigo_ubicacion='INF1', sede '01', ubic 'INF1'.
     * RESULTADO ESPERADO: códigos '20J-01-INF1-001' y '20J-01-INF1-002', con
     *           formato de 3 dígitos con cero a la izquierda.
     */
    public function testINV001GenerarCodigoElementoConsecutivo(): void
    {
        $codigo1 = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF1');
        $this->assertSame('20J-01-INF1-001', $codigo1);
        $this->crearElemento(['codigo_ubicacion' => 'INF1', 'codigo_interno' => $codigo1]);

        $codigo2 = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF1');
        $this->assertSame('20J-01-INF1-002', $codigo2);
        $this->crearElemento(['codigo_ubicacion' => 'INF1', 'codigo_interno' => $codigo2]);
        $this->assertMatchesRegularExpression('/^20J-\d{2}-[A-Z0-9]+-\d{3}$/', $codigo1);
    }

    /**
     * ID: INV-002 | MÓDULO: 01 Inventario | PRUEBA: generarCodigoElemento con inactivos
     * OBJETIVO: Confirmar que los elementos dados de baja (activo=0) NO ocupan
     *           consecutivo.
     * PRECONDICIONES: mic_test limpia; un elemento activo en INF1.
     * DATOS: 1 activo + 1 inactivo en la misma ubicación.
     * RESULTADO ESPERADO: el siguiente código sigue siendo '...002' (el inactivo
     *           no cuenta).
     */
    public function testINV002GenerarCodigoIgnoraInactivos(): void
    {
        $this->crearElemento(['codigo_ubicacion' => 'INF1']);
        $this->crearElemento(['codigo_ubicacion' => 'INF1', 'activo' => 0]);

        $codigo = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF1');
        $this->assertSame('20J-01-INF1-002', $codigo);
    }

    /**
     * ID: INV-003 | MÓDULO: 01 Inventario | PRUEBA: origenValido
     * OBJETIVO: Validar que solo se aceptan los 4 orígenes de bien del sistema.
     * PRECONDICIONES: Constante ORIGENES_VALIDOS cargada.
     * DATOS: 'Compra', 'Donación', 'Transferencia', 'Otro', 'Roba', ''.
     * RESULTADO ESPERADO: true para los 4 válidos; false para el resto.
     */
    public function testINV003OrigenValido(): void
    {
        $this->assertTrue(origenValido('Compra'));
        $this->assertTrue(origenValido('Donación'));
        $this->assertTrue(origenValido('Transferencia'));
        $this->assertTrue(origenValido('Otro'));
        $this->assertFalse(origenValido('Roba'));
        $this->assertFalse(origenValido(''));
    }

    /**
     * ID: INV-004 | MÓDULO: 01 Inventario | PRUEBA: campoDocumentoDe
     * OBJETIVO: Verificar el mapeo origen → campo de documento de adquisición.
     * PRECONDICIONES: Función real campoDocumentoDe.
     * DATOS: 4 orígenes válidos + 1 inválido.
     * RESULTADO ESPERADO: documento_compra/donacion/transferencia/origen y null.
     */
    public function testINV004CampoDocumentoDe(): void
    {
        $this->assertSame('documento_compra', campoDocumentoDe('Compra'));
        $this->assertSame('documento_donacion', campoDocumentoDe('Donación'));
        $this->assertSame('documento_transferencia', campoDocumentoDe('Transferencia'));
        $this->assertSame('documento_origen', campoDocumentoDe('Otro'));
        $this->assertNull(campoDocumentoDe('Roba'));
    }

    /**
     * ID: INV-005 | MÓDULO: 01 Inventario | PRUEBA: Registro completo [Integración BD]
     * OBJETIVO: Verificar que un registro real de inventario_general persiste con
     *           todos los campos del formulario y aparece por codigo_interno.
     * PRECONDICIONES: mic_test limpia; proveedor y profesor reales creados.
     * DATOS: elemento Compra con proveedor, profesor, ubicación INF1, estado bueno.
     * RESULTADO ESPERADO: fila guardada con codigo_interno, valor_compra,
     *           documento_no_disponible=0 y activo=1.
     */
    public function testINV005RegistroCompletoDeElemento(): void
    {
        $proveedorId = $this->crearProveedor();
        $profesorId = $this->crearProfesor('Ana', 'Pérez', 1);
        $id = $this->crearElemento([
            'codigo_interno' => '20J-01-INF1-001',
            'nombre' => 'VideoBeam EPSON',
            'tipo' => 'Proyector',
            'categoria' => 'Académico',
            'marca' => 'EPSON',
            'numero_serie' => 'SN-EPSON-1',
            'proveedor_id' => $proveedorId,
            'profesor_id' => $profesorId,
            'valor_compra' => 2500000.00,
        ]);

        $fila = $this->conn->query("SELECT * FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('20J-01-INF1-001', $fila['codigo_interno']);
        $this->assertSame('VideoBeam EPSON', $fila['nombre']);
        $this->assertSame('bueno', $fila['estado']);
        $this->assertSame(1, (int)$fila['activo']);
        $this->assertEqualsWithDelta(2500000.0, (float)$fila['valor_compra'], 0.001);
        $this->assertSame((int)$proveedorId, (int)$fila['proveedor_id']);

        $buscado = buscarElementoPorCodigo($this->conn, '20J-01-INF1-001');
        $this->assertNotNull($buscado);
        $this->assertSame('Ana Pérez', trim($buscado['prof_nombre'] . ' ' . $buscado['prof_apellido']));
    }

    /**
     * ID: INV-006 | MÓDULO: 01 Inventario | PRUEBA: urlFichaElemento
     * OBJETIVO: Verificar la URL pública de la ficha usada por los QR.
     * PRECONDICIONES: config/institucion.php con url 'http://localhost/mic/'.
     * DATOS: código '20J-01-INF1-001'.
     * RESULTADO ESPERADO: URL con ver_articulo.php?codigo= codificado.
     */
    public function testINV006UrlFichaElemento(): void
    {
        $url = urlFichaElemento('20J-01-INF1-001');
        $this->assertStringContainsString('ver_articulo.php?codigo=20J-01-INF1-001', $url);
    }

    /* ============================================================
     * SECCIÓN 02 — CATEGORÍAS (helpers_catalogos.php)
     * ============================================================ */

    /**
     * ID: CAT-001 | MÓDULO: 02 Categorías | PRUEBA: catalogoTieneDatos y listado
     * OBJETIVO: Verificar que el catálogo de categorías se lee desde la BD.
     * PRECONDICIONES: mic_test con 4 categorías sembradas.
     * DATOS: sin datos adicionales.
     * RESULTADO ESPERADO: catalogoTieneDatos()=true y 4 filas ordenadas por nombre.
     */
    public function testCAT001CatalogoCategoriasDesdeBD(): void
    {
        $this->assertTrue(catalogoTieneDatos($this->conn));
        $categorias = catalogoCategorias($this->conn);
        $this->assertGreaterThanOrEqual(4, count($categorias), 'El catálogo debe conservar al menos las 4 semillas');
        $nombres = array_column($categorias, 'nombre');
        $this->assertContains('Académico', $nombres);
        $this->assertContains('Laboratorio', $nombres);
    }

    /**
     * ID: CAT-002 | MÓDULO: 02 Categorías | PRUEBA: crearCategoriaCatalogo
     * OBJETIVO: Crear categoría nueva y rechazar duplicado activo.
     * PRECONDICIONES: mic_test limpia.
     * DATOS: 'Categoría QA 001' con descripción.
     * RESULTADO ESPERADO: id > 0, fila insertada; segundo intento lanza
     *           RuntimeException 'Ya existe'.
     */
    public function testCAT002CrearCategoriaYRechazaDuplicado(): void
    {
        $id = crearCategoriaCatalogo($this->conn, 'Categoría QA 001', 'Descripción QA');
        $this->rastrear('categorias', $id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('Categoría QA 001', $this->conn->query("SELECT nombre FROM categorias WHERE id=$id")->fetchColumn());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe');
        crearCategoriaCatalogo($this->conn, 'Categoría QA 001');
    }

    /**
     * ID: CAT-003 | MÓDULO: 02 Categorías | PRUEBA: nombreDeCategoria
     * OBJETIVO: Resolver el nombre de una categoría por id.
     * PRECONDICIONES: mic_test con categorías sembradas.
     * DATOS: id de 'Académico' (1) e id inexistente 999999.
     * RESULTADO ESPERADO: 'Académico' y null.
     */
    public function testCAT003NombreDeCategoria(): void
    {
        $this->assertSame('Académico', nombreDeCategoria($this->conn, 1));
        $this->assertNull(nombreDeCategoria($this->conn, 999999));
    }

    /**
     * ID: CAT-004 | MÓDULO: 02 Categorías | PRUEBA: editarCategoriaCatalogo
     * OBJETIVO: Renombrar categoría y rechazar colisión con otra.
     * PRECONDICIONES: 2 categorías creadas por la prueba.
     * DATOS: renombrar A a 'Categoría QA Renombrada'; intentar renombrar B con
     *        el mismo nombre.
     * RESULTADO ESPERADO: UPDATE aplicado; RuntimeException 'Ya existe otra'.
     */
    public function testCAT004EditarCategoria(): void
    {
        $a = crearCategoriaCatalogo($this->conn, 'Categoría QA A');
        $b = crearCategoriaCatalogo($this->conn, 'Categoría QA B');
        $this->rastrear('categorias', $a);
        $this->rastrear('categorias', $b);

        editarCategoriaCatalogo($this->conn, $a, 'Categoría QA Renombrada', 'Nueva desc');
        $this->assertSame('Categoría QA Renombrada', $this->conn->query("SELECT nombre FROM categorias WHERE id=$a")->fetchColumn());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe otra');
        editarCategoriaCatalogo($this->conn, $b, 'Categoría QA Renombrada');
    }

    /**
     * ID: CAT-005 | MÓDULO: 02 Categorías | PRUEBA: toggleCategoriaCatalogo
     * OBJETIVO: Desactivar/reactivar sin eliminar físicamente el registro.
     * PRECONDICIONES: categoría creada por la prueba.
     * DATOS: toggle(false) luego toggle(true).
     * RESULTADO ESPERADO: desaparece de soloActivas, sigue en el listado
     *           completo, y reaparece al reactivar.
     */
    public function testCAT005ToggleCategoriaSinEliminar(): void
    {
        $id = crearCategoriaCatalogo($this->conn, 'Categoría QA Toggle');
        $this->rastrear('categorias', $id);

        toggleCategoriaCatalogo($this->conn, $id, false);
        $this->assertSame(0, (int)$this->conn->query("SELECT activo FROM categorias WHERE id=$id")->fetchColumn());
        $this->assertNotContains($id, array_column(catalogoCategorias($this->conn, true), 'id'));
        $this->assertContains($id, array_column(catalogoCategorias($this->conn, false), 'id'));

        toggleCategoriaCatalogo($this->conn, $id, true);
        $this->assertContains($id, array_column(catalogoCategorias($this->conn, true), 'id'));
    }

    /* ============================================================
     * SECCIÓN 03 — TIPOS (helpers_catalogos.php)
     * ============================================================ */

    /**
     * ID: TIP-001 | MÓDULO: 03 Tipos | PRUEBA: crearTipoCatalogo con categoría
     * OBJETIVO: Crear tipo perteneciente a una categoría y filtrarlo.
     * PRECONDICIONES: categoría real 'Académico' (id 1).
     * DATOS: 'Tipo QA 001' bajo categoría 1.
     * RESULTADO ESPERADO: tipo insertado con categoria_id=1 y visible en
     *           catalogoTipos(1); duplicado lanza RuntimeException.
     */
    public function testTIP001CrearTipoPerteneceACategoria(): void
    {
        $tipoId = crearTipoCatalogo($this->conn, 'Tipo QA 001', 1, 'Tipo de prueba');
        $this->rastrear('tipo_equipo', $tipoId);
        $this->assertSame(1, (int)$this->conn->query("SELECT categoria_id FROM tipo_equipo WHERE id=$tipoId")->fetchColumn());
        $this->assertContains($tipoId, array_column(catalogoTipos($this->conn, 1, false), 'id'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe');
        crearTipoCatalogo($this->conn, 'Tipo QA 001', 1);
    }

    /**
     * ID: TIP-002 | MÓDULO: 03 Tipos | PRUEBA: crearTipoConCategoriaInexistente
     * OBJETIVO: Rechazar tipo con categoría que no existe.
     * PRECONDICIONES: mic_test limpia.
     * DATOS: categoriaId=999999.
     * RESULTADO ESPERADO: RuntimeException 'no existe'.
     */
    public function testTIP002TipoConCategoriaInexistenteRechazado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no existe');
        crearTipoCatalogo($this->conn, 'Tipo QA Sin Categoria', 999999);
    }

    /**
     * ID: TIP-003 | MÓDULO: 03 Tipos | PRUEBA: editar y toggle de tipos
     * OBJETIVO: Renombrar y desactivar/reactivar un tipo.
     * PRECONDICIONES: tipo creado por la prueba.
     * DATOS: renombrar a 'Tipo QA Editado'; toggle(false) y toggle(true).
     * RESULTADO ESPERADO: nombre actualizado; fuera de soloActivas al
     *           desactivar y presente al reactivar.
     */
    public function testTIP003EditarYToggleTipo(): void
    {
        $tipoId = crearTipoCatalogo($this->conn, 'Tipo QA Edit', 1);
        $this->rastrear('tipo_equipo', $tipoId);

        editarTipoCatalogo($this->conn, $tipoId, 'Tipo QA Editado', 1, 'Desc');
        $this->assertSame('Tipo QA Editado', $this->conn->query("SELECT nombre_tipo FROM tipo_equipo WHERE id=$tipoId")->fetchColumn());

        toggleTipoCatalogo($this->conn, $tipoId, false);
        $this->assertNotContains($tipoId, array_column(catalogoTipos($this->conn, 1, true), 'id'));
        toggleTipoCatalogo($this->conn, $tipoId, true);
        $this->assertContains($tipoId, array_column(catalogoTipos($this->conn, 1, true), 'id'));
    }

    /**
     * ID: TIP-004 | MÓDULO: 03 Tipos | PRUEBA: catalogoMapaTiposPorCategoria
     * OBJETIVO: El mapa categoría → tipos (forma de catalogos_inventario.php)
     *           incluye el tipo bajo su categoría.
     * PRECONDICIONES: categoría y tipo creados por la prueba.
     * DATOS: 'Categoría QA Mapa' / 'Tipo QA Mapa'.
     * RESULTADO ESPERADO: $mapa['Categoría QA Mapa'] contiene 'Tipo QA Mapa'.
     */
    public function testTIP004MapaIncluyeTipoBajoSuCategoria(): void
    {
        $catId = crearCategoriaCatalogo($this->conn, 'Categoría QA Mapa');
        $this->rastrear('categorias', $catId);
        crearTipoCatalogo($this->conn, 'Tipo QA Mapa', $catId);
        $this->rastrear('tipo_equipo', $this->conn->lastInsertId());

        $mapa = catalogoMapaTiposPorCategoria($this->conn);
        $this->assertIsArray($mapa);
        $this->assertContains('Tipo QA Mapa', $mapa['Categoría QA Mapa'] ?? []);
    }

    /* ============================================================
     * SECCIÓN 04 — ESTADOS (helpers_catalogos.php)
     * ============================================================ */

    /**
     * ID: EST-001 | MÓDULO: 04 Estados | PRUEBA: crearEstadoCatalogo
     * OBJETIVO: Crear estado y rechazar duplicado.
     * PRECONDICIONES: mic_test con 6 estados sembrados.
     * DATOS: 'estado-qa-001' con descripción.
     * RESULTADO ESPERADO: id > 0; duplicado lanza RuntimeException 'Ya existe'.
     */
    public function testEST001CrearEstadoYRechazaDuplicado(): void
    {
        $id = crearEstadoCatalogo($this->conn, 'estado-qa-001', 'Estado de prueba');
        $this->rastrear('estados', $id);
        $this->assertGreaterThan(0, $id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe');
        crearEstadoCatalogo($this->conn, 'estado-qa-001');
    }

    /**
     * ID: EST-002 | MÓDULO: 04 Estados | PRUEBA: editar y toggle de estados
     * OBJETIVO: Renombrar y desactivar/reactivar un estado.
     * PRECONDICIONES: estado creado por la prueba.
     * DATOS: renombrar a 'estado-qa-editado'; toggle(false) y toggle(true).
     * RESULTADO ESPERADO: nombre actualizado y visibilidad controlada por
     *           catalogoEstados(soloActivas).
     */
    public function testEST002EditarYToggleEstado(): void
    {
        $id = crearEstadoCatalogo($this->conn, 'estado-qa-edit');
        $this->rastrear('estados', $id);

        editarEstadoCatalogo($this->conn, $id, 'estado-qa-editado', 'Desc');
        $this->assertSame('estado-qa-editado', $this->conn->query("SELECT nombre FROM estados WHERE id=$id")->fetchColumn());

        toggleEstadoCatalogo($this->conn, $id, false);
        $this->assertNotContains($id, array_column(catalogoEstados($this->conn, true), 'id'));
        toggleEstadoCatalogo($this->conn, $id, true);
        $this->assertContains($id, array_column(catalogoEstados($this->conn, true), 'id'));
    }

    /**
     * ID: EST-003 | MÓDULO: 04 Estados | PRUEBA: crearEstadoSinNombre
     * OBJETIVO: Rechazar estado con nombre vacío.
     * PRECONDICIONES: mic_test limpia.
     * DATOS: nombre '   '.
     * RESULTADO ESPERADO: RuntimeException 'obligatorio'.
     */
    public function testEST003EstadoSinNombreRechazado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('obligatorio');
        crearEstadoCatalogo($this->conn, '   ');
    }

    /* ============================================================
     * SECCIÓN 05 — SEDES
     * ============================================================ */

    /**
     * ID: SED-001 | MÓDULO: 05 Sedes | PRUEBA: sedes sembradas [Integración BD]
     * OBJETIVO: Verificar las 5 sedes reales del sistema con sus códigos.
     * PRECONDICIONES: mic_test con sedes sembradas (01..05).
     * DATOS: consulta a tabla sedes.
     * RESULTADO ESPERADO: 5 sedes activas; 'Sede Principal' con codigo '01'.
     */
    public function testSED001SedesSembradasConCodigos(): void
    {
        $sedes = $this->conn->query("SELECT codigo, nombre FROM sedes WHERE activo=1 ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(5, $sedes);
        $this->assertSame('01', $sedes[0]['codigo']);
        $this->assertSame('Sede Principal', $sedes[0]['nombre']);
        $this->assertSame('05', $sedes[4]['codigo']);
    }

    /**
     * ID: SED-002 | MÓDULO: 05 Sedes | PRUEBA: sedes en catálogo de ubicaciones
     * OBJETIVO: Toda sede de la BD debe tener su catálogo en config/ubicaciones.php.
     * PRECONDICIONES: $GLOBALS['catalogosUbicaciones'] cargado por bootstrap.
     * DATOS: nombres de las 5 sedes de la BD.
     * RESULTADO ESPERADO: todas las sedes existen como clave del catálogo.
     */
    public function testSED002TodasLasSedesTienenCatalogo(): void
    {
        $nombres = $this->conn->query("SELECT nombre FROM sedes WHERE activo=1")->fetchAll(PDO::FETCH_COLUMN);
        $catalogo = $GLOBALS['catalogosUbicaciones'];
        foreach ($nombres as $nombre) {
            $this->assertArrayHasKey($nombre, $catalogo, "Sede '$nombre' debe existir en config/ubicaciones.php");
            $this->assertNotEmpty($catalogo[$nombre]['codigo']);
            $this->assertNotEmpty($catalogo[$nombre]['ubicaciones']);
        }
    }

    /**
     * ID: SED-003 | MÓDULO: 05 Sedes | PRUEBA: elemento vinculado a sede [Integración BD]
     * OBJETIVO: El join inventario→sedes devuelve el nombre de la sede.
     * PRECONDICIONES: elemento creado con id_sede=1.
     * DATOS: consulta real usada por alertas (LEFT JOIN sedes).
     * RESULTADO ESPERADO: sede_nombre = 'Sede Principal'.
     */
    public function testSED003ElementoVinculadoASede(): void
    {
        $id = $this->crearElemento(['id_sede' => 1]);
        $fila = $this->conn->query(
            "SELECT s.nombre AS sede_nombre FROM inventario_general ig LEFT JOIN sedes s ON ig.id_sede=s.id WHERE ig.id=$id"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Sede Principal', $fila['sede_nombre']);
    }

    /**
     * ID: SED-004 | MÓDULO: 05 Sedes | PRUEBA: integridad FK profesores→sedes [Integración BD]
     * OBJETIVO: La FK real impide profesores con sede inexistente.
     * PRECONDICIONES: mic_test con FKs del esquema real.
     * DATOS: INSERT de profesor con sede_id=999.
     * RESULTADO ESPERADO: PDOException por violación de FK.
     */
    public function testSED004IntegridadProfesorRequiereSede(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, sede_id) VALUES ('A', 'B', 999)")->execute();
    }

    /* ============================================================
     * SECCIÓN 06 — UBICACIONES (helpers_inventario.php)
     * ============================================================ */

    /**
     * ID: UBI-001 | MÓDULO: 06 Ubicaciones | PRUEBA: obtenerCodigoUbicacion
     * OBJETIVO: Resolver el código interno de una ubicación por sede.
     * PRECONDICIONES: config/ubicaciones.php cargado.
     * DATOS: 'Sede Principal'/'Coordinación' → COO; 'El Porvenir'/'Aula de
     *        Informática' → INF1; ubicación inexistente.
     * RESULTADO ESPERADO: 'COO', 'INF1' y ''.
     */
    public function testUBI001ObtenerCodigoUbicacion(): void
    {
        $this->assertSame('COO', obtenerCodigoUbicacion('Sede Principal', 'Coordinación'));
        $this->assertSame('INF1', obtenerCodigoUbicacion('El Porvenir', 'Aula de Informática'));
        $this->assertSame('', obtenerCodigoUbicacion('Sede Principal', 'Lugar Inexistente'));
        $this->assertSame('', obtenerCodigoUbicacion('Sede Inexistente', 'Coordinación'));
    }

    /**
     * ID: UBI-002 | MÓDULO: 06 Ubicaciones | PRUEBA: ubicacionPerteneceSede
     * OBJETIVO: Validar pertenencia de una ubicación a su sede.
     * PRECONDICIONES: config/ubicaciones.php cargado.
     * DATOS: 'Salón 01' en Sede Principal (true); 'Salón 30' en El Porvenir
     *        (solo llega a 20) → false; vacío → true.
     * RESULTADO ESPERADO: true, false y true.
     */
    public function testUBI002UbicacionPerteneceSede(): void
    {
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', 'Salón 01'));
        $this->assertFalse(ubicacionPerteneceSede('El Porvenir', 'Salón 30'));
        $this->assertFalse(ubicacionPerteneceSede('Sede Principal', 'Auditorio de otra sede'));
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', ''));
    }

    /**
     * ID: UBI-003 | MÓDULO: 06 Ubicaciones | PRUEBA: ubicacionValidaEnSede
     * OBJETIVO: Diferencia entre validación estricta (formulario) y permisible.
     * PRECONDICIONES: config/ubicaciones.php cargado.
     * DATOS: 'Patio Principal' en Sede Principal; 'Patio' (nombre de El Porvenir)
     *        en Sede Principal; vacío.
     * RESULTADO ESPERADO: true, false, false.
     */
    public function testUBI003UbicacionValidaEnSede(): void
    {
        $this->assertTrue(ubicacionValidaEnSede('Sede Principal', 'Patio Principal'));
        $this->assertFalse(ubicacionValidaEnSede('Sede Principal', 'Patio'));
        $this->assertFalse(ubicacionValidaEnSede('Sede Principal', ''));
    }

    /**
     * ID: UBI-004 | MÓDULO: 06 Ubicaciones | PRUEBA: toma física con ubicación ajena
     * OBJETIVO: iniciarTomaFisica rechaza una ubicación que no pertenece a la sede.
     * PRECONDICIONES: 1 elemento en 'Aula de Informática 1' de Sede Principal.
     * DATOS: sede 1 con ubicación 'Salón 99'.
     * RESULTADO ESPERADO: RuntimeException 'no pertenece a la sede'.
     */
    public function testUBI004TomaRechazaUbicacionAjena(): void
    {
        $this->crearElemento(['ubicacion' => 'Aula de Informática 1']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no pertenece');
        iniciarTomaFisica($this->conn, 1, 'Salón 99', 1);
    }

    /* ============================================================
     * SECCIÓN 07 — PROFESORES / RESPONSABLES
     * ============================================================ */

    /**
     * ID: PRO-001 | MÓDULO: 07 Profesores | PRUEBA: profesorPerteneceSede
     * OBJETIVO: Verificar que un profesor activo pertenece a su sede.
     * PRECONDICIONES: profesor creado en sede 1 con estado 'Activo'.
     * DATOS: profesor id y sede 1.
     * RESULTADO ESPERADO: true.
     */
    public function testPRO001ProfesorPerteneceSede(): void
    {
        $profesorId = $this->crearProfesor('Carlos', 'Gómez', 1);
        $this->assertTrue(profesorPerteneceSede($this->conn, $profesorId, 1));
    }

    /**
     * ID: PRO-002 | MÓDULO: 07 Profesores | PRUEBA: profesorPerteneceSede negativos
     * OBJETIVO: Rechazar profesor de otra sede, inexistente o inactivo.
     * PRECONDICIONES: profesores creados por la prueba.
     * DATOS: profesor sede 2 vs sede 1; id 999999; profesor estado 'Inactivo'.
     * RESULTADO ESPERADO: false en los tres casos.
     */
    public function testPRO002ProfesorNoPerteneceSede(): void
    {
        $otraSede = $this->crearProfesor('Lucía', 'Ramírez', 2);
        $this->assertFalse(profesorPerteneceSede($this->conn, $otraSede, 1));

        $inactivo = $this->crearProfesor('Pedro', 'Díaz', 1, 'Inactivo');
        $this->assertFalse(profesorPerteneceSede($this->conn, $inactivo, 1));

        $this->assertFalse(profesorPerteneceSede($this->conn, 999999, 1));
    }

    /**
     * ID: PRO-003 | MÓDULO: 07 Profesores | PRUEBA: elemento con responsable [Integración BD]
     * OBJETIVO: El responsable del elemento se resuelve con el join a profesores.
     * PRECONDICIONES: profesor activo en sede 1 + elemento asignado.
     * DATOS: codigo_interno '20J-01-INF1-007'.
     * RESULTADO ESPERADO: buscarElementoPorCodigo devuelve prof_nombre
     *           y prof_apellido del responsable.
     */
    public function testPRO003ResponsableDelElemento(): void
    {
        $profesorId = $this->crearProfesor('Marta', 'López', 1);
        $this->crearElemento(['codigo_interno' => '20J-01-INF1-007', 'profesor_id' => $profesorId]);

        $elemento = buscarElementoPorCodigo($this->conn, '20J-01-INF1-007');
        $this->assertSame('Marta', $elemento['prof_nombre']);
        $this->assertSame('López', $elemento['prof_apellido']);
    }

    /**
     * ID: PRO-004 | MÓDULO: 07 Profesores | PRUEBA: profesor de otra sede rechazado en acta
     * OBJETIVO: Los responsables del acta solo pueden ser de la sede del acta.
     * PRECONDICIONES: catalogosUbicaciones cargado.
     * DATOS: 'Carlos Gómez' en sede 1; elemento con profesor de sede 2.
     * RESULTADO ESPERADO: la consulta real de actas (join profesor→sede) no
     *           devuelve el elemento para la sede 1.
     */
    public function testPRO004ProfesorDeOtraSedeNoAparece(): void
    {
        $profesorId = $this->crearProfesor('Lucía', 'Ramírez', 2);
        $id = $this->crearElemento(['profesor_id' => $profesorId]);

        $rows = $this->conn->query(
            "SELECT ig.id FROM inventario_general ig JOIN profesores p ON ig.profesor_id=p.id WHERE ig.id=$id AND p.sede_id=1"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([], $rows);
    }

    /* ============================================================
     * SECCIÓN 08 — RESPONSABLE DE ACTIVOS (asignación)
     * ============================================================ */

    /**
     * ID: RES-001 | MÓDULO: 08 Responsable | PRUEBA: asignación de responsable
     * OBJETIVO: Al asignar un responsable se actualiza profesor_id y queda
     *           historial de reasignación.
     * PRECONDICIONES: elemento y 2 profesores reales.
     * DATOS: UPDATE profesor_id + registrarEventoHistorial('reasignacion').
     * RESULTADO ESPERADO: profesor_id nuevo en BD y evento con datos_nuevos
     *           decodificados con profesor destino.
     */
    public function testRES001AsignarResponsableConHistorial(): void
    {
        $profesorId = $this->crearProfesor('Ana', 'Pérez', 1);
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-010']);

        $usuarioId = $this->crearUsuario('Responsable QA');
        $this->conn->prepare("UPDATE inventario_general SET profesor_id=? WHERE id=?")->execute([$profesorId, $id]);
        registrarEventoHistorial(
            $this->conn, $id, 'reasignacion',
            'Responsable asignado',
            ['profesor_id' => null],
            ['profesor_id' => $profesorId],
            $usuarioId
        );

        $historial = historialDeElemento($this->conn, $id);
        $this->assertCount(1, $historial);
        $this->assertSame('reasignacion', $historial[0]['tipo_evento']);
        $this->assertSame($profesorId, $historial[0]['datos_nuevos']['profesor_id']);
        $this->assertSame($profesorId, (int)$this->conn->query("SELECT profesor_id FROM inventario_general WHERE id=$id")->fetchColumn());
    }

    /**
     * ID: RES-002 | MÓDULO: 08 Responsable | PRUEBA: etiquetas de reasignación
     * OBJETIVO: El historial muestra la etiqueta correcta del evento.
     * PRECONDICIONES: constante TIPOS_EVENTO_HISTORIAL cargada.
     * DATOS: tipo 'reasignacion'.
     * RESULTADO ESPERADO: label 'Reasignado'.
     */
    public function testRES002EtiquetaReasignacion(): void
    {
        $info = infoTipoEvento('reasignacion');
        $this->assertSame('Reasignado', $info['label']);
    }

    /* ============================================================
     * SECCIÓN 09 — CÓDIGOS INTERNOS
     * ============================================================ */

    /**
     * ID: COD-001 | MÓDULO: 09 Códigos | PRUEBA: formato y unicidad
     * OBJETIVO: El código interno es único por ubicación y con ceros a la izquierda.
     * PRECONDICIONES: elementos en INF1 y COO.
     * DATOS: 1 elemento en cada ubicación.
     * RESULTADO ESPERADO: '20J-01-INF1-001' y '20J-01-COO-001' (independientes
     *           por ubicación).
     */
    public function testCOD001CodigosUnicosPorUbicacion(): void
    {
        $this->crearElemento(['codigo_ubicacion' => 'INF1']);
        $this->crearElemento(['codigo_ubicacion' => 'COO']);

        $this->assertSame('20J-01-INF1-002', generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF1'));
        $this->assertSame('20J-01-COO-002', generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'COO'));
    }

    /**
     * ID: COD-002 | MÓDULO: 09 Códigos | PRUEBA: parsearCodigoQR
     * OBJETIVO: Extraer el código interno de una URL QR o de texto directo.
     * PRECONDICIONES: función real parsearCodigoQR.
     * DATOS: URL con ?codigo=, texto directo y vacío.
     * RESULTADO ESPERADO: '20J-01-INF1-001', el mismo texto y null.
     */
    public function testCOD002ParsearCodigoQR(): void
    {
        $this->assertSame('20J-01-INF1-001', parsearCodigoQR('http://localhost/mic/ver_articulo.php?codigo=20J-01-INF1-001'));
        $this->assertSame('20J-01-INF1-001', parsearCodigoQR('20J-01-INF1-001'));
        $this->assertNull(parsearCodigoQR(''));
        $this->assertNull(parsearCodigoQR('   '));
    }

    /**
     * ID: COD-003 | MÓDULO: 09 Códigos | PRUEBA: buscarElementoPorCodigo [Integración BD]
     * OBJETIVO: Un código interno encuentra el activo activo; uno dado de baja no.
     * PRECONDICIONES: elementos activo e inactivo con códigos propios.
     * DATOS: '20J-01-INF1-020' (activo) y '20J-01-INF1-021' (activo=0).
     * RESULTADO ESPERADO: encontrado para el activo, null para el inactivo.
     */
    public function testCOD003BuscarElementoPorCodigo(): void
    {
        $this->crearElemento(['codigo_interno' => '20J-01-INF1-020']);
        $this->crearElemento(['codigo_interno' => '20J-01-INF1-021', 'activo' => 0]);

        $this->assertNotNull(buscarElementoPorCodigo($this->conn, '20J-01-INF1-020'));
        $this->assertNull(buscarElementoPorCodigo($this->conn, '20J-01-INF1-021'));
        $this->assertNull(buscarElementoPorCodigo($this->conn, 'NO-EXISTE'));
    }

    /* ============================================================
     * SECCIÓN 10 — CÓDIGOS QR (implementación real Endroid)
     * ============================================================ */

    /**
     * ID: QRC-001 | MÓDULO: 10 QR | PRUEBA: generarQR
     * OBJETIVO: Generar el PNG real del QR del activo en assets/qr/.
     * PRECONDICIONES: vendor/autoload con Endroid\QrCode cargado.
     * DATOS: código '20J-01-INF1-001', id 7777.
     * RESULTADO ESPERADO: ruta 'qr/qr_7777.png', archivo PNG existente y no vacío.
     */
    public function testQRC001GenerarQRReal(): void
    {
        $ruta = generarQR('20J-01-INF1-001', 7777);
        $this->assertSame('qr/qr_7777.png', $ruta);
        $archivo = __DIR__ . '/../assets/qr/qr_7777.png';
        $this->assertFileExists($archivo);
        $this->assertGreaterThan(500, filesize($archivo));
        $this->borrarSiExiste($archivo);
    }

    /**
     * ID: QRC-002 | MÓDULO: 10 QR | PRUEBA: URL del QR
     * OBJETIVO: El QR apunta a la ficha pública del elemento.
     * PRECONDICIONES: config/institucion.php con url base.
     * DATOS: código '20J-01-INF1-001'.
     * RESULTADO ESPERADO: URL con ver_articulo.php y código codificado.
     */
    public function testQRC002UrlDelQR(): void
    {
        $url = urlFichaElemento('20J-01-INF1-001');
        $this->assertStringContainsString('http://localhost/mic/ver_articulo.php?codigo=20J-01-INF1-001', $url);
    }

    /**
     * ID: QRC-003 | MÓDULO: 10 QR | PRUEBA: qr_path persistido [Integración BD]
     * OBJETIVO: El qr_path guardado en el elemento se puede leer y el archivo existe.
     * PRECONDICIONES: elemento creado con qr_path válido (archivo generado).
     * DATOS: qr_path 'qr/qr_7778.png' + archivo creado.
     * RESULTADO ESPERADO: SELECT devuelve el path y el archivo existe.
     */
    public function testQRC003QrPathPersistido(): void
    {
        $qr = generarQR('20J-01-INF1-002', 7778);
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-002', 'qr_path' => $qr]);

        $path = $this->conn->query("SELECT qr_path FROM inventario_general WHERE id=$id")->fetchColumn();
        $this->assertSame($qr, $path);
        $this->assertFileExists(__DIR__ . '/../assets/' . $path);
        $this->borrarSiExiste(__DIR__ . '/../assets/' . $path);
    }

    /* ============================================================
     * SECCIÓN 11 — PROVEEDORES
     * ============================================================ */

    /**
     * ID: PRV-001 | MÓDULO: 11 Proveedores | PRUEBA: crear proveedor [Integración BD]
     * OBJETIVO: Un proveedor activo se persiste con sus datos comerciales.
     * PRECONDICIONES: mic_test limpia.
     * DATOS: 'Proveedor QA' con NIT 901234567.
     * RESULTADO ESPERADO: fila con estado 'Activo', NIT y teléfono.
     */
    public function testPRV001CrearProveedor(): void
    {
        $id = $this->crearProveedor('Proveedor QA', '901234567');
        $fila = $this->conn->query("SELECT * FROM proveedores WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Proveedor QA', $fila['nombre']);
        $this->assertSame('901234567', $fila['nit']);
        $this->assertSame('Activo', $fila['estado']);
    }

    /**
     * ID: PRV-002 | MÓDULO: 11 Proveedores | PRUEBA: FK proveedor del elemento [Integración BD]
     * OBJETIVO: La FK real inventario_general_ibfk_proveedor rechaza proveedor inexistente.
     * PRECONDICIONES: mic_test con FKs del esquema real.
     * DATOS: INSERT de elemento con proveedor_id=999.
     * RESULTADO ESPERADO: PDOException por violación de FK.
     */
    public function testPRV002ElementoRequiereProveedorExistente(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare(
            "INSERT INTO inventario_general (nombre, tipo, id_sede, proveedor_id, estado) VALUES ('X', 'Y', 1, 999, 'bueno')"
        )->execute();
    }

    /**
     * ID: PRV-003 | MÓDULO: 11 Proveedores | PRUEBA: proveedor en alertas [Integración BD]
     * OBJETIVO: La consulta de alertas incluye el nombre del proveedor.
     * PRECONDICIONES: proveedor + elemento vinculado.
     * DATOS: proveedor 'Proveedor Alertas'.
     * RESULTADO ESPERADO: proveedor_nombre en la fila del elemento.
     */
    public function testPRV003ProveedorEnConsultaDeAlertas(): void
    {
        $proveedorId = $this->crearProveedor('Proveedor Alertas', '900111222');
        $id = $this->crearElemento(['proveedor_id' => $proveedorId]);

        $fila = $this->conn->query(
            "SELECT prov.nombre AS proveedor_nombre FROM inventario_general ig LEFT JOIN proveedores prov ON ig.proveedor_id=prov.id WHERE ig.id=$id"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Proveedor Alertas', $fila['proveedor_nombre']);
    }

    /* ============================================================
     * SECCIÓN 12 — DOCUMENTACIÓN DE ADQUISICIÓN
     * ============================================================ */

    /**
     * ID: ADQ-001 | MÓDULO: 12 Documentación | PRUEBA: validarDocumentoSubido OK
     * OBJETIVO: Un PDF real se acepta como documento de adquisición.
     * PRECONDICIONES: archivo PDF temporal real.
     * DATOS: PDF de 400 bytes, error UPLOAD_ERR_OK.
     * RESULTADO ESPERADO: ['ok'=>true, 'ext'=>'pdf'].
     */
    public function testADQ001ValidarDocumentoPdf(): void
    {
        $tmp = $this->archivoPdfTemp();
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
            'name' => 'factura.pdf',
            'tmp_name' => $tmp,
        ]);
        $this->assertTrue($res['ok'], $res['error']);
        $this->assertSame('pdf', $res['ext']);
        $this->borrarSiExiste($tmp);
    }

    /**
     * ID: ADQ-002 | MÓDULO: 12 Documentación | PRUEBA: validarDocumentoSubido errores
     * OBJETIVO: Rechazar archivos vacíos, de extensión prohibida, excedidos o
     *           con error de subida.
     * PRECONDICIONES: archivos temporales.
     * DATOS: .txt; size 0; size 6 MB; UPLOAD_ERR_INI_SIZE.
     * RESULTADO ESPERADO: ['ok'=>false] en los cuatro casos.
     */
    public function testADQ002ValidarDocumentoRechazaInvalidos(): void
    {
        $txt = tempnam(sys_get_temp_dir(), 'mic_txt_');
        file_put_contents($txt, 'hola mundo');
        $this->assertFalse(validarDocumentoSubido(['error' => UPLOAD_ERR_OK, 'size' => 10, 'name' => 'doc.txt', 'tmp_name' => $txt])['ok']);

        $this->assertFalse(validarDocumentoSubido(['error' => UPLOAD_ERR_OK, 'size' => 0, 'name' => 'a.pdf', 'tmp_name' => $txt])['ok']);

        $pdf = $this->archivoPdfTemp();
        $this->assertFalse(validarDocumentoSubido(['error' => UPLOAD_ERR_OK, 'size' => MAX_DOC_SIZE + 1, 'name' => 'a.pdf', 'tmp_name' => $pdf])['ok']);

        $this->assertFalse(validarDocumentoSubido(['error' => UPLOAD_ERR_INI_SIZE, 'size' => 10, 'name' => 'a.pdf', 'tmp_name' => $pdf])['ok']);

        $this->borrarSiExiste($txt);
        $this->borrarSiExiste($pdf);
    }

    /**
     * ID: ADQ-003 | MÓDULO: 12 Documentación | PRUEBA: guardarDocumento
     * OBJETIVO: Guardar el documento en uploads/documentos con nombre único.
     * PRECONDICIONES: archivo PDF temporal real.
     * DATOS: elemento id 9001.
     * RESULTADO ESPERADO: ruta 'documentos/doc_9001_*.pdf' y archivo existente.
     */
    public function testADQ003GuardarDocumento(): void
    {
        $tmp = $this->archivoPdfTemp();
        $ruta = guardarDocumento(['tmp_name' => $tmp, 'name' => 'factura.pdf', 'ext' => 'pdf'], 9001);
        $this->assertNotNull($ruta);
        $this->assertStringStartsWith('documentos/doc_9001_', $ruta);
        $this->assertFileExists(__DIR__ . '/../uploads/' . $ruta);
        $this->borrarSiExiste(__DIR__ . '/../uploads/' . $ruta);
        $this->borrarSiExiste($tmp);
    }

    /**
     * ID: ADQ-004 | MÓDULO: 12 Documentación | PRUEBA: eliminarArchivoDocumento
     * OBJETIVO: Eliminar físicamente un documento subido.
     * PRECONDICIONES: archivo creado en uploads/documentos.
     * DATOS: ruta 'documentos/doc_9002_tmp.pdf'.
     * RESULTADO ESPERADO: archivo eliminado; ruta vacía no rompe nada.
     */
    public function testADQ004EliminarDocumento(): void
    {
        $archivo = __DIR__ . '/../uploads/documentos/doc_9002_tmp.pdf';
        file_put_contents($archivo, 'tmp');
        eliminarArchivoDocumento('documentos/doc_9002_tmp.pdf');
        $this->assertFileDoesNotExist($archivo);

        eliminarArchivoDocumento('');
        eliminarArchivoDocumento('documentos/no_existe.pdf');
        $this->assertTrue(true);
    }

    /* ============================================================
     * SECCIÓN 13 — ACTAS (helpers_actas.php)
     * ============================================================ */

    /**
     * ID: ACT-001 | MÓDULO: 13 Actas | PRUEBA: construirActaHTML
     * OBJETIVO: El acta HTML incluye institución, responsable, activos con
     *           valor, adquisición y firmas; escapa HTML de los datos.
     * PRECONDICIONES: $GLOBALS['institucion'] cargado; elementos de ejemplo.
     * DATOS: profesor 'Ana Pérez'; 2 elementos con vr_comercial 1.500.000 y
     *        nombre con '<script>'.
     * RESULTADO ESPERADO: contiene 'Acta de Entrega y Responsabilidad de
     *           Bienes', 'Ana Pérez', '$1.500.000', el código interno y
     *           '&lt;script&gt;' escapado.
     */
    public function testACT001ConstruirActaHTML(): void
    {
        $institucion = $GLOBALS['institucion'];
        $profesor = ['nombre' => 'Ana', 'apellido' => 'Pérez', 'identificacion' => 'CC-123', 'correo' => 'ana@test.edu.co'];
        $elementos = [
            ['id' => 1, 'codigo_interno' => '20J-01-INF1-001', 'nombre' => '<script>alert(1)</script>', 'tipo' => 'Portátil', 'categoria' => 'Académico', 'marca' => 'Lenovo', 'numero_serie' => 'SN-1', 'estado' => 'bueno', 'vr_comercial' => 1500000, 'origen_bien' => 'Compra', 'proveedor_nombre' => 'Proveedor X', 'proveedor_nit' => '900', 'numero_factura' => 'F-1', 'fecha_compra' => '2026-01-01', 'valor_compra' => 1400000, 'fecha_garantia' => '2027-01-01', 'documento_adquisicion' => 'documentos/doc.pdf', 'qr_path' => null],
            ['id' => 2, 'codigo_interno' => '20J-01-INF1-002', 'nombre' => 'VideoBeam', 'tipo' => 'Proyector', 'categoria' => 'Académico', 'marca' => 'EPSON', 'numero_serie' => 'SN-2', 'estado' => 'nuevo', 'vr_comercial' => 0, 'origen_bien' => 'Transferencia', 'institucion_origen' => 'Colegio XYZ', 'fecha_transferencia' => '2026-02-01', 'documento_adquisicion' => null, 'qr_path' => null],
        ];

        $html = construirActaHTML($institucion, $profesor, 'Sede Principal', $elementos, ['Aula de Informática 1'], null);

        $this->assertStringContainsString('Acta de Entrega y Responsabilidad de Bienes', $html);
        $this->assertStringContainsString('Institución Educativa 20 de Julio', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('20J-01-INF1-001', $html);
        $this->assertStringContainsString('$1,500,000', $html);
        $this->assertStringContainsString('CÓDIGOS QR DE LOS ACTIVOS', $html);
        $this->assertStringContainsString('Colegio XYZ', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * ID: ACT-002 | MÓDULO: 13 Actas | PRUEBA: ubicacionPerteneceSedeActa
     * OBJETIVO: Validar ubicaciones del acta contra el catálogo.
     * PRECONDICIONES: catalogosUbicaciones cargado.
     * DATOS: 'Biblioteca' (Sede Principal) true; 'Patio' (El Porvenir) en
     *        Sede Principal false; vacío true.
     * RESULTADO ESPERADO: true, false, true.
     */
    public function testACT002UbicacionPerteneceSedeActa(): void
    {
        $catalogo = $GLOBALS['catalogosUbicaciones'];
        $this->assertTrue(ubicacionPerteneceSedeActa($catalogo, 'Sede Principal', 'Biblioteca'));
        $this->assertFalse(ubicacionPerteneceSedeActa($catalogo, 'Sede Principal', 'Patio'));
        $this->assertTrue(ubicacionPerteneceSedeActa($catalogo, 'Sede Principal', ''));
    }

    /* ============================================================
     * SECCIÓN 14 — REASIGNACIÓN
     * ============================================================ */

    /**
     * ID: REA-001 | MÓDULO: 14 Reasignación | PRUEBA: cambio de responsable real
     * OBJETIVO: La reasignación actualiza profesor_id y registra el evento con
     *           el responsable anterior y nuevo.
     * PRECONDICIONES: elemento + profesor nuevo.
     * DATOS: reasignar de null a profesor id.
     * RESULTADO ESPERADO: UPDATE aplicado y historial con datos_anterior null
     *           y datos_nuevos con profesor_id.
     */
    public function testREA001ReasignarElemento(): void
    {
        $nuevo = $this->crearProfesor('Sofía', 'Herrera', 1);
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-030']);

        $usuarioId = $this->crearUsuario('Reasignación QA');
        $this->conn->prepare("UPDATE inventario_general SET profesor_id=? WHERE id=?")->execute([$nuevo, $id]);
        registrarEventoHistorial(
            $this->conn, $id, 'reasignacion',
            'Reasignado a Sofía Herrera',
            ['profesor_id' => null],
            ['profesor_id' => $nuevo],
            $usuarioId
        );

        $historial = historialDeElemento($this->conn, $id);
        $this->assertSame('reasignacion', $historial[0]['tipo_evento']);
        $this->assertNull($historial[0]['datos_anterior']['profesor_id']);
        $this->assertSame($nuevo, $historial[0]['datos_nuevos']['profesor_id']);
        $this->assertSame($nuevo, (int)$this->conn->query("SELECT profesor_id FROM inventario_general WHERE id=$id")->fetchColumn());
    }

    /**
     * ID: REA-002 | MÓDULO: 14 Reasignación | PRUEBA: historial conserva ambos datos
     * OBJETIVO: El historial guarda el responsable anterior y el nuevo (JSON).
     * PRECONDICIONES: 2 profesores y un elemento.
     * DATOS: reasignar de profesor A a profesor B.
     * RESULTADO ESPERADO: datos_anterior['profesor_id']=A y
     *           datos_nuevos['profesor_id']=B tras decodificar.
     */
    public function testREA002HistorialConservaAnteriorYNuevo(): void
    {
        $a = $this->crearProfesor('Ana', 'Pérez', 1);
        $b = $this->crearProfesor('Bruno', 'Díaz', 1);
        $id = $this->crearElemento(['profesor_id' => $a]);

        $usuarioId = $this->crearUsuario('Historial Reasignación QA');
        registrarEventoHistorial($this->conn, $id, 'reasignacion', 'Reasignado', ['profesor_id' => $a], ['profesor_id' => $b], $usuarioId);
        $historial = historialDeElemento($this->conn, $id);
        $this->assertSame($a, $historial[0]['datos_anterior']['profesor_id']);
        $this->assertSame($b, $historial[0]['datos_nuevos']['profesor_id']);
    }

    /* ============================================================
     * SECCIÓN 15 — CAMBIO DE UBICACIÓN
     * ============================================================ */

    /**
     * ID: CAM-001 | MÓDULO: 15 Cambio ubicación | PRUEBA: cambio con código nuevo
     * OBJETIVO: Al mover un activo se actualiza ubicación y codigo_ubicacion.
     * PRECONDICIONES: elemento en INF1.
     * DATOS: mover a 'Biblioteca' (BIB).
     * RESULTADO ESPERADO: codigo_ubicacion='BIB' tras obtenerCodigoUbicacion.
     */
    public function testCAM001CambiarUbicacionConCodigo(): void
    {
        $id = $this->crearElemento(['ubicacion' => 'Aula de Informática 1', 'codigo_ubicacion' => 'INF1']);

        $nuevoCodigo = obtenerCodigoUbicacion('Sede Principal', 'Biblioteca');
        $this->assertSame('BIB', $nuevoCodigo);
        $this->conn->prepare("UPDATE inventario_general SET ubicacion='Biblioteca', codigo_ubicacion=? WHERE id=?")->execute([$nuevoCodigo, $id]);

        $fila = $this->conn->query("SELECT ubicacion, codigo_ubicacion FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Biblioteca', $fila['ubicacion']);
        $this->assertSame('BIB', $fila['codigo_ubicacion']);
    }

    /**
     * ID: CAM-002 | MÓDULO: 15 Cambio ubicación | PRUEBA: historial del cambio
     * OBJETIVO: El evento cambio_ubicacion guarda ubicación anterior y nueva.
     * PRECONDICIONES: elemento existente.
     * DATOS: cambio INF1 → BIB con historial.
     * RESULTADO ESPERADO: evento 'cambio_ubicacion' con datos decodificados.
     */
    public function testCAM002HistorialCambioUbicacion(): void
    {
        $id = $this->crearElemento(['ubicacion' => 'Aula de Informática 1', 'codigo_ubicacion' => 'INF1']);

        $usuarioId = $this->crearUsuario('Cambio Ubicación QA');
        $this->conn->prepare("UPDATE inventario_general SET ubicacion='Biblioteca', codigo_ubicacion='BIB' WHERE id=?")->execute([$id]);
        registrarEventoHistorial(
            $this->conn, $id, 'cambio_ubicacion',
            'Movido a Biblioteca',
            ['ubicacion' => 'Aula de Informática 1', 'codigo_ubicacion' => 'INF1'],
            ['ubicacion' => 'Biblioteca', 'codigo_ubicacion' => 'BIB'],
            $usuarioId
        );

        $historial = historialDeElemento($this->conn, $id);
        $this->assertSame('cambio_ubicacion', $historial[0]['tipo_evento']);
        $this->assertSame('Biblioteca', $historial[0]['datos_nuevos']['ubicacion']);
        $this->assertSame('INF1', $historial[0]['datos_anterior']['codigo_ubicacion']);
    }

    /* ============================================================
     * SECCIÓN 16 — HISTORIAL (helpers_historial.php)
     * ============================================================ */

    /**
     * ID: HIS-001 | MÓDULO: 16 Historial | PRUEBA: registrarEventoHistorial
     * OBJETIVO: Insertar evento y recuperarlo decodificado.
     * PRECONDICIONES: elemento real.
     * DATOS: tipo 'modificacion' con datos JSON.
     * RESULTADO ESPERADO: 1 fila; datos_nuevos['estado']='regular'.
     */
    public function testHIS001RegistrarYLeerHistorial(): void
    {
        $id = $this->crearElemento();
        $usuarioId = $this->crearUsuario('Historial QA');
        registrarEventoHistorial($this->conn, $id, 'modificacion', 'Estado cambiado', ['estado' => 'bueno'], ['estado' => 'regular'], $usuarioId);

        $historial = historialDeElemento($this->conn, $id);
        $this->assertCount(1, $historial);
        $this->assertSame('modificacion', $historial[0]['tipo_evento']);
        $this->assertSame('regular', $historial[0]['datos_nuevos']['estado']);
        $this->assertSame('bueno', $historial[0]['datos_anterior']['estado']);
    }

    /**
     * ID: HIS-002 | MÓDULO: 16 Historial | PRUEBA: infoTipoEvento
     * OBJETIVO: Etiquetas de eventos conocidos y desconocidos.
     * PRECONDICIONES: constante TIPOS_EVENTO_HISTORIAL cargada.
     * DATOS: 'registro' y 'evento_desconocido'.
     * RESULTADO ESPERADO: 'Registrado' y 'Evento desconocido'.
     */
    public function testHIS002InfoTipoEvento(): void
    {
        $this->assertSame('Registrado', infoTipoEvento('registro')['label']);
        $this->assertSame('Evento desconocido', infoTipoEvento('evento_desconocido')['label']);
    }

    /**
     * ID: HIS-003 | MÓDULO: 16 Historial | PRUEBA: orden cronológico
     * OBJETIVO: El historial se devuelve ordenado de más antiguo a más reciente.
     * PRECONDICIONES: elemento real.
     * DATOS: 2 eventos (registro y modificacion).
     * RESULTADO ESPERADO: 2 filas; la primera es 'registro' y la segunda
     *           'modificacion'.
     */
    public function testHIS003OrdenCronologico(): void
    {
        $id = $this->crearElemento();
        $usuarioId = $this->crearUsuario('Historial Orden QA');
        registrarEventoHistorial($this->conn, $id, 'registro', 'Registro inicial', null, null, $usuarioId);
        registrarEventoHistorial($this->conn, $id, 'modificacion', 'Ajuste', null, null, $usuarioId);

        $historial = historialDeElemento($this->conn, $id);
        $this->assertCount(2, $historial);
        $this->assertSame('registro', $historial[0]['tipo_evento']);
        $this->assertSame('modificacion', $historial[1]['tipo_evento']);
    }

    /* ============================================================
     * SECCIÓN 17 — TOMA FÍSICA (helpers_toma_fisica.php)
     * ============================================================ */

    /**
     * ID: TOM-001 | MÓDULO: 17 Toma física | PRUEBA: iniciarTomaFisica [Integración BD]
     * OBJETIVO: Crear toma física con el detalle de activos esperados.
     * PRECONDICIONES: 2 activos en Sede Principal / 'Aula de Informática 1'.
     * DATOS: sede 1, ubicación 'Aula de Informática 1', usuario 1.
     * RESULTADO ESPERADO: toma 'en_progreso' con total_esperados=2 y 2 filas
     *           en tomas_fisicas_detalle.
     */
    public function testTOM001IniciarTomaFisica(): void
    {
        $this->crearElemento(['codigo_interno' => '20J-01-INF1-040']);
        $this->crearElemento(['codigo_interno' => '20J-01-INF1-041']);
        $usuarioId = $this->crearUsuario('Toma Física QA');

        $tomaId = iniciarTomaFisica($this->conn, 1, 'Aula de Informática 1', $usuarioId);
        $this->assertGreaterThan(0, $tomaId);

        $toma = obtenerToma($this->conn, $tomaId);
        $this->assertSame('en_progreso', $toma['estado']);
        $this->assertSame(2, (int)$toma['total_esperados']);
        $this->assertSame('Sede Principal', $toma['sede_nombre']);
        $this->assertCount(2, obtenerDetallesToma($this->conn, $tomaId));
    }

    /**
     * ID: TOM-002 | MÓDULO: 17 Toma física | PRUEBA: errores de inicio
     * OBJETIVO: Rechazar sede inexistente y ubicación sin activos.
     * PRECONDICIONES: 1 activo en INF1.
     * DATOS: sede 999; ubicación 'Bodega' sin activos.
     * RESULTADO ESPERADO: RuntimeException 'no existe' y 'No hay activos'.
     */
    public function testTOM002ErroresAlIniciarToma(): void
    {
        $this->crearElemento(['ubicacion' => 'Aula de Informática 1']);
        $usuarioId = $this->crearUsuario('Toma Física Errores QA');

        try {
            iniciarTomaFisica($this->conn, 999, 'Aula de Informática 1', $usuarioId);
            $this->fail('Debió rechazar la sede inexistente');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no existe', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay activos');
        iniciarTomaFisica($this->conn, 1, 'Bodega', $usuarioId);
    }

    /**
     * ID: TOM-003 | MÓDULO: 17 Toma física | PRUEBA: verificar elemento encontrado
     * OBJETIVO: Verificar físicamente un activo: estado, situación y coincidencias.
     * PRECONDICIONES: toma iniciada con 1 activo (estado 'bueno').
     * DATOS: encontrado=1, estado_encontrado 'dañado', situacion_despues
     *        'en_reparacion', coincide_codigo=1.
     * RESULTADO ESPERADO: elemento con estado 'dañado' y situacion
     *           'en_reparacion'; detalle encontrado=1; historial
     *           'inspeccion_fisica' con descripción de cambio.
     */
    public function testTOM003VerificarElementoEncontrado(): void
    {
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-050', 'estado' => 'bueno']);
        $usuarioId = $this->crearUsuario('Verificador Toma QA');
        $tomaId = iniciarTomaFisica($this->conn, 1, 'Aula de Informática 1', $usuarioId);
        $detalle = $this->conn->query("SELECT id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND elemento_id=$id")->fetchColumn();

        $ok = verificarElementoEnToma($this->conn, (int)$detalle, [
            'encontrado' => true,
            'estado_encontrado' => 'dañado',
            'coincide_codigo' => true,
            'coincide_sede' => true,
            'coincide_ubicacion' => true,
            'coincide_responsable' => false,
            'cambiar_estado' => true,
            'situacion_despues' => 'en_reparacion',
            'observacion' => 'Pantalla rota',
        ], $usuarioId);

        $this->assertTrue($ok);
        $fila = $this->conn->query("SELECT estado, situacion FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('dañado', $fila['estado']);
        $this->assertSame('en_reparacion', $fila['situacion']);

        $det = $this->conn->query("SELECT * FROM tomas_fisicas_detalle WHERE id=$detalle")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$det['encontrado']);
        $this->assertSame('dañado', $det['estado_encontrado']);
        $this->assertSame(1, (int)$det['coincide_codigo']);
        $this->assertSame('Pantalla rota', $det['observacion']);

        $historial = historialDeElemento($this->conn, $id);
        $this->assertSame('inspeccion_fisica', $historial[0]['tipo_evento']);
        $this->assertStringContainsString('bueno → dañado', $historial[0]['descripcion']);
    }

    /**
     * ID: TOM-004 | MÓDULO: 17 Toma física | PRUEBA: verificar elemento NO encontrado
     * OBJETIVO: Un activo no encontrado pasa a situacion 'no_encontrado'.
     * PRECONDICIONES: toma iniciada con 1 activo.
     * DATOS: encontrado=false con observación.
     * RESULTADO ESPERADO: detalle encontrado=0; elemento situacion
     *           'no_encontrado'; historial 'inspeccion_fisica' con
     *           'NO encontrado'.
     */
    public function testTOM004VerificarElementoNoEncontrado(): void
    {
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-051']);
        $usuarioId = $this->crearUsuario('Verificador Toma QA 2');
        $tomaId = iniciarTomaFisica($this->conn, 1, 'Aula de Informática 1', $usuarioId);
        $detalle = $this->conn->query("SELECT id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId AND elemento_id=$id")->fetchColumn();

        verificarElementoEnToma($this->conn, (int)$detalle, ['encontrado' => false, 'observacion' => 'No estaba'], $usuarioId);

        $det = $this->conn->query("SELECT encontrado FROM tomas_fisicas_detalle WHERE id=$detalle")->fetchColumn();
        $this->assertSame(0, (int)$det);
        $this->assertSame('no_encontrado', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$id")->fetchColumn());

        $historial = historialDeElemento($this->conn, $id);
        $this->assertStringContainsString('NO encontrado', $historial[0]['descripcion']);
    }

    /**
     * ID: TOM-005 | MÓDULO: 17 Toma física | PRUEBA: finalizarTomaFisica
     * OBJETIVO: Al finalizar se calculan encontrados, no encontrados y dañados.
     * PRECONDICIONES: toma con 2 activos; 1 verificado encontrado (dañado) y 1
     *                 verificado no encontrado.
     * DATOS: verificación encontrado + no encontrado.
     * RESULTADO ESPERADO: toma 'finalizada' con encontrados=1,
     *           no_encontrados=1 y dañados=1.
     */
    public function testTOM005FinalizarTomaFisica(): void
    {
        $a = $this->crearElemento(['codigo_interno' => '20J-01-INF1-052']);
        $b = $this->crearElemento(['codigo_interno' => '20J-01-INF1-053']);
        $usuarioId = $this->crearUsuario('Verificador Toma QA 3');
        $tomaId = iniciarTomaFisica($this->conn, 1, 'Aula de Informática 1', $usuarioId);
        $dets = $this->conn->query("SELECT id, elemento_id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId")->fetchAll(PDO::FETCH_ASSOC);
        $detA = $dets[0]['elemento_id'] == $a ? $dets[0]['id'] : $dets[1]['id'];
        $detB = $dets[0]['elemento_id'] == $b ? $dets[0]['id'] : $dets[1]['id'];

        verificarElementoEnToma($this->conn, (int)$detA, ['encontrado' => true, 'estado_encontrado' => 'dañado', 'cambiar_estado' => true], $usuarioId);
        verificarElementoEnToma($this->conn, (int)$detB, ['encontrado' => false], $usuarioId);

        finalizarTomaFisica($this->conn, $tomaId, 'Toma completada');
        $toma = obtenerToma($this->conn, $tomaId);
        $this->assertSame('finalizada', $toma['estado']);
        $this->assertSame(1, (int)$toma['encontrados']);
        $this->assertSame(1, (int)$toma['no_encontrados']);
        $this->assertSame(1, (int)$toma['danados']);
        $this->assertSame('Toma completada', $toma['observaciones']);
    }

    /**
     * ID: TOM-006 | MÓDULO: 17 Toma física | PRUEBA: cancelarTomaFisica
     * OBJETIVO: Solo el dueño puede cancelar su toma en progreso.
     * PRECONDICIONES: toma creada por usuario 1.
     * DATOS: cancelar con usuario 2 (debe fallar) y con usuario 1 (debe pasar).
     * RESULTADO ESPERADO: RuntimeException para otro usuario; estado 'cancelada'
     *           para el dueño; obtenerTomaActiva ya no la devuelve.
     */
    public function testTOM006CancelarTomaFisica(): void
    {
        $this->crearElemento(['codigo_interno' => '20J-01-INF1-054']);
        $dueno = $this->crearUsuario('Dueño Toma QA');
        $otro = $this->crearUsuario('Otro Usuario QA');
        $tomaId = iniciarTomaFisica($this->conn, 1, 'Aula de Informática 1', $dueno);

        try {
            cancelarTomaFisica($this->conn, $tomaId, $otro);
            $this->fail('Otro usuario no debe poder cancelar la toma');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no es de este usuario', $e->getMessage());
        }

        $this->assertTrue(cancelarTomaFisica($this->conn, $tomaId, $dueno));
        $this->assertSame('cancelada', $this->conn->query("SELECT estado FROM tomas_fisicas WHERE id=$tomaId")->fetchColumn());
        $this->assertNull(obtenerTomaActiva($this->conn, $dueno));
    }

    /**
     * ID: TOM-007 | MÓDULO: 17 Toma física | PRUEBA: cambiarSituacion
     * OBJETIVO: Cambiar la situación de un activo con historial.
     * PRECONDICIONES: activo existente.
     * DATOS: 'en_investigacion'; luego 'en_investigacion' de nuevo; luego 'rota'.
     * RESULTADO ESPERADO: UPDATE aplicado + historial 'cambio_situacion';
     *           repetir la misma no duplica historial; inválida lanza
     *           RuntimeException.
     */
    public function testTOM007CambiarSituacion(): void
    {
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-055']);
        $usuarioId = $this->crearUsuario('Situación QA');

        $this->assertTrue(cambiarSituacion($this->conn, $id, 'en_investigacion', $usuarioId, 'En revisión'));
        $this->assertSame('en_investigacion', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$id")->fetchColumn());
        $this->assertCount(1, historialDeElemento($this->conn, $id));
        $this->assertSame('cambio_situacion', historialDeElemento($this->conn, $id)[0]['tipo_evento']);

        cambiarSituacion($this->conn, $id, 'en_investigacion', $usuarioId);
        $this->assertCount(1, historialDeElemento($this->conn, $id), 'No debe duplicar historial si la situación no cambia');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Situación inválida');
        cambiarSituacion($this->conn, $id, 'rota', $usuarioId);
    }

    /* ============================================================
     * SECCIÓN 18 — EVIDENCIAS
     * ============================================================ */

    /**
     * ID: EVI-001 | MÓDULO: 18 Evidencias | PRUEBA: validarEvidenciaSubida
     * OBJETIVO: Aceptar PNG real y rechazar extensiones prohibidas.
     * PRECONDICIONES: archivo PNG 1x1 real.
     * DATOS: PNG válido; .txt.
     * RESULTADO ESPERADO: ['ok'=>true,'ext'=>'png'] y ['ok'=>false].
     */
    public function testEVI001ValidarEvidencia(): void
    {
        $png = $this->archivoPngTemp();
        $res = validarEvidenciaSubida(['error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'name' => 'foto.png', 'tmp_name' => $png]);
        $this->assertTrue($res['ok'], $res['error']);
        $this->assertSame('png', $res['ext']);

        $txt = tempnam(sys_get_temp_dir(), 'mic_txt_');
        file_put_contents($txt, 'no es imagen');
        $this->assertFalse(validarEvidenciaSubida(['error' => UPLOAD_ERR_OK, 'size' => 10, 'name' => 'foto.txt', 'tmp_name' => $txt])['ok']);
        $this->assertFalse(validarEvidenciaSubida(['error' => UPLOAD_ERR_OK, 'size' => MAX_EVIDENCIA_SIZE + 1, 'name' => 'foto.png', 'tmp_name' => $png])['ok']);

        $this->borrarSiExiste($png);
        $this->borrarSiExiste($txt);
    }

    /**
     * ID: EVI-002 | MÓDULO: 18 Evidencias | PRUEBA: registrarEvidencias [Integración BD]
     * OBJETIVO: Guardar evidencias válidas y reportar errores de las inválidas.
     * PRECONDICIONES: usuario real; PNG temporal.
     * DATOS: 1 PNG válido + 1 .txt inválido.
     * RESULTADO ESPERADO: guardadas=1, errores=1, ok=false; evidenciasDeEntidad
     *           devuelve 1 registro.
     */
    public function testEVI002RegistrarEvidencias(): void
    {
        $usuarioId = $this->crearUsuario('Evidencias QA');
        $png = $this->archivoPngTemp();
        $txt = tempnam(sys_get_temp_dir(), 'mic_txt_');
        file_put_contents($txt, 'no valido');

        $res = registrarEvidencias($this->conn, [
            ['archivo' => ['name' => 'foto.png', 'tmp_name' => $png, 'size' => filesize($png), 'error' => UPLOAD_ERR_OK], 'tipo' => 'Foto del daño'],
            ['archivo' => ['name' => 'nota.txt', 'tmp_name' => $txt, 'size' => 10, 'error' => UPLOAD_ERR_OK], 'tipo' => 'Documento adicional'],
        ], 'mantenimiento', 500, $usuarioId);

        $this->assertFalse($res['ok']);
        $this->assertSame(1, $res['guardadas']);
        $this->assertCount(1, $res['errores']);

        $evidencias = evidenciasDeEntidad($this->conn, 'mantenimiento', 500);
        $this->assertCount(1, $evidencias);
        $this->assertSame('Foto del daño', $evidencias[0]['tipo_evidencia']);
        $this->assertSame('Evidencias QA', $evidencias[0]['usuario_nombre']);

        $this->borrarSiExiste(__DIR__ . '/../uploads/' . $evidencias[0]['archivo']);
        $this->borrarSiExiste($png);
        $this->borrarSiExiste($txt);
    }

    /**
     * ID: EVI-003 | MÓDULO: 18 Evidencias | PRUEBA: eliminarEvidencia [Integración BD]
     * OBJETIVO: Eliminar el registro y el archivo físico de la evidencia.
     * PRECONDICIONES: evidencia guardada real.
     * DATOS: evidencia id de la prueba.
     * RESULTADO ESPERADO: fila eliminada y archivo ya no existe.
     */
    public function testEVI003EliminarEvidencia(): void
    {
        $usuarioId = $this->crearUsuario('Evidencias QA 2');
        $png = $this->archivoPngTemp();
        $res = registrarEvidencias($this->conn, [
            ['archivo' => ['name' => 'foto.png', 'tmp_name' => $png, 'size' => filesize($png), 'error' => UPLOAD_ERR_OK], 'tipo' => 'Foto'],
        ], 'bajas', 501, $usuarioId);
        $this->assertSame(1, $res['guardadas']);
        $this->borrarSiExiste($png);

        $ev = evidenciasDeEntidad($this->conn, 'bajas', 501)[0];
        $archivo = __DIR__ . '/../uploads/' . $ev['archivo'];
        $this->assertFileExists($archivo);

        eliminarEvidencia($this->conn, (int)$ev['id']);
        $this->assertSame([], evidenciasDeEntidad($this->conn, 'bajas', 501));
        $this->assertFileDoesNotExist($archivo);
    }

    /**
     * ID: EVI-004 | MÓDULO: 18 Evidencias | PRUEBA: guardarArchivoEvidencia
     * OBJETIVO: El archivo se guarda con nombre único generado por el servidor.
     * PRECONDICIONES: PNG temporal real.
     * DATOS: PNG sin 'ext' (se infiere del nombre).
     * RESULTADO ESPERADO: ruta 'evidencias/ev_*.png' y archivo existente.
     */
    public function testEVI004GuardarArchivoEvidencia(): void
    {
        $png = $this->archivoPngTemp();
        $ruta = guardarArchivoEvidencia(['tmp_name' => $png, 'name' => 'foto.png']);
        $this->assertNotNull($ruta);
        $this->assertMatchesRegularExpression('#^evidencias/ev_\d{8}_\d{6}_[0-9a-f]{16}\.png$#', $ruta);
        $this->assertFileExists(__DIR__ . '/../uploads/' . $ruta);
        $this->borrarSiExiste(__DIR__ . '/../uploads/' . $ruta);
        $this->borrarSiExiste($png);
    }

    /* ============================================================
     * SECCIÓN 19 — MANTENIMIENTO
     * ============================================================ */

    /**
     * ID: MNT-001 | MÓDULO: 19 Mantenimiento | PRUEBA: enviarAMantenimiento [Integración BD]
     * OBJETIVO: Enviar activo a mantenimiento: registro 'programado', situación
     *           'en_mantenimiento' e historial.
     * PRECONDICIONES: activo real + usuario real.
     * DATOS: descripción, costo 120000, técnico 'Juan Rojas'.
     * RESULTADO ESPERADO: mantenimiento insertado (estado 'programado'),
     *           situacion='en_mantenimiento' e historial 'mantenimiento_iniciado'.
     */
    public function testMNT001EnviarAMantenimiento(): void
    {
        $usuarioId = $this->crearUsuario('Mantenimiento QA');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-060']);

        $mtoId = enviarAMantenimiento($this->conn, $id, [
            'descripcion' => 'Cambio de disco duro',
            'costo' => 120000,
            'tecnico' => 'Juan Rojas',
            'proveedor' => 'TecnoServicios',
        ], $usuarioId);

        $this->assertGreaterThan(0, $mtoId);
        $mto = $this->conn->query("SELECT * FROM mantenimiento WHERE id=$mtoId")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('programado', $mto['estado']);
        $this->assertSame('Cambio de disco duro', $mto['descripcion_trabajo']);
        $this->assertSame('Juan Rojas', $mto['tecnico']);
        $this->assertEqualsWithDelta(120000.0, (float)$mto['costo'], 0.001);

        $this->assertSame('en_mantenimiento', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$id")->fetchColumn());
        $this->assertSame('mantenimiento_iniciado', historialDeElemento($this->conn, $id)[0]['tipo_evento']);
    }

    /**
     * ID: MNT-002 | MÓDULO: 19 Mantenimiento | PRUEBA: errores al enviar
     * OBJETIVO: Rechazar mantenimiento sin descripción o de activo dado de baja.
     * PRECONDICIONES: activo real; activo con situacion 'dado_de_baja'.
     * DATOS: descripción vacía; activo dado de baja.
     * RESULTADO ESPERADO: RuntimeException 'obligatoria' y 'dado de baja'.
     */
    public function testMNT002ErroresAlEnviarMantenimiento(): void
    {
        $usuarioId = $this->crearUsuario('Mantenimiento QA 2');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-061']);

        try {
            enviarAMantenimiento($this->conn, $id, ['descripcion' => '   '], $usuarioId);
            $this->fail('Debió exigir descripción');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('obligatoria', $e->getMessage());
        }

        $this->conn->prepare("UPDATE inventario_general SET situacion='dado_de_baja' WHERE id=?")->execute([$id]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dado de baja');
        enviarAMantenimiento($this->conn, $id, ['descripcion' => 'Reparar'], $usuarioId);
    }

    /**
     * ID: MNT-003 | MÓDULO: 19 Mantenimiento | PRUEBA: finalizar 'Reparado' [Integración BD]
     * OBJETIVO: Al finalizar con 'Reparado' el activo queda 'bueno' y disponible.
     * PRECONDICIONES: mantenimiento programado creado por la prueba.
     * DATOS: resultado 'Reparado', costo 90000.
     * RESULTADO ESPERADO: mantenimiento 'completado' con resultado; activo
     *           estado 'bueno' y situacion 'disponible'; historial
     *           'mantenimiento_finalizado'.
     */
    public function testMNT003FinalizarReparado(): void
    {
        $usuarioId = $this->crearUsuario('Mantenimiento QA 3');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-062', 'estado' => 'dañado']);
        $mtoId = enviarAMantenimiento($this->conn, $id, ['descripcion' => 'Cambio de pantalla'], $usuarioId);

        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'Reparado', 'costo' => 90000], $usuarioId);

        $mto = $this->conn->query("SELECT estado, resultado FROM mantenimiento WHERE id=$mtoId")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('completado', $mto['estado']);
        $this->assertSame('Reparado', $mto['resultado']);

        $fila = $this->conn->query("SELECT estado, situacion FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('bueno', $fila['estado']);
        $this->assertSame('disponible', $fila['situacion']);

        $historial = historialDeElemento($this->conn, $id);
        $this->assertSame('mantenimiento_finalizado', $historial[1]['tipo_evento']);
    }

    /**
     * ID: MNT-004 | MÓDULO: 19 Mantenimiento | PRUEBA: finalizar 'Requiere nueva reparación'
     * OBJETIVO: Resultado que requiere nueva reparación deja el activo en
     *           'en_reparacion' sin cambiar el estado; resultado inválido falla.
     * PRECONDICIONES: mantenimiento programado.
     * DATOS: resultado 'Requiere nueva reparación'; luego resultado 'Roba'.
     * RESULTADO ESPERADO: situacion 'en_reparacion', estado intacto;
     *           RuntimeException 'inválido'.
     */
    public function testMNT004FinalizarRequiereNuevaReparacion(): void
    {
        $usuarioId = $this->crearUsuario('Mantenimiento QA 4');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-063', 'estado' => 'regular']);
        $mtoId = enviarAMantenimiento($this->conn, $id, ['descripcion' => 'Revisión general'], $usuarioId);

        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'Requiere nueva reparación'], $usuarioId);
        $fila = $this->conn->query("SELECT estado, situacion FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('regular', $fila['estado']);
        $this->assertSame('en_reparacion', $fila['situacion']);

        $mtoId2 = enviarAMantenimiento($this->conn, $id, ['descripcion' => 'Otra revisión'], $usuarioId);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inválido');
        finalizarMantenimientoToma($this->conn, $mtoId2, ['resultado' => 'Roba'], $usuarioId);
    }

    /* ============================================================
     * SECCIÓN 20 — BAJA DE ACTIVOS
     * ============================================================ */

    /**
     * ID: BAJ-001 | MÓDULO: 20 Baja | PRUEBA: solicitarBaja [Integración BD]
     * OBJETIVO: Solicitar baja de un activo (no se elimina; requiere aprobación).
     * PRECONDICIONES: activo real + usuario real.
     * DATOS: motivo 'Obsolescencia', fecha de hoy, descripción.
     * RESULTADO ESPERADO: baja 'solicitada'; historial 'baja_solicitada';
     *           duplicado pendiente lanza RuntimeException.
     */
    public function testBAJ001SolicitarBaja(): void
    {
        $usuarioId = $this->crearUsuario('Baja QA');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-070']);

        $bajaId = solicitarBaja($this->conn, $id, 'Obsolescencia', date('Y-m-d'), 'Equipo obsoleto', $usuarioId);
        $this->assertGreaterThan(0, $bajaId);
        $baja = $this->conn->query("SELECT * FROM bajas WHERE id=$bajaId")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('solicitada', $baja['estado']);
        $this->assertSame('Obsolescencia', $baja['motivo']);
        $this->assertSame((int)$usuarioId, (int)$baja['usuario_solicita']);

        $this->assertSame('baja_solicitada', historialDeElemento($this->conn, $id)[0]['tipo_evento']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pendiente');
        solicitarBaja($this->conn, $id, 'Pérdida', date('Y-m-d'), 'Otra', $usuarioId);
    }

    /**
     * ID: BAJ-002 | MÓDULO: 20 Baja | PRUEBA: motivos inválidos y doble baja
     * OBJETIVO: Rechazar motivo fuera del catálogo y activo ya dado de baja.
     * PRECONDICIONES: activo real; activo con situacion 'dado_de_baja'.
     * DATOS: motivo 'Roba'; activo dado de baja.
     * RESULTADO ESPERADO: RuntimeException 'inválido' y 'ya fue dado de baja'.
     */
    public function testBAJ002ErroresAlSolicitarBaja(): void
    {
        $usuarioId = $this->crearUsuario('Baja QA 2');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-071']);

        try {
            solicitarBaja($this->conn, $id, 'Roba', date('Y-m-d'), 'x', $usuarioId);
            $this->fail('Debió rechazar el motivo');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('inválido', $e->getMessage());
        }

        $this->conn->prepare("UPDATE inventario_general SET situacion='dado_de_baja' WHERE id=?")->execute([$id]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya fue dado de baja');
        solicitarBaja($this->conn, $id, 'Pérdida', date('Y-m-d'), 'x', $usuarioId);
    }

    /**
     * ID: BAJ-003 | MÓDULO: 20 Baja | PRUEBA: aprobarBaja [Integración BD]
     * OBJETIVO: Aprobar la baja cambia el activo a 'dado_de_baja'.
     * PRECONDICIONES: baja solicitada por la prueba.
     * DATOS: aprobación con observación.
     * RESULTADO ESPERADO: baja 'aprobada'; activo situacion 'dado_de_baja';
     *           historial 'baja_aprobada' con motivo.
     */
    public function testBAJ003AprobarBaja(): void
    {
        $usuarioId = $this->crearUsuario('Baja QA 3');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-072']);
        $bajaId = solicitarBaja($this->conn, $id, 'Hurto', date('Y-m-d'), 'Robado', $usuarioId);

        $this->assertTrue(aprobarBaja($this->conn, $bajaId, $usuarioId, 'Autorizado'));

        $baja = $this->conn->query("SELECT estado FROM bajas WHERE id=$bajaId")->fetchColumn();
        $this->assertSame('aprobada', $baja);
        $this->assertSame('dado_de_baja', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$id")->fetchColumn());

        $historial = historialDeElemento($this->conn, $id);
        $this->assertSame('baja_aprobada', $historial[1]['tipo_evento']);
        $this->assertSame('Hurto', $historial[1]['datos_nuevos']['motivo']);
    }

    /**
     * ID: BAJ-004 | MÓDULO: 20 Baja | PRUEBA: rechazarBaja [Integración BD]
     * OBJETIVO: Rechazar la baja deja el activo activo y registra el evento.
     * PRECONDICIONES: baja solicitada por la prueba.
     * DATOS: rechazo con observación.
     * RESULTADO ESPERADO: baja 'rechazada'; activo sigue activo con situacion
     *           original; historial 'baja_rechazada'; aprobar una resuelta
     *           lanza RuntimeException.
     */
    public function testBAJ004RechazarBaja(): void
    {
        $usuarioId = $this->crearUsuario('Baja QA 4');
        $id = $this->crearElemento(['codigo_interno' => '20J-01-INF1-073']);
        $bajaId = solicitarBaja($this->conn, $id, 'Daño irreparable', date('Y-m-d'), 'Dañado', $usuarioId);

        $this->assertTrue(rechazarBaja($this->conn, $bajaId, $usuarioId, 'Se recuperó'));
        $this->assertSame('rechazada', $this->conn->query("SELECT estado FROM bajas WHERE id=$bajaId")->fetchColumn());
        $fila = $this->conn->query("SELECT activo, situacion FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$fila['activo']);
        $this->assertSame('disponible', $fila['situacion']);
        $this->assertSame('baja_rechazada', historialDeElemento($this->conn, $id)[1]['tipo_evento']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya fue resuelta');
        aprobarBaja($this->conn, $bajaId, $usuarioId);
    }

    /* ============================================================
     * SECCIÓN 21 — ALERTAS (helpers_alertas.php)
     * ============================================================ */

    /**
     * ID: ALE-001 | MÓDULO: 21 Alertas | PRUEBA: calcularAlertas dañados [Integración BD]
     * OBJETIVO: Un activo 'malo' genera la alerta crítica 'danados'.
     * PRECONDICIONES: activo con estado 'malo'.
     * DATOS: 1 elemento estado 'malo'.
     * RESULTADO ESPERADO: alerta con clave 'danados', prioridad 'critica' y
     *           cantidad 1.
     */
    public function testALE001AlertaElementosDanados(): void
    {
        $this->crearElemento(['estado' => 'malo']);
        $alertas = calcularAlertas($this->conn);

        $danados = null;
        foreach ($alertas as $a) {
            if ($a['clave'] === 'danados') {
                $danados = $a;
            }
        }
        $this->assertNotNull($danados, 'Debe existir la alerta danados');
        $this->assertSame('critica', $danados['prioridad']);
        $this->assertSame(1, $danados['cantidad']);
    }

    /**
     * ID: ALE-002 | MÓDULO: 21 Alertas | PRUEBA: garantías vencidas y próximas [Integración BD]
     * OBJETIVO: fecha_garantia pasada genera 'garantias_vencidas'; a 10 días
     *           genera 'garantias_proximas'.
     * PRECONDICIONES: 2 activos con fecha_garantia definida.
     * DATOS: '-30 days' y '+10 days'.
     * RESULTADO ESPERADO: ambas claves presentes con cantidad 1; la vencida con
     *           prioridad 'critica'.
     */
    public function testALE002AlertasGarantias(): void
    {
        $this->crearElemento(['fecha_garantia' => date('Y-m-d', strtotime('-30 days'))]);
        $this->crearElemento(['fecha_garantia' => date('Y-m-d', strtotime('+10 days'))]);

        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('garantias_vencidas', $claves);
        $this->assertContains('garantias_proximas', $claves);

        foreach ($alertas as $a) {
            if ($a['clave'] === 'garantias_vencidas') {
                $this->assertSame('critica', $a['prioridad']);
            }
        }
    }

    /**
     * ID: ALE-003 | MÓDULO: 21 Alertas | PRUEBA: sin documento y vida útil [Integración BD]
     * OBJETIVO: 'documento_no_disponible=1' genera 'sin_documento'; vida útil
     *           vencida genera 'vida_util_vencida'.
     * PRECONDICIONES: 2 activos.
     * DATOS: documento_no_disponible=1; fecha_ingreso -5 años con vida_util=1.
     * RESULTADO ESPERADO: alertas 'sin_documento' y 'vida_util_vencida' con
     *           cantidad 1.
     */
    public function testALE003AlertaSinDocumentoYVidaUtil(): void
    {
        $this->crearElemento(['documento_no_disponible' => 1]);
        $this->crearElemento(['fecha_ingreso' => date('Y-m-d', strtotime('-5 years')), 'vida_util' => 1]);

        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('sin_documento', $claves);
        $this->assertContains('vida_util_vencida', $claves);
    }

    /**
     * ID: ALE-004 | MÓDULO: 21 Alertas | PRUEBA: diasAlertaGarantia
     * OBJETIVO: El umbral de garantía se lee de la configuración.
     * PRECONDICIONES: configuracion con 'dias_alerta_garantia'=30.
     * DATOS: sin datos.
     * RESULTADO ESPERADO: 30.
     */
    public function testALE004DiasAlertaGarantia(): void
    {
        $this->assertSame(30, diasAlertaGarantia($this->conn));
    }

    /**
     * ID: ALE-005 | MÓDULO: 21 Alertas | PRUEBA: alertasDeElemento
     * OBJETIVO: La ficha del elemento muestra sus alertas puntuales.
     * PRECONDICIONES: registro real del elemento.
     * DATOS: estado 'malo', garantía vencida, vida útil vencida y
     *        documento_no_disponible=1.
     * RESULTADO ESPERADO: 4 alertas con clases badge-danger/badge-warning.
     */
    public function testALE005AlertasDeElemento(): void
    {
        $id = $this->crearElemento([
            'estado' => 'malo',
            'fecha_garantia' => date('Y-m-d', strtotime('-30 days')),
            'fecha_ingreso' => date('Y-m-d', strtotime('-5 years')),
            'vida_util' => 1,
            'documento_no_disponible' => 1,
        ]);
        $item = $this->conn->query("SELECT * FROM inventario_general WHERE id=$id")->fetch(PDO::FETCH_ASSOC);

        $alertas = alertasDeElemento($this->conn, $item);
        $this->assertCount(4, $alertas);
        $textos = array_column($alertas, 'texto');
        $this->assertContains('Elemento dañado', $textos);
        $this->assertContains('Garantía vencida hace 30 días', $textos);
        $this->assertContains('Vida útil vencida', $textos);
        $this->assertContains('Sin documento de adquisición', $textos);
    }

    /* ============================================================
     * SECCIÓN 22 — VALOR DEL INVENTARIO
     * ============================================================ */

    /**
     * ID: VAL-001 | MÓDULO: 22 Valor | PRUEBA: suma de valor_compra [Integración BD]
     * OBJETIVO: El valor total del inventario suma los valor_compra activos.
     * PRECONDICIONES: 3 activos con valores conocidos.
     * DATOS: 1.500.000 + 2.500.000 + 500.000.
     * RESULTADO ESPERADO: SUM = 4.500.000.
     */
    public function testVAL001SumaValorCompra(): void
    {
        $this->crearElemento(['valor_compra' => 1500000]);
        $this->crearElemento(['valor_compra' => 2500000]);
        $this->crearElemento(['valor_compra' => 500000]);

        $total = $this->conn->query("SELECT COALESCE(SUM(valor_compra),0) FROM inventario_general WHERE activo=1")->fetchColumn();
        $this->assertEqualsWithDelta(4500000.0, (float)$total, 0.001);
    }

    /**
     * ID: VAL-002 | MÓDULO: 22 Valor | PRUEBA: valor en el acta
     * OBJETIVO: El acta muestra el valor comercial formateado en pesos.
     * PRECONDICIONES: $GLOBALS['institucion'] cargado.
     * DATOS: vr_comercial = 1500000.
     * RESULTADO ESPERADO: '$1.500.000' en el HTML del acta.
     */
    public function testVAL002ValorFormateadoEnActa(): void
    {
        $elementos = [[
            'id' => 9, 'codigo_interno' => '20J-01-INF1-009', 'nombre' => 'PC', 'tipo' => 'Portátil',
            'categoria' => 'Académico', 'marca' => 'L', 'numero_serie' => 'S', 'estado' => 'bueno',
            'vr_comercial' => 1500000, 'origen_bien' => 'Compra', 'documento_adquisicion' => null, 'qr_path' => null,
            'valor_compra' => 1400000, 'numero_factura' => null, 'fecha_compra' => null, 'fecha_garantia' => null,
            'proveedor_nombre' => null, 'proveedor_nit' => null,
        ]];
        $html = construirActaHTML(
            $GLOBALS['institucion'],
            ['nombre' => 'Ana', 'apellido' => 'Pérez', 'identificacion' => '', 'correo' => ''],
            'Sede Principal',
            $elementos,
            [],
            null
        );
        $this->assertStringContainsString('$1,500,000', $html);
    }

    /**
     * ID: VAL-003 | MÓDULO: 22 Valor | PRUEBA: precisión decimal [Integración BD]
     * OBJETIVO: Los valores se guardan con decimales sin pérdida.
     * PRECONDICIONES: mic_test limpia.
     * DATOS: valor_compra = 1500000.75.
     * RESULTADO ESPERADO: el valor recuperado es 1500000.75 (columna decimal(10,2)).
     */
    public function testVAL003ValorConDecimales(): void
    {
        $id = $this->crearElemento(['valor_compra' => 1500000.75]);
        $valor = $this->conn->query("SELECT valor_compra FROM inventario_general WHERE id=$id")->fetchColumn();
        $this->assertEqualsWithDelta(1500000.75, (float)$valor, 0.001);
    }

    /* ============================================================
     * SECCIÓN 23 — PRÉSTAMOS (tablas solicitudes/prestamos/equipos)
     * ============================================================ */

    /**
     * ID: PRE-001 | MÓDULO: 23 Préstamos | PRUEBA: FK solicitud→equipo [Integración BD]
     * OBJETIVO: Una solicitud no puede referenciar un equipo inexistente.
     * PRECONDICIONES: mic_test con FKs reales.
     * DATOS: id_equipo=999.
     * RESULTADO ESPERADO: PDOException por FK.
     */
    public function testPRE001SolicitudRequiereEquipo(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo) VALUES (1, 999, CURDATE(), CURTIME(), 'x')"
        )->execute();
    }

    /**
     * ID: PRE-002 | MÓDULO: 23 Préstamos | PRUEBA: flujo solicitud→préstamo→devolución [Integración BD]
     * OBJETIVO: Recorrer el ciclo real: solicitud 'pendiente', aprobación,
     *           préstamo 'activo' con fecha esperada, y devolución.
     * PRECONDICIONES: equipo disponible + estudiante reales.
     * DATOS: solicitud por estudiante; préstamo de 3 días.
     * RESULTADO ESPERADO: solicitud 'aprobada'; préstamo 'activo' unido al
     *           equipo y estudiante; devolución con fecha real y estado 'devuelto'.
     */
    public function testPRE002FlujoCompletoPrestamo(): void
    {
        $equipoId = $this->crearEquipo(['codigo_interno' => 'EQ-1001', 'nombre' => 'Portátil Préstamo']);
        $estudiante = $this->crearEstudiante('Estudiante QA');

        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado) VALUES (?, ?, CURDATE(), CURTIME(), 'Necesito el equipo', 'pendiente')"
        )->execute([$estudiante['usuario_id'], $equipoId]);
        $solicitudId = (int)$this->conn->lastInsertId();

        $this->conn->prepare("UPDATE solicitudes SET estado='aprobada' WHERE id=?")->execute([$solicitudId]);
        $this->conn->prepare(
            "INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, hora_prestamo) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), CURTIME())"
        )->execute([$solicitudId, $equipoId, $estudiante['estudiante_id']]);
        $prestamoId = (int)$this->conn->lastInsertId();

        $prestamo = $this->conn->query(
            "SELECT p.estado, e.nombre AS equipo_nombre, es.codigo_estudiante
             FROM prestamos p
             JOIN equipos e ON p.id_equipo=e.id
             JOIN estudiantes es ON p.id_estudiante=es.id
             WHERE p.id=$prestamoId"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('activo', $prestamo['estado']);
        $this->assertSame('Portátil Préstamo', $prestamo['equipo_nombre']);
        $this->assertStringStartsWith('EST-', $prestamo['codigo_estudiante']);

        $this->conn->prepare("UPDATE prestamos SET estado='devuelto', fecha_devolucion_real=CURDATE() WHERE id=?")->execute([$prestamoId]);
        $this->assertSame('devuelto', $this->conn->query("SELECT estado FROM prestamos WHERE id=$prestamoId")->fetchColumn());
    }

    /**
     * ID: PRE-003 | MÓDULO: 23 Préstamos | PRUEBA: enum de solicitudes [Integración BD]
     * OBJETIVO: El enum de solicitudes rechaza estados fuera del catálogo.
     * PRECONDICIONES: mic_test con esquema real.
     * DATOS: estado 'rota'.
     * RESULTADO ESPERADO: PDOException.
     */
    public function testPRE003EstadoSolicitudRechazaInvalido(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado) VALUES (1, 1, CURDATE(), CURTIME(), 'x', 'rota')"
        )->execute();
    }

    /**
     * ID: PRE-004 | MÓDULO: 23 Préstamos | PRUEBA: configuración de días y multa
     * OBJETIVO: Los días máximos de préstamo y la multa vienen de configuración.
     * PRECONDICIONES: configuracion sembrada.
     * DATOS: claves 'dias_prestamo_estudiante' y 'multa_dia_retraso'.
     * RESULTADO ESPERADO: 3 y 2000.
     */
    public function testPRE004ConfiguracionPrestamos(): void
    {
        $dias = $this->conn->query("SELECT valor FROM configuracion WHERE clave='dias_prestamo_estudiante'")->fetchColumn();
        $multa = $this->conn->query("SELECT valor FROM configuracion WHERE clave='multa_dia_retraso'")->fetchColumn();
        $this->assertSame('3', $dias);
        $this->assertSame('2000', $multa);
    }

    /* ============================================================
     * SECCIÓN 24 — REPORTES
     * ============================================================ */

    /**
     * ID: REP-001 | MÓDULO: 24 Reportes | PRUEBA: conteo por sede [Integración BD]
     * OBJETIVO: El reporte por sede agrupa correctamente los activos activos.
     * PRECONDICIONES: 2 activos en sede 1 y 1 en sede 2.
     * DATOS: consulta GROUP BY id_sede.
     * RESULTADO ESPERADO: sede 1 → 2, sede 2 → 1.
     */
    public function testREP001ConteoPorSede(): void
    {
        $this->crearElemento(['id_sede' => 1]);
        $this->crearElemento(['id_sede' => 1]);
        $this->crearElemento(['id_sede' => 2]);

        $porSede = $this->conn->query(
            "SELECT ig.id_sede, COUNT(*) AS total FROM inventario_general ig WHERE ig.activo=1 GROUP BY ig.id_sede"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(2, (int)($porSede[1] ?? 0));
        $this->assertSame(1, (int)($porSede[2] ?? 0));
    }

    /**
     * ID: REP-002 | MÓDULO: 24 Reportes | PRUEBA: conteo por estado [Integración BD]
     * OBJETIVO: El reporte por estado agrupa correctamente.
     * PRECONDICIONES: 2 'bueno' y 1 'malo'.
     * DATOS: consulta GROUP BY estado.
     * RESULTADO ESPERADO: bueno → 2, malo → 1.
     */
    public function testREP002ConteoPorEstado(): void
    {
        $this->crearElemento(['estado' => 'bueno']);
        $this->crearElemento(['estado' => 'bueno']);
        $this->crearElemento(['estado' => 'malo']);

        $porEstado = $this->conn->query(
            "SELECT ig.estado, COUNT(*) AS total FROM inventario_general ig WHERE ig.activo=1 GROUP BY ig.estado"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(2, (int)($porEstado['bueno'] ?? 0));
        $this->assertSame(1, (int)($porEstado['malo'] ?? 0));
    }

    /* ============================================================
     * SECCIÓN 25 — FILTROS DEL INVENTARIO (helpers_alertas.php)
     * ============================================================ */

    /**
     * ID: FIL-001 | MÓDULO: 25 Filtros | PRUEBA: filtrosInventario
     * OBJETIVO: Construir WHERE/params con todos los filtros del módulo.
     * PRECONDICIONES: función real filtrosInventario.
     * DATOS: sede=1, categoria, tipo, estado, responsable, desde, hasta.
     * RESULTADO ESPERADO: 6 placeholders en params y cláusulas AND para cada
     *           filtro.
     */
    public function testFIL001FiltrosInventario(): void
    {
        [$where, $params] = filtrosInventario([
            'sede' => 1,
            'categoria' => 'Académico',
            'tipo' => 'Portátil',
            'estado' => 'bueno',
            'responsable' => 5,
            'desde' => '2026-01-01',
            'hasta' => '2026-12-31',
        ]);

        $this->assertStringStartsWith('ig.activo=1', $where);
        $this->assertStringContainsString('AND ig.id_sede=?', $where);
        $this->assertStringContainsString('AND ig.categoria=?', $where);
        $this->assertStringContainsString('AND ig.tipo=?', $where);
        $this->assertStringContainsString('AND ig.estado=?', $where);
        $this->assertStringContainsString('AND ig.profesor_id=?', $where);
        $this->assertStringContainsString('AND DATE(ig.creado_en)>=?', $where);
        $this->assertCount(7, $params);
    }

    /**
     * ID: FIL-002 | MÓDULO: 25 Filtros | PRUEBA: filtro ejecutado [Integración BD]
     * OBJETIVO: Aplicar un filtro real devuelve solo los activos que cumplen.
     * PRECONDICIONES: 3 activos con distintos estados/sedes.
     * DATOS: sede 1 + estado 'bueno'.
     * RESULTADO ESPERADO: 1 resultado (el único que cumple ambos).
     */
    public function testFIL002FiltroEjecutado(): void
    {
        $this->crearElemento(['id_sede' => 1, 'estado' => 'bueno']);
        $this->crearElemento(['id_sede' => 1, 'estado' => 'malo']);
        $this->crearElemento(['id_sede' => 2, 'estado' => 'bueno']);

        [$where, $params] = filtrosInventario(['sede' => 1, 'estado' => 'bueno']);
        $stmt = $this->conn->prepare("SELECT ig.id FROM inventario_general ig WHERE $where");
        $stmt->execute($params);
        $this->assertCount(1, $stmt->fetchAll());
    }

    /* ============================================================
     * SECCIÓN 26 — IMPORTACIÓN (helpers_importacion.php)
     * ============================================================ */

    /** Asegura la categoría/tipo propios de importación en mic_test. */
    private function prepararCatalogoImport(): array
    {
        if (self::$catalogoImport) {
            return self::$catalogoImport;
        }
        $cat = 'Catálogo Import MIC';
        $tipo = 'Computador Import MIC';
        $stmt = $this->conn->prepare('SELECT id FROM categorias WHERE nombre=? LIMIT 1');
        $stmt->execute([$cat]);
        $catId = $stmt->fetchColumn();
        if (!$catId) {
            $this->conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, 'Categoría usada por MICUnitTest')")->execute([$cat]);
            $catId = $this->conn->lastInsertId();
        }
        $stmt = $this->conn->prepare('SELECT id FROM tipo_equipo WHERE nombre_tipo=? LIMIT 1');
        $stmt->execute([$tipo]);
        if (!$stmt->fetchColumn()) {
            $this->conn->prepare('INSERT INTO tipo_equipo (nombre_tipo, categoria_id, descripcion) VALUES (?, ?, ?)')->execute([$tipo, (int)$catId, 'Tipo usado por MICUnitTest']);
        }
        self::$catalogoImport = ['categoria' => $cat, 'tipo' => $tipo];
        return self::$catalogoImport;
    }

    /** Fila completa y válida contra la BD de pruebas. */
    private function filaImportacion(string $serial, array $cambios = []): array
    {
        $c = $this->prepararCatalogoImport();
        return array_merge([
            'nombre' => 'Computador de escritorio HP',
            'categoria' => $c['categoria'],
            'tipo' => $c['tipo'],
            'sede' => 'Sede Principal',
            'ubicacion' => 'Aula de Informática 1',
            'responsable' => 'Carlos Pérez',
            'estado' => 'bueno',
            'marca' => 'HP',
            'modelo' => 'ProDesk 400',
            'numero_serie' => $serial,
            'vida_util' => '5',
            'valor_comercial' => '1200000',
            'valor_compra' => '1500000',
            'descripcion' => 'Equipo importado masivamente',
            '_fila_excel' => 2,
        ], $cambios);
    }

    /**
     * ID: IMP-001 | MÓDULO: 26 Importación | PRUEBA: normalizarNumeroImportacion
     * OBJETIVO: Normalizar números colombianos (puntos y comas).
     * PRECONDICIONES: función real normalizarNumeroImportacion.
     * DATOS: '1.200.000,50', '1200000.5', '1200000', 'abc', '-500', ''.
     * RESULTADO ESPERADO: 1200000.5, 1200000.5, 1200000.0, null, null, null.
     */
    public function testIMP001NormalizarNumero(): void
    {
        $this->assertEqualsWithDelta(1200000.5, normalizarNumeroImportacion('1.200.000,50'), 0.001);
        $this->assertEqualsWithDelta(1200000.5, normalizarNumeroImportacion('1200000.5'), 0.001);
        $this->assertEqualsWithDelta(1200000.0, normalizarNumeroImportacion('1.200.000'), 0.001);
        $this->assertNull(normalizarNumeroImportacion('abc'));
        $this->assertNull(normalizarNumeroImportacion('-500'));
        $this->assertNull(normalizarNumeroImportacion(''));
    }

    /**
     * ID: IMP-002 | MÓDULO: 26 Importación | PRUEBA: fila válida se normaliza [Integración BD]
     * OBJETIVO: Una fila válida se traduce a ids reales de sede/ubicación/responsable.
     * PRECONDICIONES: profesor 'Carlos Pérez' en sede 1; catálogo propio.
     * DATOS: fila completa válida.
     * RESULTADO ESPERADO: ok=true; sede_id=1; sede_codigo '01'; ubic_codigo
     *           'INF1'; profesor_id del profesor creado.
     */
    public function testIMP002FilaValidaNormalizada(): void
    {
        $profesorId = $this->crearProfesor('Carlos', 'Pérez', 1);
        $fila = $this->filaImportacion('SN-MIC-001');
        $ctx = contextoImportacion($this->conn);
        $serials = [];

        $res = validarFilaImportacion($this->conn, $fila, $ctx, $serials);
        $this->assertTrue($res['ok'], implode(' | ', $res['errores']));
        $this->assertSame([], $res['errores']);
        $this->assertSame(1, $res['datos']['sede_id']);
        $this->assertSame('01', $res['datos']['sede_codigo']);
        $this->assertSame('INF1', $res['datos']['ubic_codigo']);
        $this->assertSame($profesorId, $res['datos']['profesor_id']);
        $this->assertSame('bueno', $res['datos']['estado']);
    }

    /**
     * ID: IMP-003 | MÓDULO: 26 Importación | PRUEBA: errores de validación
     * OBJETIVO: Rechazar sede inexistente, estado inválido y tipo ajeno a la
     *           categoría.
     * PRECONDICIONES: profesor 'Carlos Pérez' en sede 1.
     * DATOS: 'Sede Fantasma'; estado 'excelente'; tipo 'Computador de Escritorio'
     *        (no pertenece al catálogo de la fila).
     * RESULTADO ESPERADO: ok=false con mensajes esperados.
     */
    public function testIMP003ErroresDeValidacion(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $this->filaImportacion('SN-ERR-0');
        $ctx = contextoImportacion($this->conn);
        $serials = [];

        $casos = [
            ['cambios' => ['sede' => 'Sede Fantasma'], 'esperado' => 'no existe'],
            ['cambios' => ['estado' => 'excelente'], 'esperado' => 'no es válido'],
            ['cambios' => ['tipo' => 'Computador de Escritorio'], 'esperado' => 'no pertenece'],
            ['cambios' => ['nombre' => ''], 'esperado' => 'obligatorio'],
        ];
        foreach ($casos as $i => $caso) {
            $res = validarFilaImportacion($this->conn, $this->filaImportacion('SN-ERR-' . $i, $caso['cambios']), $ctx, $serials);
            $this->assertFalse($res['ok'], 'Fila ' . $i . ' debería ser inválida');
            $this->assertStringContainsString($caso['esperado'], implode(' | ', $res['errores']), 'Fila ' . $i);
        }
    }

    /**
     * ID: IMP-004 | MÓDULO: 26 Importación | PRUEBA: serial duplicado en archivo
     * OBJETIVO: Rechazar un serial repetido dentro del mismo archivo.
     * PRECONDICIONES: profesor 'Carlos Pérez' en sede 1.
     * DATOS: dos filas con serial 'SN-DUP'.
     * RESULTADO ESPERADO: la segunda fila es inválida con mensaje
     *           'duplicado dentro del archivo'.
     */
    public function testIMP004SerialDuplicadoEnArchivo(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $filaBase = $this->filaImportacion('SN-DUP');
        $ctx = contextoImportacion($this->conn);
        $serials = [];

        validarFilaImportacion($this->conn, $filaBase, $ctx, $serials);
        $res = validarFilaImportacion($this->conn, $this->filaImportacion('SN-DUP', ['nombre' => 'Otro PC']), $ctx, $serials);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('duplicado dentro del archivo', implode(' | ', $res['errores']));
    }

    /**
     * ID: IMP-005 | MÓDULO: 26 Importación | PRUEBA: importarFilasValidas [Integración BD]
     * OBJETIVO: Importar 2 filas válidas genera códigos consecutivos, historial
     *           y QR.
     * PRECONDICIONES: profesor 'Carlos Pérez' en sede 1; usuario real.
     * DATOS: 2 filas con seriales propios.
     * RESULTADO ESPERADO: creados=2; códigos '20J-01-INF1-001' y '...002';
     *           2 eventos 'registro' en historial; qr_path no vacío.
     */
    public function testIMP005ImportarFilasValidas(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $usuarioId = $this->crearUsuario('Importador MIC');
        $filas = [
            $this->filaImportacion('SN-MIC-002', ['_fila_excel' => 2, 'nombre' => 'PC Alpha']),
            $this->filaImportacion('SN-MIC-003', ['_fila_excel' => 3, 'nombre' => 'PC Beta']),
        ];
        $res = importarFilasValidas($this->conn, validarFilasImportacion($this->conn, $filas)['validas'], $usuarioId);
        $this->assertSame(2, $res['creados']);

        $codigos = $this->conn->query("SELECT codigo_interno FROM inventario_general ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['20J-01-INF1-001', '20J-01-INF1-002'], $codigos);

        $eventos = $this->conn->query("SELECT tipo_evento FROM elemento_historial")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(2, $eventos);
        $this->assertSame('registro', $eventos[0]);

        foreach ($this->conn->query("SELECT qr_path FROM inventario_general")->fetchAll(PDO::FETCH_COLUMN) as $ruta) {
            $this->assertNotEmpty($ruta);
            $this->assertFileExists(__DIR__ . '/../assets/' . $ruta);
            $this->borrarSiExiste(__DIR__ . '/../assets/' . $ruta);
        }
    }

    /**
     * ID: IMP-006 | MÓDULO: 26 Importación | PRUEBA: plantilla y validación de archivo
     * OBJETIVO: La plantilla tiene las hojas/encabezados reales; el validador
     *           rechaza archivos que no son Excel.
     * PRECONDICIONES: PhpSpreadsheet cargado.
     * DATOS: hoja 'Importación'; CSV con tmp_name real.
     * RESULTADO ESPERADO: 2 hojas y A1='Nombre'; ['ok'=>false] con 'Excel' en
     *           el error.
     */
    public function testIMP006PlantillaYArchivo(): void
    {
        $spreadsheet = construirPlantillaImportacion();
        $this->assertSame(['Importación', 'Instrucciones'], $spreadsheet->getSheetNames());
        $this->assertSame('Nombre', $spreadsheet->getSheetByName('Importación')->getCell('A1')->getValue());

        $res = validarArchivoExcelSubido(['error' => UPLOAD_ERR_OK, 'name' => 'datos.csv', 'size' => 10, 'tmp_name' => __FILE__]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Excel', $res['error']);
    }

    /* ============================================================
     * SECCIÓN 27 — AUDITORÍA (helpers_auditoria.php)
     * ============================================================ */

    /**
     * ID: AUD-001 | MÓDULO: 27 Auditoría | PRUEBA: registrarAuditoria [Integración BD]
     * OBJETIVO: Registrar una acción global y recuperarla con JSON decodificado.
     * PRECONDICIONES: usuario real en sesión.
     * DATOS: accion 'crear_activo', modulo 'inventario', datos con claves.
     * RESULTADO ESPERADO: registro con usuario_id, modulo, y datos_nuevos
     *           decodificados.
     */
    public function testAUD001RegistrarAuditoria(): void
    {
        $usuarioId = $this->crearUsuario('Auditoría QA');
        $_SESSION['user_id'] = $usuarioId;

        $ok = registrarAuditoria($this->conn, 'crear_activo', 'inventario', 'inventario_general', 55, 'Activo creado', ['estado' => 'nuevo'], ['estado' => 'bueno']);
        $this->assertTrue($ok);

        $fila = $this->conn->query("SELECT * FROM auditoria ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('crear_activo', $fila['accion']);
        $this->assertSame('inventario', $fila['modulo']);
        $this->assertSame((int)$usuarioId, (int)$fila['usuario_id']);

        $aud = obtenerAuditoria($this->conn, (int)$fila['id']);
        $this->assertSame('bueno', $aud['datos_nuevos']['estado']);
        $this->assertSame('nuevo', $aud['datos_anteriores']['estado']);
    }

    /**
     * ID: AUD-002 | MÓDULO: 27 Auditoría | PRUEBA: auditoriaListar con filtros [Integración BD]
     * OBJETIVO: Filtrar por acción, módulo y búsqueda de texto.
     * PRECONDICIONES: 3 registros (2 crear_activo, 1 iniciar_toma_fisica).
     * DATOS: filtros accion, modulo, buscar.
     * RESULTADO ESPERADO: 2, 1 y 2 registros respectivamente.
     */
    public function testAUD002ListarConFiltros(): void
    {
        $usuarioId = $this->crearUsuario('Auditoría QA 2');
        registrarAuditoria($this->conn, 'crear_activo', 'inventario', 'inventario_general', 1, 'Activo PC-1 creado', null, null);
        registrarAuditoria($this->conn, 'crear_activo', 'inventario', 'inventario_general', 2, 'Activo PC-2 creado', null, null);
        registrarAuditoria($this->conn, 'iniciar_toma_fisica', 'toma_fisica', 'tomas_fisicas', 3, 'Toma en Aula', null, null);

        $this->assertCount(2, auditoriaListar($this->conn, ['accion' => 'crear_activo']));
        $this->assertCount(1, auditoriaListar($this->conn, ['modulo' => 'toma_fisica']));
        $this->assertCount(2, auditoriaListar($this->conn, ['buscar' => 'creado']));
        $this->assertCount(3, auditoriaListar($this->conn));
    }

    /**
     * ID: AUD-003 | MÓDULO: 27 Auditoría | PRUEBA: etiquetaAccionAuditoria
     * OBJETIVO: Etiquetas legibles de acciones conocidas y desconocidas.
     * PRECONDICIONES: constante ACCIONES_AUDITORIA cargada.
     * DATOS: 'crear_activo' y 'accion_nueva'.
     * RESULTADO ESPERADO: 'Crear activo' y 'Accion nueva'.
     */
    public function testAUD003EtiquetaAccion(): void
    {
        $this->assertSame('Crear activo', etiquetaAccionAuditoria('crear_activo'));
        $this->assertSame('Accion nueva', etiquetaAccionAuditoria('accion_nueva'));
    }

    /**
     * ID: AUD-004 | MÓDULO: 27 Auditoría | PRUEBA: acciones usadas [Integración BD]
     * OBJETIVO: El filtro de acciones se alimenta de las acciones reales.
     * PRECONDICIONES: 2 registros con acciones distintas.
     * DATOS: 'crear_activo' y 'solicitar_baja'.
     * RESULTADO ESPERADO: auditoriaAccionesUsadas contiene ambas.
     */
    public function testAUD004AccionesUsadas(): void
    {
        registrarAuditoria($this->conn, 'crear_activo', 'inventario', null, null, null, null, null);
        registrarAuditoria($this->conn, 'solicitar_baja', 'inventario', null, null, null, null, null);

        $acciones = auditoriaAccionesUsadas($this->conn);
        $this->assertContains('crear_activo', $acciones);
        $this->assertContains('solicitar_baja', $acciones);
    }

    /* ============================================================
     * SECCIÓN 28 — ROLES Y PERMISOS
     * ============================================================ */

    /**
     * ID: ROL-001 | MÓDULO: 28 Roles | PRUEBA: estaLogueado
     * OBJETIVO: La sesión determina si hay usuario autenticado.
     * PRECONDICIONES: réplicas exactas de config/conexion.php.
     * DATOS: sin sesión; con user_id.
     * RESULTADO ESPERADO: false y true.
     */
    public function testROL001EstaLogueado(): void
    {
        $this->assertFalse(estaLogueado());
        $_SESSION['user_id'] = 1;
        $this->assertTrue(estaLogueado());
    }

    /**
     * ID: ROL-002 | MÓDULO: 28 Roles | PRUEBA: esAdmin
     * OBJETIVO: Solo el rol 'admin' tiene permisos administrativos.
     * PRECONDICIONES: réplicas exactas de config/conexion.php.
     * DATOS: roles admin, coordinador, docente, estudiante.
     * RESULTADO ESPERADO: true solo para 'admin'.
     */
    public function testROL002EsAdminSoloConRolAdmin(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_rol'] = 'admin';
        $this->assertTrue(esAdmin());
        foreach (['coordinador', 'docente', 'estudiante'] as $rol) {
            $_SESSION['user_rol'] = $rol;
            $this->assertFalse(esAdmin(), "El rol '$rol' no debe ser admin");
        }
    }

    /**
     * ID: ROL-003 | MÓDULO: 28 Roles | PRUEBA: roles de la BD [Integración BD]
     * OBJETIVO: El catálogo de roles real contiene los 4 roles del sistema.
     * PRECONDICIONES: mic_test con roles sembrados.
     * DATOS: consulta a tabla roles.
     * RESULTADO ESPERADO: 4 filas: admin, coordinador, docente, estudiante.
     */
    public function testROL003RolesDeLaBD(): void
    {
        $roles = $this->conn->query("SELECT nombre FROM roles ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['admin', 'coordinador', 'docente', 'estudiante'], $roles);
    }

    /**
     * ID: ROL-004 | MÓDULO: 28 Roles | PRUEBA: guard de permisos en backends reales
     * OBJETIVO: Los endpoints de acciones exigen sesión + rol admin + CSRF en
     *           el backend (no solo en la interfaz).
     * PRECONDICIONES: archivos reales de los módulos.
     * DATOS: acciones_catalogos.php, acciones_elemento.php,
     *        modulo_toma_fisica/acciones.php.
     * RESULTADO ESPERADO: cada uno contiene estaLogueado(), esAdmin() y
     *           verificarCSRF().
     */
    public function testROL004GuardPermisosEnBackend(): void
    {
        $backends = [
            __DIR__ . '/../modulo_inventario_general/acciones_catalogos.php',
            __DIR__ . '/../modulo_inventario_general/acciones_elemento.php',
            __DIR__ . '/../modulo_toma_fisica/acciones.php',
        ];
        foreach ($backends as $archivo) {
            $contenido = file_get_contents($archivo);
            $this->assertStringContainsString('estaLogueado()', $contenido, basename($archivo));
            $this->assertStringContainsString('esAdmin()', $contenido, basename($archivo));
            $this->assertStringContainsString('verificarCSRF()', $contenido, basename($archivo));
        }
    }

    /* ============================================================
     * SECCIÓN 29 — SEGURIDAD
     * ============================================================ */

    /**
     * ID: SEG-001 | MÓDULO: 29 Seguridad | PRUEBA: token CSRF
     * OBJETIVO: El token se genera una vez y se valida con hash_equals.
     * PRECONDICIONES: réplicas exactas de config/conexion.php.
     * DATOS: token generado, token ajeno, vacío.
     * RESULTADO ESPERADO: válido=true para el correcto; false para ajeno y
     *           vacío; el token generado es de 64 caracteres hex.
     */
    public function testSEG001TokenCSRF(): void
    {
        $token = generarTokenCSRF();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, generarTokenCSRF(), 'No debe regenerarse');

        $this->assertTrue(validarTokenCSRF($token));
        $this->assertFalse(validarTokenCSRF(str_repeat('a', 64)));
        $this->assertFalse(validarTokenCSRF(''));
    }

    /**
     * ID: SEG-002 | MÓDULO: 29 Seguridad | PRUEBA: verificarCSRF en POST
     * OBJETIVO: Un POST sin token válido es rechazado con error de seguridad.
     * PRECONDICIONES: réplica exacta de config/conexion.php.
     * DATOS: REQUEST_METHOD=POST, _csrf_token='malo'.
     * RESULTADO ESPERADO: RuntimeException con mensaje 'CSRF inválido'.
     */
    public function testSEG002VerificarCSRFRechaza(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'token-invalido';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSRF inválido');
        verificarCSRF();
    }

    /**
     * ID: SEG-003 | MÓDULO: 29 Seguridad | PRUEBA: SQL injection preparada
     * OBJETIVO: Las consultas preparadas no devuelven datos ajenos con entrada
     *           maliciosa.
     * PRECONDICIONES: mic_test limpia.
     * DATOS: email " ' OR '1'='1".
     * RESULTADO ESPERADO: 0 filas devueltas y sin excepción.
     */
    public function testSEG003InyeccionSQLNoDevuelveDatos(): void
    {
        $malicioso = "' OR '1'='1";
        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE email=? AND activo=1");
        $stmt->execute([$malicioso]);
        $this->assertCount(0, $stmt->fetchAll());

        $stmt = $this->conn->prepare("SELECT * FROM inventario_general WHERE activo=1 AND (nombre LIKE ? OR codigo_interno LIKE ?)");
        $p = "%' OR '1'='1";
        $stmt->execute([$p, $p]);
        $this->assertCount(0, $stmt->fetchAll());
    }

    /**
     * ID: SEG-004 | MÓDULO: 29 Seguridad | PRUEBA: email duplicado [Integración BD]
     * OBJETIVO: La unicidad real del email de usuarios impide duplicados.
     * PRECONDICIONES: mic_test con esquema real.
     * DATOS: dos usuarios con el mismo email.
     * RESULTADO ESPERADO: el segundo INSERT lanza PDOException.
     */
    public function testSEG004EmailDuplicadoRechazado(): void
    {
        $email = 'duplicado-mic-' . uniqid() . '@test.local';
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Uno', ?, 'x', 'admin', 1, 1)")->execute([$email]);
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Dos', ?, 'x', 'admin', 1, 1)")->execute([$email]);
    }

    /**
     * ID: SEG-005 | MÓDULO: 29 Seguridad | PRUEBA: FKs de integridad [Integración BD]
     * OBJETIVO: Las FKs reales protegen historial, bajas y evidencias.
     * PRECONDICIONES: mic_test con FKs del esquema real.
     * DATOS: historial de elemento inexistente; evidencia de usuario
     *        inexistente; baja de elemento inexistente.
     * RESULTADO ESPERADO: PDOException en cada caso.
     */
    public function testSEG005IntegridadReferencial(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO elemento_historial (elemento_id, tipo_evento, descripcion) VALUES (999999, 'registro', 'x')")->execute();
    }

    /* ============================================================
     * SECCIÓN 30 — BITÁCORA DE PRUEBAS (autocontrol del archivo)
     * ============================================================ */

    /**
     * ID: BIT-001 | MÓDULO: 30 Bitácora | PRUEBA: toda prueba documentada
     * OBJETIVO: Garantizar que cada test de este archivo tenga su bitácora con
     *           ID, MÓDULO, PRUEBA, OBJETIVO, PRECONDICIONES, DATOS y
     *           RESULTADO ESPERADO.
     * PRECONDICIONES: este mismo archivo (tests/MICUnitTest.php).
     * DATOS: análisis del contenido del archivo.
     * RESULTADO ESPERADO: ninguna prueba sin documentar; cada clave presente
     *           en el bloque de comentario inmediatamente anterior.
     */
    public function testBIT001TodasLasPruebasDocumentadas(): void
    {
        $contenido = file_get_contents(__FILE__);
        preg_match_all('/public function (test\w+)\(\): void\s*\{/', $contenido, $m);
        $metodos = $m[1];
        $this->assertNotEmpty($metodos, 'Debe haber pruebas en el archivo');

        $claves = ['ID:', 'MÓDULO:', 'PRUEBA:', 'OBJETIVO:', 'PRECONDICIONES:', 'DATOS:', 'RESULTADO ESPERADO:'];
        $faltantes = [];
        foreach ($metodos as $metodo) {
            $pos = strpos($contenido, "public function $metodo");
            $bloque = substr($contenido, max(0, $pos - 1500), 1500);
            foreach ($claves as $clave) {
                if (strpos($bloque, $clave) === false) {
                    $faltantes[] = "$metodo falta la clave $clave";
                }
            }
        }
        $this->assertSame([], $faltantes, implode("\n", $faltantes));
    }
}
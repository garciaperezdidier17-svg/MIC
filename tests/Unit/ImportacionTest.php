<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de importación masiva de inventario desde Excel.
 * Funciones reales: validarArchivoExcelSubido, leerFilasExcel,
 * contextoImportacion, validarFilaImportacion, validarFilasImportacion,
 * importarFilasValidas, construirPlantillaImportacion, normalizarNumeroImportacion.
 *
 * Nota: los catálogos de categorías/tipos provienen de la BD (tipo_equipo),
 * por lo que estas pruebas crean su propia categoría y tipo de prueba que
 * persisten en mic_test (igual que las categorías de TomaFisicaIntegracionTest).
 */
class ImportacionTest extends TestCase
{
    private PDO $conn;
    private static array $catalogoPrueba = [];

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    /**
     * Asegura que exista la categoría/tipo propios de esta prueba en mic_test.
     */
    private function prepararCatalogo(): array
    {
        if (self::$catalogoPrueba) {
            return self::$catalogoPrueba;
        }
        $cat = 'Catálogo Import Prueba';
        $tipo = 'Computador Import Prueba';

        $stmt = $this->conn->prepare('SELECT id FROM categorias WHERE nombre=? LIMIT 1');
        $stmt->execute([$cat]);
        $catId = $stmt->fetchColumn();
        if (!$catId) {
            $this->conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, 'Categoría usada por ImportacionTest')")->execute([$cat]);
            $catId = $this->conn->lastInsertId();
        }
        $stmt = $this->conn->prepare('SELECT id FROM tipo_equipo WHERE nombre_tipo=? LIMIT 1');
        $stmt->execute([$tipo]);
        if (!$stmt->fetchColumn()) {
            $this->conn->prepare('INSERT INTO tipo_equipo (nombre_tipo, categoria_id, descripcion) VALUES (?, ?, ?)')->execute([$tipo, (int)$catId, 'Tipo usado por ImportacionTest']);
        }
        self::$catalogoPrueba = ['categoria' => $cat, 'tipo' => $tipo];
        return self::$catalogoPrueba;
    }

    private function crearProfesor(string $nombre, string $apellido, int $sedeId): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO profesores (nombre, apellido, identificacion, correo, sede_id, estado) VALUES (?, ?, ?, ?, ?, 'Activo')"
        );
        $stmt->execute([$nombre, $apellido, 'CC-' . random_int(100000, 999999), strtolower($nombre . $apellido) . '@test.edu.co', $sedeId]);
        return (int)$this->conn->lastInsertId();
    }

    private function crearUsuario(): int
    {
        $email = 'import' . uniqid() . '@test.local';
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Importador Prueba', ?, 'x', 'admin', 1, 1)"
        );
        $stmt->execute([$email]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Fila completa y válida contra la BD de pruebas.
     */
    private function filaValida(string $serial = 'SN-IMPORT-001', array $cambios = []): array
    {
        $c = $this->prepararCatalogo();
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

    public function testFilaValidaSeNormalizaConIds(): void
    {
        $profeId = $this->crearProfesor('Carlos', 'Pérez', 1);
        $ctx = contextoImportacion($this->conn);
        $serials = [];

        $res = validarFilaImportacion($this->conn, $this->filaValida(), $ctx, $serials);
        $this->assertTrue($res['ok'], implode(' | ', $res['errores']));
        $this->assertSame([], $res['errores']);
        $this->assertSame(1, $res['datos']['sede_id']);
        $this->assertSame('01', $res['datos']['sede_codigo']);
        $this->assertSame('INF1', $res['datos']['ubic_codigo']);
        $this->assertSame($profeId, $res['datos']['profesor_id']);
        $this->assertSame('bueno', $res['datos']['estado']);
    }

    public function testCategoriaVaciaSeInfiereDesdeTipo(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $ctx = contextoImportacion($this->conn);
        $serials = [];
        $res = validarFilaImportacion($this->conn, $this->filaValida('SN-X-1', ['categoria' => '']), $ctx, $serials);
        $this->assertTrue($res['ok'], implode(' | ', $res['errores']));
        $this->assertSame(self::$catalogoPrueba['categoria'], $res['datos']['categoria']);
    }

    public function testErroresDeValidacion(): void
    {
        $ctx = contextoImportacion($this->conn);
        $serials = [];

        $casos = [
            ['cambios' => ['nombre' => ''], 'esperado' => 'obligatorio'],
            ['cambios' => ['sede' => 'Sede Fantasma'], 'esperado' => 'no existe'],
            ['cambios' => ['categoria' => 'Categoría Fantasma'], 'esperado' => 'no existe en el catálogo'],
            ['cambios' => ['tipo' => 'Tipo Inexistente'], 'esperado' => 'no existe'],
            ['cambios' => ['tipo' => 'Computador de escritorio'], 'esperado' => 'no pertenece'],
            ['cambios' => ['ubicacion' => 'Aula de Informática'], 'esperado' => 'no pertenece a la sede'],
            ['cambios' => ['responsable' => 'Nadie Nadie'], 'esperado' => 'no existe o está inactivo'],
            ['cambios' => ['estado' => 'excelente'], 'esperado' => 'no es válido'],
            ['cambios' => ['vida_util' => 'abc'], 'esperado' => 'número válido'],
            ['cambios' => ['valor_comercial' => '-500'], 'esperado' => 'número válido'],
            ['cambios' => ['numero_serie' => str_repeat('X', 60)], 'esperado' => '50 caracteres'],
        ];

        foreach ($casos as $i => $caso) {
            $res = validarFilaImportacion($this->conn, $this->filaValida('SN-ERR-' . $i, $caso['cambios']), $ctx, $serials);
            $this->assertFalse($res['ok'], 'Fila ' . $i . ' debería ser inválida');
            $this->assertStringContainsString($caso['esperado'], implode(' | ', $res['errores']), 'Fila ' . $i);
        }
    }

    public function testResponsableDeOtraSedeRechazado(): void
    {
        $this->crearProfesor('Lucía', 'Gómez', 2);
        $ctx = contextoImportacion($this->conn);
        $serials = [];
        $res = validarFilaImportacion($this->conn, $this->filaValida('SN-RS-1', ['responsable' => 'Lucía Gómez']), $ctx, $serials);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('no pertenece a la sede', implode(' | ', $res['errores']));
    }

    public function testSerialDuplicadoEnBaseDatosRechazado(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $this->conn->prepare(
            "INSERT INTO inventario_general (nombre, tipo, estado, id_sede, codigo_ubicacion, numero_serie, activo) VALUES ('PC Viejo', 'Computador import prueba', 'bueno', 1, 'INF1', 'SN-EXISTENTE', 1)"
        )->execute();

        $ctx = contextoImportacion($this->conn);
        $serials = [];
        $res = validarFilaImportacion($this->conn, $this->filaValida('SN-EXISTENTE'), $ctx, $serials);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('ya existe en el inventario', implode(' | ', $res['errores']));
    }

    public function testSerialDuplicadoDentroDelArchivoRechazado(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $ctx = contextoImportacion($this->conn);
        $serials = [];
        validarFilaImportacion($this->conn, $this->filaValida('SN-DUP'), $ctx, $serials);
        $res = validarFilaImportacion($this->conn, $this->filaValida('SN-DUP', ['nombre' => 'Otro PC']), $ctx, $serials);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('duplicado dentro del archivo', implode(' | ', $res['errores']));
    }

    public function testValorConPuntosYComasSeNormaliza(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $ctx = contextoImportacion($this->conn);
        $serials = [];
        $res = validarFilaImportacion($this->conn, $this->filaValida('SN-NUM-1', [
            'valor_comercial' => '1.200.000,50',
            'valor_compra' => '1.500.000',
            'vida_util' => '10,4',
        ]), $ctx, $serials);
        $this->assertTrue($res['ok'], implode(' | ', $res['errores']));
        $this->assertEqualsWithDelta(1200000.5, (float)$res['datos']['valor_comercial'], 0.001);
        $this->assertEqualsWithDelta(1500000.0, (float)$res['datos']['valor_compra'], 0.001);
        $this->assertSame(10, $res['datos']['vida_util']);
    }

    public function testNormalizarNumeroImportacion(): void
    {
        $this->assertEqualsWithDelta(1200000.5, normalizarNumeroImportacion('1.200.000,50'), 0.001);
        $this->assertEqualsWithDelta(1200000.5, normalizarNumeroImportacion('1200000,5'), 0.001);
        $this->assertEqualsWithDelta(1200000.5, normalizarNumeroImportacion('1200000.5'), 0.001);
        $this->assertEqualsWithDelta(1200000.0, normalizarNumeroImportacion('1.200.000'), 0.001);
        $this->assertEqualsWithDelta(1200000.0, normalizarNumeroImportacion('1200000'), 0.001);
        $this->assertNull(normalizarNumeroImportacion('abc'));
        $this->assertNull(normalizarNumeroImportacion('-500'));
        $this->assertNull(normalizarNumeroImportacion(''));
    }

    public function testValidarFilasImportacionSeparaValidasEInvalidas(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $filas = [
            $this->filaValida('SN-MIX-1', ['_fila_excel' => 2]),
            $this->filaValida('SN-MIX-2', ['_fila_excel' => 3, 'sede' => 'Sede Inexistente']),
            $this->filaValida('SN-MIX-3', ['_fila_excel' => 4]),
        ];
        $resultado = validarFilasImportacion($this->conn, $filas);
        $this->assertCount(2, $resultado['validas']);
        $this->assertCount(1, $resultado['invalidas']);
        $this->assertSame(3, $resultado['invalidas'][0]['fila']['_fila_excel']);
    }

    public function testImportarFilasValidasGeneraCodigosConsecutivosQRYHistorial(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $usuarioId = $this->crearUsuario();
        $filas = [
            $this->filaValida('SN-IMP-001', ['_fila_excel' => 2, 'nombre' => 'PC Alpha']),
            $this->filaValida('SN-IMP-002', ['_fila_excel' => 3, 'nombre' => 'PC Beta']),
            $this->filaValida('SN-IMP-003', ['_fila_excel' => 4, 'nombre' => 'PC Gamma']),
        ];
        $res = importarFilasValidas($this->conn, validarFilasImportacion($this->conn, $filas)['validas'], $usuarioId);
        $this->assertSame(3, $res['creados']);
        $this->assertCount(3, $res['ids']);

        $codigos = $this->conn->query("SELECT codigo_interno FROM inventario_general ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['20J-01-INF1-001', '20J-01-INF1-002', '20J-01-INF1-003'], $codigos);

        $fila1 = $this->conn->query("SELECT estado, valor_compra FROM inventario_general ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('bueno', $fila1['estado']);
        $this->assertEqualsWithDelta(1500000.0, (float)$fila1['valor_compra'], 0.001);

        $historial = $this->conn->query("SELECT tipo_evento, descripcion FROM elemento_historial")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(3, $historial);
        $this->assertSame('registro', $historial[0]['tipo_evento']);
        $this->assertStringContainsString('importación masiva', $historial[0]['descripcion']);

        $qr = $this->conn->query("SELECT qr_path FROM inventario_general ORDER BY id LIMIT 1")->fetchColumn();
        $this->assertNotEmpty($qr);
        $this->assertFileExists(__DIR__ . '/../../assets/' . $qr);
        foreach ($this->conn->query("SELECT qr_path FROM inventario_general")->fetchAll(PDO::FETCH_COLUMN) as $ruta) {
            @unlink(__DIR__ . '/../../assets/' . $ruta);
        }
    }

    public function testImportarConFilaInvalidaEnMedioHaceRollbackTotal(): void
    {
        $this->crearProfesor('Carlos', 'Pérez', 1);
        $filas = validarFilasImportacion($this->conn, [
            $this->filaValida('SN-RB-001', ['_fila_excel' => 2]),
            $this->filaValida('SN-RB-002', ['_fila_excel' => 3]),
        ])['validas'];
        unset($filas[1]['tipo']);

        $this->expectException(Throwable::class);
        importarFilasValidas($this->conn, $filas, 1);

        $total = $this->conn->query("SELECT COUNT(*) FROM inventario_general")->fetchColumn();
        $this->assertSame(0, (int)$total);
    }

    public function testImportarFilasVaciasNoHaceNada(): void
    {
        $res = importarFilasValidas($this->conn, [], 1);
        $this->assertSame(0, $res['creados']);
        $this->assertSame([], $res['ids']);
    }

    public function testValidarArchivoExcelSubidoRechazaErrores(): void
    {
        $res = validarArchivoExcelSubido(null);
        $this->assertFalse($res['ok']);

        $res = validarArchivoExcelSubido(['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'a.xlsx', 'size' => 10, 'tmp_name' => '']);
        $this->assertFalse($res['ok']);

        $res = validarArchivoExcelSubido(['error' => UPLOAD_ERR_OK, 'name' => 'datos.csv', 'size' => 10, 'tmp_name' => __FILE__]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Excel', $res['error']);
    }

    public function testConstruirPlantillaContieneHojasEncabezadosYEjemplo(): void
    {
        $spreadsheet = construirPlantillaImportacion();
        $this->assertSame(['Importación', 'Instrucciones'], $spreadsheet->getSheetNames());

        $hoja = $spreadsheet->getSheetByName('Importación');
        $this->assertSame('Nombre', $hoja->getCell('A1')->getValue());
        $this->assertSame('Descripción', $hoja->getCell('N1')->getValue());
        $this->assertSame('Computador de escritorio HP', $hoja->getCell('A2')->getValue());

        $instrucciones = $spreadsheet->getSheetByName('Instrucciones');
        $this->assertSame('Instrucciones para importar inventario', $instrucciones->getCell('A1')->getValue());
        $this->assertStringContainsString('código interno', $instrucciones->getCell('A4')->getValue());
        $this->assertStringContainsString('2000 filas', $instrucciones->getCell('A13')->getValue());
    }

    public function testLeerFilasExcelReconstruyeFilasPorEncabezado(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'mic_imp_') . '.xlsx';
        $spreadsheet = construirPlantillaImportacion();
        (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($ruta);

        $filas = leerFilasExcel($ruta);
        @unlink($ruta);

        $this->assertCount(1, $filas);
        $this->assertSame('Computador de escritorio HP', $filas[0]['nombre']);
        $this->assertSame('Equipos de Cómputo', $filas[0]['categoria']);
        $this->assertSame('El Porvenir', $filas[0]['sede']);
        $this->assertSame('SN-IMPORT-001', $filas[0]['numero_serie']);
        $this->assertSame(2, $filas[0]['_fila_excel']);
    }

    public function testPlantillaGuardaYRecargaConIOFactory(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'mic_xls_') . '.xlsx';
        $spreadsheet = construirPlantillaImportacion();
        (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($ruta);

        $cargado = IOFactory::load($ruta);
        @unlink($ruta);
        $hoja = $cargado->getSheetByName('Importación');
        $this->assertNotNull($hoja);
        $this->assertSame('Nombre', $hoja->getCell('A1')->getValue());
    }
}
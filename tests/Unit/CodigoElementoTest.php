<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de generación de códigos internos y QR.
 * Funciones reales: obtenerCodigoUbicacion, generarCodigoElemento,
 * generarQR, urlBase, urlFichaElemento.
 */
class CodigoElementoTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function insertarElemento(string $codigoUbicacion): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM inventario_general WHERE codigo_ubicacion=?");
        $stmt->execute([$codigoUbicacion]);
        $consecutivo = (int)$stmt->fetchColumn() + 1;
        $codigo = sprintf('20J-01-%s-%03d', $codigoUbicacion, $consecutivo);
        $stmt = $this->conn->prepare(
            "INSERT INTO inventario_general (codigo_interno, nombre, tipo, estado, id_sede, codigo_ubicacion, activo) VALUES (?, 'PC', 'Computador de escritorio', 'bueno', 1, ?, 1)"
        );
        $stmt->execute([$codigo, $codigoUbicacion]);
        return (int)$this->conn->lastInsertId();
    }

    public function testCodigoSigueFormatoInstitucionSedeUbicacionConsecutivo(): void
    {
        $codigo = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF01');
        $this->assertMatchesRegularExpression('/^20J-01-INF01-\d{3}$/', $codigo);
        $this->assertSame('20J-01-INF01-001', $codigo);
    }

    public function testConsecutivoIncrementaConCadaElementoExistente(): void
    {
        $this->insertarElemento('INF01');
        $this->insertarElemento('INF01');
        $codigo = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF01');
        $this->assertSame('20J-01-INF01-003', $codigo);
    }

    public function testConsecutivoNoRepiteCodigoTrasDesactivarElementoIntermedio(): void
    {
        $codigos = ['20J-01-INF01-001', '20J-01-INF01-002', '20J-01-INF01-003'];
        foreach ($codigos as $codigo) {
            $stmt = $this->conn->prepare("INSERT INTO inventario_general (codigo_interno, nombre, tipo, estado, id_sede, codigo_ubicacion, activo) VALUES (?, 'PC', 'Computador', 'bueno', 1, 'INF01', 1)");
            $stmt->execute([$codigo]);
        }
        $this->conn->exec("UPDATE inventario_general SET activo=0 WHERE codigo_interno='20J-01-INF01-002'");

        $this->assertSame('20J-01-INF01-004', generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'INF01'));
    }

    public function testConsecutivoIndependientePorUbicacion(): void
    {
        $this->insertarElemento('INF01');
        $codigoOtro = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'BIB01');
        $this->assertSame('20J-01-BIB01-001', $codigoOtro);
    }

    public function testObtenerCodigoUbicacionSedePrincipal(): void
    {
        $this->assertSame('INF1', obtenerCodigoUbicacion('Sede Principal', 'Aula de Informática 1'));
        $this->assertSame('BIB', obtenerCodigoUbicacion('Sede Principal', 'Biblioteca'));
        $this->assertSame('COO', obtenerCodigoUbicacion('Sede Principal', 'Coordinación'));
    }

    public function testObtenerCodigoUbicacionSedeSecundaria(): void
    {
        $this->assertSame('INF1', obtenerCodigoUbicacion('El Porvenir', 'Aula de Informática'));
        $this->assertSame('', obtenerCodigoUbicacion('Sede Inexistente', 'Aula'));
    }

    public function testObtenerCodigoUbicacionInexistente(): void
    {
        $this->assertSame('', obtenerCodigoUbicacion('Sede Principal', 'Ubicación inventada'));
    }

    public function testUrlFichaElementoContieneCodigo(): void
    {
        $url = urlFichaElemento('20J-01-INF01-001');
        $this->assertStringContainsString('http://localhost/mic/', $url);
        $this->assertStringContainsString('ver_articulo.php?codigo=', $url);
        $this->assertStringContainsString(urlencode('20J-01-INF01-001'), $url);
    }

    public function testUrlBaseUsaUrlDeInstitucion(): void
    {
        $this->assertSame('http://localhost/mic/', urlBase());
    }

    public function testGenerarQRDevuelveRutaRelativaYArchivoReal(): void
    {
        $codigo = generarCodigoElemento($this->conn, '20J', 'Sede Principal', '01', 'COO');
        $ruta = generarQR($codigo, 999);
        $this->assertNotNull($ruta, 'El QR no se pudo generar (Endroid)');
        $this->assertSame('qr/qr_999.png', $ruta);
        $this->assertFileExists(__DIR__ . '/../../assets/' . $ruta);
        $contenido = file_get_contents(__DIR__ . '/../../assets/' . $ruta);
        $this->assertStringStartsWith("\x89PNG", $contenido);
        @unlink(__DIR__ . '/../../assets/' . $ruta);
    }
}

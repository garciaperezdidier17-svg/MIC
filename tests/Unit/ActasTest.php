<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de generación de actas (helpers_actas.php).
 * Funciones reales: construirActaHTML, ubicacionPerteneceSedeActa.
 */
class ActasTest extends TestCase
{
    private array $institucion;
    private array $profesor;
    private array $elementos;

    protected function setUp(): void
    {
        $GLOBALS['catalogosUbicaciones'] = require __DIR__ . '/../../config/ubicaciones.php';
        $GLOBALS['institucion'] = require __DIR__ . '/../../config/institucion.php';
        $this->institucion = $GLOBALS['institucion'];
        $this->profesor = ['nombre' => 'Juan', 'apellido' => 'Perez', 'identificacion' => '123456', 'correo' => 'juan@test.local'];
        $this->elementos = [
            [
                'id' => 1, 'codigo_interno' => '20J-01-INF1-001', 'nombre' => 'Computador 1',
                'tipo' => 'Computador de escritorio', 'categoria' => 'Académico', 'marca' => 'HP',
                'numero_serie' => 'SN-001', 'estado' => 'bueno', 'vr_comercial' => 1000000,
                'origen_bien' => 'Compra', 'proveedor_nombre' => 'Tecnologías S.A.',
                'proveedor_nit' => '900123456', 'numero_factura' => 'FAC-001',
                'fecha_compra' => '2025-01-15', 'valor_compra' => 1000000,
                'fecha_garantia' => '2026-01-15', 'documento_adquisicion' => 'documentos/doc.pdf',
            ],
            [
                'id' => 2, 'codigo_interno' => '20J-01-COO-001', 'nombre' => 'Impresora',
                'tipo' => 'Impresora', 'categoria' => 'Administrativo', 'marca' => 'Epson',
                'numero_serie' => 'SN-002', 'estado' => 'bueno', 'vr_comercial' => 500000,
                'origen_bien' => 'Donación', 'donante_nombre' => 'Fundación X',
                'fecha_donacion' => '2025-06-01', 'documento_adquisicion' => null,
            ],
        ];
    }

    public function testActaContieneEncabezadoDeLaInstitucionReal(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, ['Aula de Informática 1'], '');
        $this->assertStringContainsString($this->institucion['nombre'], $html);
        $this->assertStringContainsString('Código de la institución: ' . $this->institucion['codigo'], $html);
        $this->assertStringContainsString('Acta de Entrega y Responsabilidad de Bienes', $html);
    }

    public function testActaContieneDatosDelResponsable(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('Juan Perez', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('juan@test.local', $html);
    }

    public function testActaContieneAmbosElementosConCodigos(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('20J-01-INF1-001', $html);
        $this->assertStringContainsString('20J-01-COO-001', $html);
        $this->assertStringContainsString('Computador 1', $html);
        $this->assertStringContainsString('Impresora', $html);
        $this->assertStringContainsString('DATOS DE LOS ACTIVOS (2)', $html);
    }

    public function testActaMuestraValorMonetarioFormateado(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('$1,000,000', $html);
        $this->assertStringContainsString('$500,000', $html);
    }

    public function testActaContieneDatosDeCompra(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('Tecnologías S.A.', $html);
        $this->assertStringContainsString('900123456', $html);
        $this->assertStringContainsString('FAC-001', $html);
        $this->assertStringContainsString('15/01/2025', $html);
        $this->assertStringContainsString('15/01/2026', $html);
    }

    public function testActaContieneDatosDeDonacion(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('Fundación X', $html);
        $this->assertStringContainsString('01/06/2025', $html);
    }

    public function testActaEscapaCaracteresHTML(): void
    {
        $elementos = [$this->elementos[0]];
        $elementos[0]['nombre'] = '<script>alert(1)</script>';
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $elementos, [], '');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testActaContieneSeccionQR(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('CÓDIGOS QR DE LOS ACTIVOS', $html);
    }

    public function testActaContieneFirmas(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('Responsable', $html);
        $this->assertStringContainsString('Administrador del inventario', $html);
    }

    public function testActaSinLogoNoRompe(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('Sede Principal', $html);
    }

    public function testActaContieneUbicacionesUnicas(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, ['Bodega', 'Bodega', 'Archivo'], '');
        $this->assertStringContainsString('Bodega — Archivo', $html);
    }

    public function testActaSinUbicacionesMuestraNoRegistrada(): void
    {
        $html = construirActaHTML($this->institucion, $this->profesor, 'Sede Principal', $this->elementos, [], '');
        $this->assertStringContainsString('No registrada', $html);
    }

    public function testUbicacionPerteneceSedeActa(): void
    {
        $catalogos = $GLOBALS['catalogosUbicaciones'];
        $this->assertTrue(ubicacionPerteneceSedeActa($catalogos, 'Sede Principal', 'Rectoría'));
        $this->assertTrue(ubicacionPerteneceSedeActa($catalogos, 'Sede Principal', 'Cancha'));
        $this->assertTrue(ubicacionPerteneceSedeActa($catalogos, 'El Porvenir', 'Aula de Informática'));
        $this->assertFalse(ubicacionPerteneceSedeActa($catalogos, 'Sede Principal', 'Aula de Informática'));
        $this->assertTrue(ubicacionPerteneceSedeActa($catalogos, 'Sede Principal', ''));
    }
}

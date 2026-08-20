<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de validaciones (helpers_inventario.php y sesión/CSRF).
 * Funciones reales: origenValido, campoDocumentoDe, validarDocumentoSubido,
 * MAX_DOC_SIZE, validarTokenCSRF, generarTokenCSRF.
 */
class ValidacionesTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
        $this->tmpDir = sys_get_temp_dir() . '/mic_validaciones_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }
        $_SESSION = [];
    }

    public function testOrigenesValidosReales(): void
    {
        foreach (ORIGENES_VALIDOS as $origen) {
            $this->assertTrue(origenValido($origen), "Debe ser válido: $origen");
        }
    }

    public function testOrigenesInvalidos(): void
    {
        $this->assertFalse(origenValido('Comprado'));
        $this->assertFalse(origenValido(''));
        $this->assertFalse(origenValido('donación'));
        $this->assertFalse(origenValido('Roba'));
    }

    public function testCampoDocumentoDe(): void
    {
        $this->assertSame('documento_compra', campoDocumentoDe('Compra'));
        $this->assertSame('documento_donacion', campoDocumentoDe('Donación'));
        $this->assertSame('documento_transferencia', campoDocumentoDe('Transferencia'));
        $this->assertSame('documento_origen', campoDocumentoDe('Otro'));
        $this->assertNull(campoDocumentoDe('Invalido'));
    }

    public function testMaxDocSizeEsCincoMegabytes(): void
    {
        $this->assertSame(5 * 1024 * 1024, MAX_DOC_SIZE);
    }

    public function testValidarDocumentoPdfValido(): void
    {
        $tmp = "$this->tmpDir/factura.pdf";
        file_put_contents($tmp, "%PDF-1.4\nfake pdf content");
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
            'name' => 'factura.pdf', 'tmp_name' => $tmp,
        ]);
        $this->assertTrue($res['ok'], $res['error']);
        $this->assertSame('pdf', $res['ext']);
    }

    public function testValidarDocumentoJpegValido(): void
    {
        $tmp = "$this->tmpDir/foto.jpg";
        $img = imagecreatetruecolor(10, 10);
        imagejpeg($img, $tmp);
        imagedestroy($img);
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
            'name' => 'foto.jpg', 'tmp_name' => $tmp,
        ]);
        $this->assertTrue($res['ok'], $res['error']);
        $this->assertSame('jpg', $res['ext']);
    }

    public function testValidarDocumentoRechazaExtension(): void
    {
        $tmp = "$this->tmpDir/virus.exe";
        file_put_contents($tmp, 'MZ fake');
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
            'name' => 'virus.exe', 'tmp_name' => $tmp,
        ]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('PDF, JPG', $res['error']);
    }

    public function testValidarDocumentoRechazaTamanoMayorACincoMB(): void
    {
        $tmp = "$this->tmpDir/grande.pdf";
        file_put_contents($tmp, str_repeat('a', MAX_DOC_SIZE + 1));
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK, 'size' => MAX_DOC_SIZE + 1,
            'name' => 'grande.pdf', 'tmp_name' => $tmp,
        ]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('5 MB', $res['error']);
    }

    public function testValidarDocumentoRechazaMimeFalso(): void
    {
        $tmp = "$this->tmpDir/falso.pdf";
        file_put_contents($tmp, "esto no es un pdf real");
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
            'name' => 'falso.pdf', 'tmp_name' => $tmp,
        ]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('tipo de archivo', $res['error']);
    }

    public function testValidarDocumentoRechazaErrorDeSubida(): void
    {
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0,
            'name' => 'x.pdf', 'tmp_name' => '',
        ]);
        $this->assertFalse($res['ok']);
    }

    public function testValidarDocumentoRechazaArchivoVacio(): void
    {
        $res = validarDocumentoSubido([
            'error' => UPLOAD_ERR_OK, 'size' => 0,
            'name' => 'vacio.pdf', 'tmp_name' => '',
        ]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('vacío', $res['error']);
    }

    public function testTokenCSRFSeGeneraUnaSolaVez(): void
    {
        $t1 = generarTokenCSRF();
        $t2 = generarTokenCSRF();
        $this->assertSame($t1, $t2);
        $this->assertNotEmpty($t1);
    }

    public function testValidarTokenCSRF(): void
    {
        $token = generarTokenCSRF();
        $this->assertTrue(validarTokenCSRF($token));
        $this->assertFalse(validarTokenCSRF('token-invalido'));
        $this->assertFalse(validarTokenCSRF(''));
    }

    public function testCampoCSRFContieneToken(): void
    {
        $token = generarTokenCSRF();
        $html = campoCSRF();
        $this->assertStringContainsString('_csrf_token', $html);
        $this->assertStringContainsString($token, $html);
    }
}

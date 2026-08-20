<?php

use PHPUnit\Framework\TestCase;

/**
 * Documentación y archivos (helpers_inventario.php):
 * validarDocumentoSubido, guardarDocumento, eliminarArchivoDocumento.
 * Los archivos se crean y eliminan dentro de uploads/documentos.
 */
class DocumentacionIntegracionTest extends TestCase
{
    private string $tmpDir;
    private string $dirDocumentos;

    protected function setUp(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
        $this->tmpDir = sys_get_temp_dir() . '/mic_docs_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
        $this->dirDocumentos = __DIR__ . '/../../uploads/documentos';
        if (!is_dir($this->dirDocumentos)) {
            mkdir($this->dirDocumentos, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        foreach (glob($this->dirDocumentos . '/doc_99999_*') ?: [] as $f) {
            @unlink($f);
        }
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function pdfValido(string $nombre = 'factura.pdf'): array
    {
        $tmp = "$this->tmpDir/$nombre";
        file_put_contents($tmp, "%PDF-1.4\ncontenido de prueba");
        return ['error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'name' => $nombre, 'tmp_name' => $tmp];
    }

    public function testFlujoCompletoSubirYEliminarDocumento(): void
    {
        $archivo = $this->pdfValido();
        $validado = validarDocumentoSubido($archivo);
        $this->assertTrue($validado['ok']);

        $ruta = guardarDocumento($archivo, 99999);
        $this->assertNotNull($ruta);
        $this->assertStringStartsWith('documentos/', $ruta);
        $this->assertFileExists(__DIR__ . '/../../uploads/' . $ruta);

        $rutaCompleta = __DIR__ . '/../../uploads/' . $ruta;
        $contenido = file_get_contents($rutaCompleta);
        $this->assertStringContainsString('%PDF-1.4', $contenido);

        eliminarArchivoDocumento($ruta);
        $this->assertFileDoesNotExist($rutaCompleta);
    }

    public function testGuardarDocumentoGeneraNombreUnico(): void
    {
        $ruta1 = guardarDocumento($this->pdfValido('a.pdf'), 99999);
        $ruta2 = guardarDocumento($this->pdfValido('b.pdf'), 99999);
        $this->assertNotSame($ruta1, $ruta2);
        $this->assertFileExists(__DIR__ . '/../../uploads/' . $ruta1);
        $this->assertFileExists(__DIR__ . '/../../uploads/' . $ruta2);
    }

    public function testEliminarDocumentoInexistenteNoRompe(): void
    {
        eliminarArchivoDocumento('documentos/no_existe.pdf');
        $this->assertTrue(true);
    }

    public function testEliminarDocumentoVacioNoRompe(): void
    {
        eliminarArchivoDocumento('');
        $this->assertTrue(true);
    }

    public function testExtensionSeConservaEnArchivoGuardado(): void
    {
        $ruta = guardarDocumento($this->pdfValido('factura.pdf'), 99999);
        $this->assertStringEndsWith('.pdf', $ruta);
        $rutaPng = guardarDocumento([
            'error' => UPLOAD_ERR_OK, 'size' => 100, 'name' => 'foto.png', 'tmp_name' => '',
        ], 99999);
        $this->assertNull($rutaPng, 'Archivo sin tmp_name real no debe guardarse');
    }

    public function testDocumentoRechazadoNoSeGuarda(): void
    {
        $archivo = $this->pdfValido('malo.exe');
        $archivo['name'] = 'malo.exe';
        $validado = validarDocumentoSubido($archivo);
        $this->assertFalse($validado['ok']);
        $guardados = glob($this->dirDocumentos . '/doc_99999_*') ?: [];
        $this->assertCount(0, $guardados);
    }
}

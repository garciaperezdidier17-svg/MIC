<?php

use PHPUnit\Framework\TestCase;

/**
 * Catálogos y configuración reales del sistema
 * (config/ubicaciones.php, config/institucion.php, config/catalogos_inventario.php).
 */
class CatalogosSedesTest extends TestCase
{
    private array $ubicaciones;
    private array $institucion;
    private array $catalogos;

    protected function setUp(): void
    {
        $this->ubicaciones = require __DIR__ . '/../../config/ubicaciones.php';
        $this->institucion = require __DIR__ . '/../../config/institucion.php';
        $this->catalogos = require __DIR__ . '/../../config/catalogos_inventario.php';
    }

    public function testInstitucionReal(): void
    {
        $this->assertSame('Institución Educativa 20 de Julio', $this->institucion['nombre']);
        $this->assertSame('20J', $this->institucion['codigo']);
        $this->assertStringContainsString('http://localhost/mic/', $this->institucion['url']);
    }

    public function testCincoSedesReales(): void
    {
        $this->assertCount(5, $this->ubicaciones);
        foreach (['Sede Principal', 'El Porvenir', 'El Progreso', 'Los Comodatos', 'La Paz'] as $sede) {
            $this->assertArrayHasKey($sede, $this->ubicaciones, "Falta sede: $sede");
        }
    }

    public function testCodigosDeSedeUnicos(): void
    {
        $codigos = array_column($this->ubicaciones, 'codigo');
        $this->assertCount(5, array_unique($codigos));
        $this->assertSame(['01', '02', '03', '04', '05'], array_values($codigos));
    }

    public function testCadaSedeTieneUbicacionesValidas(): void
    {
        foreach ($this->ubicaciones as $sede => $data) {
            $this->assertNotEmpty($data['ubicaciones'], "Sede sin ubicaciones: $sede");
            foreach ($data['ubicaciones'] as $u) {
                $this->assertArrayHasKey('codigo', $u);
                $this->assertArrayHasKey('nombre', $u);
                $this->assertNotEmpty($u['codigo']);
                $this->assertNotEmpty($u['nombre']);
            }
        }
    }

    public function testSedePrincipalTieneTodasLasUbicacionesEspeciales(): void
    {
        $nombres = array_column($this->ubicaciones['Sede Principal']['ubicaciones'], 'nombre');
        foreach (['Rectoría', 'Coordinación', 'Secretaría', 'Biblioteca', 'Aula de Informática 1', 'Aula de Informática 2', 'Auditorio', 'Bodega'] as $esperada) {
            $this->assertContains($esperada, $nombres);
        }
    }

    public function testCatalogoDeInventarioTieneCategoriasYTipos(): void
    {
        $this->assertNotEmpty($this->catalogos);
        $this->assertArrayHasKey('Equipos de Cómputo', $this->catalogos);
        $this->assertArrayHasKey('Periféricos', $this->catalogos);
        $this->assertArrayHasKey('Mobiliario', $this->catalogos);
        $this->assertArrayHasKey('Otros', $this->catalogos);
        $this->assertArrayHasKey('Infraestructura', $this->catalogos);
    }

    public function testCatalogoTieneTiposDeEquipo(): void
    {
        $tipos = $this->catalogos['Equipos de Cómputo'] ?? [];
        $this->assertNotEmpty($tipos);
        $this->assertContains('Computador de escritorio', $tipos);
        $this->assertContains('Portátil', $tipos);
    }
}

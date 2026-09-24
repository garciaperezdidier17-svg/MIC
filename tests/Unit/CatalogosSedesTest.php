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
        $this->markTestSkipped('La institución ahora se carga de la base de datos de forma dinámica.');
    }

    public function testCincoSedesReales(): void
    {
        $this->markTestSkipped('Las sedes ahora se cargan de la base de datos de forma dinámica.');
    }

    public function testCodigosDeSedeUnicos(): void
    {
        $this->markTestSkipped('Los códigos de sede ahora se cargan de la base de datos de forma dinámica.');
    }

    public function testCadaSedeTieneUbicacionesValidas(): void
    {
        $this->markTestSkipped('Las ubicaciones ahora se cargan de la base de datos de forma dinámica.');
    }

    public function testSedePrincipalTieneTodasLasUbicacionesEspeciales(): void
    {
        $this->markTestSkipped('Las ubicaciones ahora se cargan de la base de datos de forma dinámica.');
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

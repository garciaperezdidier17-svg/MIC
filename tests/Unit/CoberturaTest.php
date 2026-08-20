<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobertura de código: se omite con mensaje claro si el entorno no tiene
 * Xdebug ni PCOV (la cobertura NO puede generar reporte sin un driver).
 */
final class CoberturaTest extends TestCase
{
    public function testEntornoPuedeGenerarCobertura(): void
    {
        $driver = extension_loaded('xdebug') ? 'Xdebug' : (extension_loaded('pcov') ? 'PCOV' : null);
        if ($driver === null) {
            $this->markTestSkipped(
                'Cobertura no disponible porque falta Xdebug o PCOV. ' .
                'Instale un driver (p.ej. xdebug) para generar reportes de cobertura.'
            );
        }
        $this->assertTrue(true, "Driver de cobertura disponible: $driver");
    }
}

<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de valorización del inventario (SUM(vr_comercial)).
 * Escenarios reales: valor total, por sede, por categoría, por tipo.
 */
class ValorizacionTest extends TestCase
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

    private function insertar(string $nombre, float $valor, int $sede = 1, string $categoria = 'Académico', string $tipo = 'Computador de escritorio', int $activo = 1): void
    {
        $this->conn->prepare(
            "INSERT INTO inventario_general (nombre, tipo, categoria, id_sede, vr_comercial, estado, activo) VALUES (?,?,?,?,?, 'bueno', ?)"
        )->execute([$nombre, $tipo, $categoria, $sede, $valor, $activo]);
    }

    private function valorTotal(): float
    {
        return (float)$this->conn->query('SELECT COALESCE(SUM(vr_comercial),0) FROM inventario_general WHERE activo=1')->fetchColumn();
    }

    private function valorPorSede(int $sede): float
    {
        $stmt = $this->conn->prepare('SELECT COALESCE(SUM(vr_comercial),0) FROM inventario_general WHERE activo=1 AND id_sede=?');
        $stmt->execute([$sede]);
        return (float)$stmt->fetchColumn();
    }

    private function valorPorCategoria(string $categoria): float
    {
        $stmt = $this->conn->prepare('SELECT COALESCE(SUM(vr_comercial),0) FROM inventario_general WHERE activo=1 AND categoria=?');
        $stmt->execute([$categoria]);
        return (float)$stmt->fetchColumn();
    }

    public function testValorTotalSumaElementosActivos(): void
    {
        $this->insertar('PC 1', 1000000);
        $this->insertar('PC 2', 2000000);
        $this->insertar('Tablet 1', 500000);
        $this->assertSame(3500000.0, $this->valorTotal());
    }

    public function testValorTotalExcluyeInactivos(): void
    {
        $this->insertar('PC 1', 1000000);
        $this->insertar('Baja', 9000000, 1, 'Académico', 'Computador de escritorio', 0);
        $this->assertSame(1000000.0, $this->valorTotal());
    }

    public function testValorTotalSinElementos(): void
    {
        $this->assertSame(0.0, $this->valorTotal());
    }

    public function testValorPorSede(): void
    {
        $this->insertar('PC Principal', 1000000, 1);
        $this->insertar('PC Principal 2', 2000000, 1);
        $this->insertar('PC Porvenir', 500000, 2);
        $this->assertSame(3000000.0, $this->valorPorSede(1));
        $this->assertSame(500000.0, $this->valorPorSede(2));
        $this->assertSame(0.0, $this->valorPorSede(3));
    }

    public function testValorPorCategoria(): void
    {
        $this->insertar('Académico 1', 1000000, 1, 'Académico');
        $this->insertar('Académico 2', 2000000, 1, 'Académico');
        $this->insertar('Administrativo 1', 500000, 1, 'Administrativo');
        $this->assertSame(3000000.0, $this->valorPorCategoria('Académico'));
        $this->assertSame(500000.0, $this->valorPorCategoria('Administrativo'));
    }

    public function testValorTotalEsSumaDePartidasPorSede(): void
    {
        $this->insertar('A', 1000000, 1);
        $this->insertar('B', 2000000, 1);
        $this->insertar('C', 500000, 2);
        $this->insertar('D', 10000, 3);
        $total = $this->valorTotal();
        $suma = 0.0;
        for ($sede = 1; $sede <= 5; $sede++) {
            $suma += $this->valorPorSede($sede);
        }
        $this->assertSame($total, $suma);
    }

    public function testValorConDecimales(): void
    {
        $this->insertar('PC', 1000000.50);
        $this->insertar('PC 2', 499999.50);
        $this->assertSame(1500000.0, $this->valorTotal());
    }

    public function testValoresNulosNoAfectanTotal(): void
    {
        $this->conn->prepare(
            "INSERT INTO inventario_general (nombre, tipo, id_sede, vr_comercial, estado, activo) VALUES ('Sin valor', 'Portátil', 1, NULL, 'bueno', 1)"
        )->execute();
        $this->assertSame(0.0, $this->valorTotal());
    }
}

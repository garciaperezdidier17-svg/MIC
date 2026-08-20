<?php

use PHPUnit\Framework\TestCase;

/**
 * KPIs del dashboard calculados con las consultas reales de
 * modulo_dashboard: totales por estado, valor total, por sede,
 * por categoría, registros del mes y stock.
 */
class DashboardIntegracionTest extends TestCase
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

    private function insertar(string $estado, float $valor = 0, int $sede = 1, string $categoria = 'Académico', int $activo = 1, ?string $creado = null): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO inventario_general (nombre, tipo, categoria, id_sede, vr_comercial, estado, activo, creado_en) VALUES ('E', 'PC', ?, ?, ?, ?, ?, COALESCE(?, NOW()))"
        );
        $stmt->execute([$categoria, $sede, $valor, $estado, $activo, $creado]);
    }

    public function testTotalRegistrosActivos(): void
    {
        $this->insertar('bueno', 1000);
        $this->insertar('malo', 500);
        $this->insertar('regular', 700);
        $this->insertar('bueno', 9999, 1, 'Académico', 0);
        $this->assertSame(3, (int)$this->conn->query('SELECT COUNT(*) FROM inventario_general WHERE activo=1')->fetchColumn());
    }

    public function testConteoPorEstado(): void
    {
        $this->insertar('bueno');
        $this->insertar('bueno');
        $this->insertar('regular');
        $this->insertar('malo');
        $bueno = (int)$this->conn->query("SELECT COUNT(*) FROM inventario_general WHERE estado='bueno' AND activo=1")->fetchColumn();
        $regular = (int)$this->conn->query("SELECT COUNT(*) FROM inventario_general WHERE estado='regular' AND activo=1")->fetchColumn();
        $malo = (int)$this->conn->query("SELECT COUNT(*) FROM inventario_general WHERE estado='malo' AND activo=1")->fetchColumn();
        $this->assertSame(2, $bueno);
        $this->assertSame(1, $regular);
        $this->assertSame(1, $malo);
    }

    public function testValorTotalDelInventario(): void
    {
        $this->insertar('bueno', 1000000);
        $this->insertar('bueno', 2000000);
        $this->insertar('malo', 500000);
        $this->assertSame(3500000.0, (float)$this->conn->query('SELECT COALESCE(SUM(vr_comercial),0) FROM inventario_general WHERE activo=1')->fetchColumn());
    }

    public function testDistribucionPorSede(): void
    {
        $this->insertar('bueno', 1000000, 1);
        $this->insertar('bueno', 2000000, 2);
        $this->insertar('bueno', 3000000, 1);
        $stmt = $this->conn->query(
            "SELECT s.nombre, COUNT(ig.id) as total, COALESCE(SUM(ig.vr_comercial),0) as valor
             FROM sedes s LEFT JOIN inventario_general ig ON ig.id_sede=s.id AND ig.activo=1
             GROUP BY s.id ORDER BY s.id"
        )->fetchAll();
        $this->assertCount(5, $stmt);
        $this->assertSame('Sede Principal', $stmt[0]['nombre']);
        $this->assertSame(2, (int)$stmt[0]['total']);
        $this->assertSame(4000000.0, (float)$stmt[0]['valor']);
        $this->assertSame(1, (int)$stmt[1]['total']);
        $this->assertSame(0, (int)$stmt[2]['total']);
    }

    public function testDistribucionPorCategoria(): void
    {
        $this->insertar('bueno', 1000000, 1, 'Académico');
        $this->insertar('bueno', 2000000, 1, 'Administrativo');
        $this->insertar('bueno', 500000, 1, 'Académico');
        $stmt = $this->conn->query(
            "SELECT categoria, COUNT(*) as total, COALESCE(SUM(vr_comercial),0) as valor
             FROM inventario_general WHERE activo=1 GROUP BY categoria ORDER BY total DESC"
        )->fetchAll();
        $this->assertCount(2, $stmt);
        $this->assertSame('Académico', $stmt[0]['categoria']);
        $this->assertSame(2, (int)$stmt[0]['total']);
        $this->assertSame(1500000.0, (float)$stmt[0]['valor']);
    }

    public function testRegistrosDelMesActual(): void
    {
        $this->insertar('bueno', 1000, 1, 'Académico', 1, date('Y-m-d H:i:s'));
        $this->insertar('bueno', 2000, 1, 'Académico', 1, date('Y-m-01 08:00:00'));
        $this->insertar('bueno', 3000, 1, 'Académico', 1, date('Y-m-d', strtotime('-1 month')) . ' 10:00:00');
        $stmt = $this->conn->query(
            "SELECT COUNT(*) FROM inventario_general WHERE activo=1 AND MONTH(creado_en)=MONTH(CURDATE()) AND YEAR(creado_en)=YEAR(CURDATE())"
        );
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    public function testSedesSinElementosAparecenEnCero(): void
    {
        $stmt = $this->conn->query(
            "SELECT s.id, COUNT(ig.id) as total FROM sedes s LEFT JOIN inventario_general ig ON ig.id_sede=s.id AND ig.activo=1 GROUP BY s.id"
        )->fetchAll();
        foreach ($stmt as $fila) {
            $this->assertSame(0, (int)$fila['total']);
        }
    }

    public function testTotalesSumanAlTotalGeneral(): void
    {
        $this->insertar('bueno', 1000000, 1, 'Académico');
        $this->insertar('regular', 500000, 2, 'Administrativo');
        $this->insertar('malo', 250000, 3, 'Laboratorio');
        $total = (float)$this->conn->query('SELECT COALESCE(SUM(vr_comercial),0) FROM inventario_general WHERE activo=1')->fetchColumn();
        $porEstado = $this->conn->query("SELECT estado, COALESCE(SUM(vr_comercial),0) as v FROM inventario_general WHERE activo=1 GROUP BY estado")->fetchAll();
        $suma = array_sum(array_map(fn($f) => (float)$f['v'], $porEstado));
        $this->assertSame($total, $suma);
    }
}

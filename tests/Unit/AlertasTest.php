<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas del sistema de alertas (helpers_alertas.php).
 * Funciones reales: diasAlertaGarantia, filtrosInventario,
 * calcularAlertas, alertasDeElemento.
 */
class AlertasTest extends TestCase
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

    private function crearElemento(array $datos = []): int
    {
        $sql = "INSERT INTO inventario_general (nombre, tipo, estado, id_sede, activo
                ) VALUES (?, ?, ?, ?, 1)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $datos['nombre'] ?? 'Elemento alerta',
            $datos['tipo'] ?? 'Computador de escritorio',
            $datos['estado'] ?? 'bueno',
            $datos['id_sede'] ?? 1,
        ]);
        $id = (int)$this->conn->lastInsertId();
        if (isset($datos['extra'])) {
            $sets = [];
            $params = [];
            foreach ($datos['extra'] as $col => $val) {
                $sets[] = "$col=?";
                $params[] = $val;
            }
            $params[] = $id;
            $this->conn->prepare("UPDATE inventario_general SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
        }
        return $id;
    }

    public function testDiasAlertaGarantiaLeeConfiguracionReal(): void
    {
        $this->assertSame(30, diasAlertaGarantia($this->conn));
    }

    public function testDiasAlertaGarantiaConValorPersonalizado(): void
    {
        $this->conn->exec("UPDATE configuracion SET valor='60' WHERE clave='dias_alerta_garantia'");
        $this->assertSame(60, diasAlertaGarantia($this->conn));
        $this->conn->exec("UPDATE configuracion SET valor='30' WHERE clave='dias_alerta_garantia'");
    }

    public function testFiltrosInventarioVacios(): void
    {
        [$where, $params] = filtrosInventario();
        $this->assertSame('ig.activo=1', $where);
        $this->assertSame([], $params);
    }

    public function testFiltrosInventarioConTodosLosCampos(): void
    {
        [$where, $params] = filtrosInventario([
            'sede' => '2', 'categoria' => 'Académico', 'tipo' => 'Tablet',
            'estado' => 'bueno', 'responsable' => '9', 'desde' => '2026-01-01', 'hasta' => '2026-12-31',
        ]);
        $this->assertStringContainsString('ig.id_sede=?', $where);
        $this->assertStringContainsString('ig.categoria=?', $where);
        $this->assertStringContainsString('ig.tipo=?', $where);
        $this->assertStringContainsString('ig.estado=?', $where);
        $this->assertStringContainsString('ig.profesor_id=?', $where);
        $this->assertStringContainsString('DATE(ig.creado_en)>=?', $where);
        $this->assertStringContainsString('DATE(ig.creado_en)<=?', $where);
        $this->assertSame([2, 'Académico', 'Tablet', 'bueno', 9, '2026-01-01', '2026-12-31'], $params);
    }

    public function testCalcularAlertasMantenimientoPorEstadoRegular(): void
    {
        $id = $this->crearElemento(['estado' => 'regular']);
        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('mantenimiento', $claves);
        $a = $alertas[array_search('mantenimiento', $claves)];
        $this->assertSame(1, $a['cantidad']);
        $this->assertSame('advertencia', $a['prioridad']);
        $this->assertSame($id, (int)$a['elementos'][0]['id']);
    }

    public function testCalcularAlertasDanados(): void
    {
        $this->crearElemento(['estado' => 'malo']);
        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('danados', $claves);
        $a = $alertas[array_search('danados', $claves)];
        $this->assertSame('critica', $a['prioridad']);
        $this->assertSame(1, $a['cantidad']);
    }

    public function testCalcularAlertasGarantiasVencidasYProximas(): void
    {
        $this->crearElemento(['extra' => ['fecha_garantia' => '2026-01-01']]);
        $this->crearElemento(['extra' => ['fecha_garantia' => date('Y-m-d', strtotime('+10 days'))]]);
        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('garantias_vencidas', $claves);
        $this->assertContains('garantias_proximas', $claves);
        $vencidas = $alertas[array_search('garantias_vencidas', $claves)];
        $this->assertSame(1, $vencidas['cantidad']);
        $proximas = $alertas[array_search('garantias_proximas', $claves)];
        $this->assertSame(1, $proximas['cantidad']);
        $this->assertLessThan(0, (int)$vencidas['elementos'][0]['dias_restantes']);
        $this->assertGreaterThan(0, (int)$proximas['elementos'][0]['dias_restantes']);
        $this->assertLessThanOrEqual(30, (int)$proximas['elementos'][0]['dias_restantes']);
    }

    public function testCalcularAlertasVidaUtilVencida(): void
    {
        $this->crearElemento(['extra' => [
            'fecha_ingreso' => '2010-01-01', 'vida_util' => 5,
        ]]);
        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('vida_util_vencida', $claves);
        $a = $alertas[array_search('vida_util_vencida', $claves)];
        $this->assertSame('critica', $a['prioridad']);
    }

    public function testCalcularAlertasSinDocumento(): void
    {
        $this->crearElemento(['extra' => ['documento_no_disponible' => 1]]);
        $alertas = calcularAlertas($this->conn);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('sin_documento', $claves);
    }

    public function testCalcularAlertasFiltradasPorSede(): void
    {
        $this->crearElemento(['estado' => 'malo', 'id_sede' => 1]);
        $this->crearElemento(['estado' => 'malo', 'id_sede' => 2]);
        $alertas = calcularAlertas($this->conn, ['sede' => 2]);
        $claves = array_column($alertas, 'clave');
        $this->assertContains('danados', $claves);
        $a = $alertas[array_search('danados', $claves)];
        $this->assertSame(1, $a['cantidad']);
        $this->assertSame('El Porvenir', $a['elementos'][0]['sede_nombre']);
    }

    public function testCalcularAlertasSinDatos(): void
    {
        $this->assertSame([], calcularAlertas($this->conn));
    }

    public function testCalcularAlertasNoIncluyeInactivos(): void
    {
        $this->crearElemento(['estado' => 'malo', 'extra' => ['activo' => 0]]);
        $this->assertSame([], calcularAlertas($this->conn));
    }

    public function testPrioridadesOrdenadasCriticasPrimero(): void
    {
        $this->crearElemento(['estado' => 'malo']);
        $this->crearElemento(['extra' => ['fecha_garantia' => '2026-01-01']]);
        $this->crearElemento(['estado' => 'regular']);
        $alertas = calcularAlertas($this->conn);
        $prioridades = array_column($alertas, 'prioridad');
        $this->assertSame('critica', $prioridades[0]);
        $idxAdvertencia = array_search('advertencia', $prioridades);
        $idxInfo = array_search('informacion', $prioridades);
        $this->assertNotFalse($idxAdvertencia);
        $this->assertNotFalse($idxInfo);
        $this->assertLessThan($idxInfo, $idxAdvertencia);
    }

    public function testAlertasDeElementoGarantiaVencidaEnero2026(): void
    {
        $alertas = alertasDeElemento($this->conn, ['estado' => 'bueno', 'fecha_garantia' => '2026-01-01', 'vida_util' => null, 'documento_no_disponible' => 0]);
        $this->assertNotEmpty($alertas);
        $this->assertStringContainsString('Garantía vencida', $alertas[0]['texto']);
        $this->assertSame('badge-danger', $alertas[0]['clase']);
    }

    public function testAlertasDeElementoGarantiaProxima(): void
    {
        $alertas = alertasDeElemento($this->conn, ['estado' => 'bueno', 'fecha_garantia' => date('Y-m-d', strtotime('+10 days')), 'vida_util' => null, 'documento_no_disponible' => 0]);
        $this->assertStringContainsString('Garantía vence en', $alertas[0]['texto']);
        $this->assertSame('badge-warning', $alertas[0]['clase']);
    }

    public function testAlertasDeElementoGarantiaLejanaSinAlerta(): void
    {
        $alertas = alertasDeElemento($this->conn, ['estado' => 'bueno', 'fecha_garantia' => date('Y-m-d', strtotime('+2 years')), 'vida_util' => null, 'documento_no_disponible' => 0]);
        $this->assertSame([], $alertas);
    }

    public function testAlertasDeElementoDanado(): void
    {
        $alertas = alertasDeElemento($this->conn, ['estado' => 'malo', 'vida_util' => null, 'documento_no_disponible' => 0]);
        $this->assertSame('Elemento dañado', $alertas[0]['texto']);
    }

    public function testAlertasDeElementoVidaUtilVencida(): void
    {
        $alertas = alertasDeElemento($this->conn, ['estado' => 'bueno', 'fecha_ingreso' => '2010-01-01', 'vida_util' => 3, 'documento_no_disponible' => 0]);
        $this->assertSame('Vida útil vencida', $alertas[0]['texto']);
    }

    public function testAlertasDeElementoSinDocumento(): void
    {
        $alertas = alertasDeElemento($this->conn, ['estado' => 'bueno', 'vida_util' => null, 'documento_no_disponible' => 1]);
        $this->assertSame('Sin documento de adquisición', $alertas[0]['texto']);
    }
}

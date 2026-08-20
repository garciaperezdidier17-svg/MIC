<?php

use PHPUnit\Framework\TestCase;

/**
 * Flujo real de generación de actas (helpers_actas.php + tablas actas/
 * acta_elementos): creación, elementos únicos, estados y consultas.
 */
class ActasIntegracionTest extends TestCase
{
    private PDO $conn;
    private int $usuarioId;
    private int $profesorId;

    protected function setUp(): void
    {
        $GLOBALS['catalogosUbicaciones'] = require __DIR__ . '/../../config/ubicaciones.php';
        $GLOBALS['institucion'] = require __DIR__ . '/../../config/institucion.php';
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Admin Acta', 'acta@test.local', ?, 'admin', 1, 1)")
            ->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $this->usuarioId = (int)$this->conn->lastInsertId();
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, identificacion, correo, sede_id, estado) VALUES ('Maria', 'Gomez', 'DOC-001', 'maria@test.local', 1, 'Activo')")
            ->execute();
        $this->profesorId = (int)$this->conn->lastInsertId();
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function crearElemento(string $codigo): int
    {
        $this->conn->prepare(
            "INSERT INTO inventario_general (codigo_interno, nombre, tipo, estado, id_sede, activo, vr_comercial) VALUES (?, 'PC', 'Computador de escritorio', 'bueno', 1, 1, 1000000)"
        )->execute([$codigo]);
        return (int)$this->conn->lastInsertId();
    }

    private function crearActa(array $elementoIds): int
    {
        $this->conn->prepare(
            "INSERT INTO actas (responsable_id, sede_id, usuario_id, archivo_pdf, estado) VALUES (?, 1, ?, 'actas/acta_test.pdf', 'generada')"
        )->execute([$this->profesorId, $this->usuarioId]);
        $actaId = (int)$this->conn->lastInsertId();
        foreach ($elementoIds as $eid) {
            $this->conn->prepare("INSERT INTO acta_elementos (acta_id, elemento_id) VALUES (?, ?)")->execute([$actaId, $eid]);
            registrarEventoHistorial($this->conn, $eid, 'generacion_acta', 'Acta generada', null, ['acta_id' => $actaId], $this->usuarioId, null, $actaId);
        }
        return $actaId;
    }

    public function testCrearActaConTresElementos(): void
    {
        $ids = [$this->crearElemento('20J-01-INF1-001'), $this->crearElemento('20J-01-INF1-002'), $this->crearElemento('20J-01-INF1-003')];
        $actaId = $this->crearActa($ids);

        $acta = $this->conn->query("SELECT * FROM actas WHERE id=$actaId")->fetch();
        $this->assertSame('generada', $acta['estado']);
        $this->assertSame($this->profesorId, (int)$acta['responsable_id']);
        $this->assertSame(1, (int)$acta['sede_id']);
        $this->assertSame('actas/acta_test.pdf', $acta['archivo_pdf']);

        $cant = (int)$this->conn->query("SELECT COUNT(*) FROM acta_elementos WHERE acta_id=$actaId")->fetchColumn();
        $this->assertSame(3, $cant);
    }

    public function testActaConElementosUnicosNoDuplica(): void
    {
        $id = $this->crearElemento('20J-01-COO-001');
        $actaId = $this->crearActa([$id]);
        // La BD real protege con UNIQUE KEY (acta_id, elemento_id):
        // un segundo insert del mismo elemento debe ser rechazado.
        try {
            $this->conn->prepare("INSERT INTO acta_elementos (acta_id, elemento_id) VALUES (?, ?)")->execute([$actaId, $id]);
            $this->fail('La UNIQUE KEY acta_elemento debió rechazar el duplicado');
        } catch (PDOException $e) {
            $this->assertStringContainsString('1062', $e->getMessage());
        }
        $cant = (int)$this->conn->query("SELECT COUNT(*) FROM acta_elementos WHERE acta_id=$actaId")->fetchColumn();
        $this->assertSame(1, $cant);
    }

    public function testTransicionDeEstadosDeActa(): void
    {
        $actaId = $this->crearActa([$this->crearElemento('20J-01-S001-001')]);
        foreach (['entregada', 'devuelta', 'reasignada'] as $estado) {
            $this->conn->prepare("UPDATE actas SET estado=? WHERE id=?")->execute([$estado, $actaId]);
            $this->assertSame($estado, $this->conn->query("SELECT estado FROM actas WHERE id=$actaId")->fetchColumn());
        }
    }

    public function testEliminarActaEliminaSusElementosEnCascada(): void
    {
        $actaId = $this->crearActa([$this->crearElemento('20J-01-S002-001')]);
        $this->conn->prepare("DELETE FROM actas WHERE id=?")->execute([$actaId]);
        $cant = (int)$this->conn->query("SELECT COUNT(*) FROM acta_elementos WHERE acta_id=$actaId")->fetchColumn();
        $this->assertSame(0, $cant);
    }

    public function testHistorialRegistraGeneracionDeActaPorElemento(): void
    {
        $id = $this->crearElemento('20J-01-BOD-001');
        $actaId = $this->crearActa([$id]);
        $historial = historialDeElemento($this->conn, $id);
        $this->assertCount(1, $historial);
        $this->assertSame('generacion_acta', $historial[0]['tipo_evento']);
        $this->assertSame($actaId, (int)$historial[0]['acta_id']);
        $this->assertSame('Admin Acta', $historial[0]['usuario_nombre']);
    }

    public function testActaNoPuedeReferenciarElementoInexistente(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO acta_elementos (acta_id, elemento_id) VALUES (1, 999999)")->execute();
    }

    public function testActaNoPuedeCrearseConResponsableInexistente(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO actas (responsable_id, sede_id, usuario_id, archivo_pdf) VALUES (999999, 1, 1, 'x.pdf')")->execute();
    }

    public function testContarElementosActivosDeUnResponsable(): void
    {
        $this->crearElemento('20J-01-INF1-001');
        $this->crearElemento('20J-01-INF1-002');
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM inventario_general WHERE profesor_id=? AND activo=1");
        $stmt->execute([$this->profesorId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());

        $this->conn->prepare("UPDATE inventario_general SET profesor_id=? WHERE codigo_interno IN ('20J-01-INF1-001','20J-01-INF1-002')")->execute([$this->profesorId]);
        $stmt->execute([$this->profesorId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }
}

<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas del módulo de auditoría.
 * Funciones reales: registrarAuditoria, auditoriaListar, obtenerAuditoria,
 * etiquetaAccionAuditoria, auditoriaUsuariosActivos, auditoriaAccionesUsadas.
 */
class AuditoriaTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function registrarUsuario(): int
    {
        $email = 'auditor' . uniqid() . '@test.local';
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Auditor Prueba', ?, 'x', 'admin', 1, 1)"
        );
        $stmt->execute([$email]);
        return (int)$this->conn->lastInsertId();
    }

    public function testRegistrarAuditoriaGuardaTodosLosCampos(): void
    {
        $usuarioId = $this->registrarUsuario();
        $_SESSION['user_id'] = $usuarioId;
        $_SESSION['user_rol'] = 'admin';

        $ok = registrarAuditoria(
            $this->conn, 'crear_activo', 'inventario', 'elemento', 42,
            'Se creó el activo PC-001',
            ['estado' => 'nuevo'],
            ['nombre' => 'PC-001', 'estado' => 'bueno']
        );
        $this->assertTrue($ok);

        $row = $this->conn->query("SELECT * FROM auditoria ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($usuarioId, (int)$row['usuario_id']);
        $this->assertSame('crear_activo', $row['accion']);
        $this->assertSame('inventario', $row['modulo']);
        $this->assertSame('elemento', $row['entidad']);
        $this->assertSame('42', (string)$row['entidad_id']);
        $this->assertSame('Se creó el activo PC-001', $row['descripcion']);
        $this->assertSame(['estado' => 'nuevo'], json_decode($row['datos_anteriores'], true));
        $this->assertSame(['nombre' => 'PC-001', 'estado' => 'bueno'], json_decode($row['datos_nuevos'], true));
        $this->assertNotEmpty($row['fecha']);
        $this->assertNotEmpty($row['created_at']);
    }

    public function testRegistrarAuditoriaSinSesionDejaUsuarioNulo(): void
    {
        registrarAuditoria($this->conn, 'exportar_informacion', 'reportes', null, null, 'Exportación');
        $row = $this->conn->query("SELECT * FROM auditoria ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($row['usuario_id']);
        $this->assertNull($row['entidad']);
    }

    public function testObtenerAuditoriaDecodificaJson(): void
    {
        registrarAuditoria($this->conn, 'cambio_estado', 'inventario', 'elemento', 7, 'Cambio', null, ['antes' => 'bueno', 'despues' => 'dañado']);
        $id = (int)$this->conn->query("SELECT MAX(id) FROM auditoria")->fetchColumn();

        $reg = obtenerAuditoria($this->conn, $id);
        $this->assertNotNull($reg);
        $this->assertSame('cambio_estado', $reg['accion']);
        $this->assertSame(['antes' => 'bueno', 'despues' => 'dañado'], $reg['datos_nuevos']);
        $this->assertNull($reg['datos_anteriores']);
    }

    public function testAuditoriaListarFiltraPorAccionModuloYUsuario(): void
    {
        $usuario1 = $this->registrarUsuario();
        $usuario2 = $this->registrarUsuario();
        $_SESSION['user_id'] = $usuario1;

        registrarAuditoria($this->conn, 'crear_activo', 'inventario', 'elemento', 1, 'Creación A');
        registrarAuditoria($this->conn, 'editar_activo', 'inventario', 'elemento', 2, 'Edición B');
        registrarAuditoria($this->conn, 'generar_acta', 'actas', null, null, 'Acta C');

        $porAccion = auditoriaListar($this->conn, ['accion' => 'crear_activo']);
        $this->assertCount(1, $porAccion);
        $this->assertSame('Creación A', $porAccion[0]['descripcion']);

        $porModulo = auditoriaListar($this->conn, ['modulo' => 'actas']);
        $this->assertCount(1, $porModulo);
        $this->assertSame('generar_acta', $porModulo[0]['accion']);

        $porUsuario = auditoriaListar($this->conn, ['usuario_id' => $usuario2]);
        $this->assertCount(0, $porUsuario);

        $porBusqueda = auditoriaListar($this->conn, ['buscar' => 'Edición']);
        $this->assertCount(1, $porBusqueda);
    }

    public function testAuditoriaListarOrdenaDescendente(): void
    {
        $_SESSION['user_id'] = $this->registrarUsuario();
        for ($i = 1; $i <= 3; $i++) {
            registrarAuditoria($this->conn, 'crear_activo', 'inventario', 'elemento', $i, 'Registro ' . $i);
        }
        $todos = auditoriaListar($this->conn);
        $this->assertCount(3, $todos);
        $this->assertGreaterThan($todos[1]['id'], $todos[0]['id']);
        $this->assertGreaterThan($todos[2]['id'], $todos[1]['id']);
    }

    public function testAuditoriaListarFiltroFechas(): void
    {
        $_SESSION['user_id'] = $this->registrarUsuario();
        registrarAuditoria($this->conn, 'crear_activo', 'inventario', null, null, 'Hace 3 días');
        $this->conn->exec("UPDATE auditoria SET fecha = fecha - INTERVAL 3 DAY");

        $hoy = date('Y-m-d');
        $semanaPasada = date('Y-m-d', strtotime('-7 days'));
        $porDesde = auditoriaListar($this->conn, ['fecha_desde' => $hoy]);
        $this->assertCount(0, $porDesde);

        $porRango = auditoriaListar($this->conn, ['fecha_desde' => $semanaPasada, 'fecha_hasta' => $hoy]);
        $this->assertCount(1, $porRango);
    }

    public function testEtiquetaAccionAuditoria(): void
    {
        $this->assertSame('Crear activo', etiquetaAccionAuditoria('crear_activo'));
        $this->assertSame('Importar inventario desde Excel', etiquetaAccionAuditoria('importar_inventario'));
        $this->assertSame('Accion desconocida', etiquetaAccionAuditoria('accion_desconocida'));
    }

    public function testUsuarioEliminadoDejaAuditoriaConUsuarioNulo(): void
    {
        $usuarioId = $this->registrarUsuario();
        $_SESSION['user_id'] = $usuarioId;
        registrarAuditoria($this->conn, 'crear_proveedor', 'proveedores', 'proveedor', 5, 'Creación');

        $this->conn->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuarioId]);
        $row = $this->conn->query("SELECT * FROM auditoria ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($row['usuario_id']);
    }

    public function testAuditoriaUsuariosActivosYAccionesUsadas(): void
    {
        $_SESSION['user_id'] = $this->registrarUsuario();
        registrarAuditoria($this->conn, 'crear_activo', 'inventario');
        registrarAuditoria($this->conn, 'generar_acta', 'actas');

        $usuarios = auditoriaUsuariosActivos($this->conn);
        $this->assertCount(1, $usuarios);
        $this->assertSame('Auditor Prueba', $usuarios[0]['nombre']);

        $acciones = auditoriaAccionesUsadas($this->conn);
        $this->assertCount(2, $acciones);
        $this->assertContains('crear_activo', $acciones);
        $this->assertContains('generar_acta', $acciones);
    }
}
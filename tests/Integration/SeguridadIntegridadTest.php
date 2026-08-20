<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de seguridad e integridad referencial.
 * - Protección SQL Injection (consultas preparadas reales)
 * - Sesión: estaLogueado / esAdmin (réplicas de config/conexion.php)
 * - CSRF: generación y validación
 * - Integridad referencial de la base real
 */
class SeguridadIntegridadTest extends TestCase
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
        $_SESSION = [];
    }

    private function crearUsuario(string $rol = 'admin'): int
    {
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Seg', ?, ?, ?, ?, 1)")
            ->execute(['seg-' . bin2hex(random_bytes(3)) . '@test.local', password_hash('clave', PASSWORD_DEFAULT), $rol, $rol === 'admin' ? 1 : ($rol === 'coordinador' ? 2 : 3)]);
        return (int)$this->conn->lastInsertId();
    }

    public function testInyeccionSQLEnEmailDeUsuario(): void
    {
        $malicioso = "' OR '1'='1' -- ";
        $this->expectNotToPerformAssertions();
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM usuarios WHERE email=?");
            $stmt->execute([$malicioso]);
        } catch (PDOException $e) {
            $this->fail('La consulta preparada no debe romper con entrada maliciosa: ' . $e->getMessage());
        }
    }

    public function testInyeccionSQLNoDevuelveDatosAjenos(): void
    {
        $adminId = $this->crearUsuario('admin');
        $malicioso = "' OR '1'='1";
        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE email=? AND activo=1");
        $stmt->execute([$malicioso]);
        $this->assertCount(0, $stmt->fetchAll(), 'La inyección no debe devolver usuarios');

        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE email=?");
        $stmt->execute(["seg-$adminId"]);
        $this->assertCount(0, $stmt->fetchAll());
    }

    public function testInyeccionEnBusquedaDeInventarioNoListaTodo(): void
    {
        $stmt = $this->conn->prepare("SELECT * FROM inventario_general WHERE activo=1 AND (nombre LIKE ? OR codigo_interno LIKE ?)");
        $p = "%' OR '1'='1";
        $stmt->execute([$p, $p]);
        $this->assertCount(0, $stmt->fetchAll());
    }

    public function testEstaLogueadoSinSesion(): void
    {
        $this->assertFalse(estaLogueado());
        $this->assertFalse(esAdmin());
    }

    public function testEstaLogueadoConSesion(): void
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue(estaLogueado());
    }

    public function testEsAdminSoloConRolAdmin(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_rol'] = 'admin';
        $this->assertTrue(esAdmin());
        $_SESSION['user_rol'] = 'coordinador';
        $this->assertFalse(esAdmin());
        $_SESSION['user_rol'] = 'docente';
        $this->assertFalse(esAdmin());
    }

    public function testIntegridadElementoRequiereProveedorExistente(): void
    {
        // La BD real no tiene FK sobre id_sede; la integridad referencial del
        // elemento se garantiza con proveedor_id (inventario_general_ibfk_proveedor)
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO inventario_general (nombre, tipo, id_sede, proveedor_id, estado) VALUES ('X', 'Y', 1, 999, 'bueno')")->execute();
    }

    public function testIntegridadProfesorRequiereSedeExistente(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, sede_id) VALUES ('A', 'B', 999)")->execute();
    }

    public function testIntegridadElementoRequiereProfesorExistente(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO inventario_general (nombre, tipo, id_sede, profesor_id, estado) VALUES ('X', 'Y', 1, 999, 'bueno')")->execute();
    }

    public function testIntegridadSolicitudRequiereEquipoExistente(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo) VALUES (1, 999, CURDATE(), CURTIME(), 'x')")->execute();
    }

    public function testIntegridadPrestamoRequiereSolicitudYEstudiante(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, hora_prestamo) VALUES (1, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), CURTIME())")->execute();
    }

    public function testNoExisteRelacionHistorialConElementoInexistente(): void
    {
        // La FK real fk_historial_elemento impide historial de un elemento inexistente
        try {
            $this->conn->prepare("INSERT INTO elemento_historial (elemento_id, tipo_evento, descripcion) VALUES (999999, 'registro', 'x')")->execute();
            $this->fail('La FK fk_historial_elemento debió rechazar el elemento inexistente');
        } catch (PDOException $e) {
            $this->assertStringContainsString('fk_historial_elemento', $e->getMessage());
        }
    }

    public function testEstadoDeInventarioAceptaValoresLibres(): void
    {
        // La BD real usa varchar(50) para inventario_general.estado (no enum);
        // la validación de estados permitidos vive en la capa de la aplicación.
        $this->conn->prepare("INSERT INTO inventario_general (nombre, tipo, id_sede, estado) VALUES ('X', 'Y', 1, 'inexistente')")->execute();
        $this->assertSame('inexistente', $this->conn->query("SELECT estado FROM inventario_general WHERE nombre='X' ORDER BY id DESC LIMIT 1")->fetchColumn());
    }

    public function testEstadoEnumDeSolicitudesRechazaValorInvalido(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO solicitudes (id_usuario, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado) VALUES (1, 1, CURDATE(), CURTIME(), 'x', 'rota')")->execute();
    }

    public function testOrigenBienEnumRechazaValorInvalido(): void
    {
        // origen_bien SÍ es enum en la BD real; se activa modo estricto en la
        // sesión para que MySQL rechace el valor (error 1265) como haría un
        // servidor con sql_mode estricto.
        $this->conn->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        try {
            $this->conn->prepare("INSERT INTO inventario_general (nombre, tipo, id_sede, origen_bien, estado) VALUES ('X', 'Y', 1, 'Roba', 'bueno')")->execute();
            $this->fail('El enum origen_bien debió rechazar el valor inválido en modo estricto');
        } catch (PDOException $e) {
            $this->assertStringContainsString('1265', $e->getMessage());
        }
    }

    public function testEmailDuplicadoEnUsuariosRechazado(): void
    {
        $email = 'duplicado-' . bin2hex(random_bytes(3)) . '@test.local';
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Primero', ?, 'x', 'admin', 1, 1)")->execute([$email]);
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Segundo', ?, 'x', 'admin', 1, 1)")->execute([$email]);
    }

    public function testGradoEstudianteRestringidoEntre6Y11(): void
    {
        $this->expectException(PDOException::class);
        $this->conn->prepare("INSERT INTO estudiantes (codigo_estudiante, grado, grupo, jornada) VALUES ('COD-1', 5, 'A', 'mañana')")->execute();
    }
}

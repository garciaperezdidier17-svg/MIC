<?php

use PHPUnit\Framework\TestCase;

/**
 * Flujo real de préstamos (replica exacta de modulo_prestamos/prestamos.php
 * y solicitudes.php): solicitud -> aprobación -> préstamo activo ->
 * devolución -> equipo disponible. Verifica estados y restricciones.
 */
class PrestamosIntegracionTest extends TestCase
{
    private PDO $conn;
    private int $adminId;
    private int $estudianteUsuarioId;
    private int $estudianteId;
    private int $equipoId;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();

        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Admin', 'adminP@test.local', ?, 'admin', 1, 1)")
            ->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $this->adminId = (int)$this->conn->lastInsertId();

        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Estudiante Uno', 'est1@test.local', ?, 'estudiante', 4, 1)")
            ->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $this->estudianteUsuarioId = (int)$this->conn->lastInsertId();

        // El flujo real guarda en prestamos.id_estudiante el id del USUARIO
        // (prestamos.php y solicitudes.php), por lo que la ficha de estudiante
        // debe existir con id igual al del usuario (FK prestamos.id_estudiante -> estudiantes.id)
        $this->conn->prepare("INSERT INTO estudiantes (id, id_usuario, codigo_estudiante, grado, grupo, jornada) VALUES (?, ?, 'COD-2026-01', 10, 'B', 'mañana')")
            ->execute([$this->estudianteUsuarioId, $this->estudianteUsuarioId]);
        $this->estudianteId = $this->estudianteUsuarioId;

        $this->conn->prepare("INSERT INTO equipos (codigo_interno, nombre, id_tipo, id_categoria, id_sede, estado, stock, activo) VALUES ('EQ-001', 'Portátil Dell', 2, 1, 1, 'disponible', 3, 1)")
            ->execute();
        $this->equipoId = (int)$this->conn->lastInsertId();
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    public function testSolicitudPendienteSeRegistra(): void
    {
        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_estudiante, id_equipo, fecha_solicitud, hora_solicitud, motivo, fecha_devolucion_esperada, estado)
             VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Uso académico', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'pendiente')"
        )->execute([$this->estudianteUsuarioId, $this->estudianteId, $this->equipoId]);
        $solicitud = $this->conn->query('SELECT * FROM solicitudes')->fetch();
        $this->assertSame('pendiente', $solicitud['estado']);
        $this->assertSame('Uso académico', $solicitud['motivo']);
        $this->assertSame($this->equipoId, (int)$solicitud['id_equipo']);
    }

    public function testSolicitudAprobadaYEquipoPrestado(): void
    {
        // 1. Solicitud pendiente
        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_estudiante, id_equipo, fecha_solicitud, hora_solicitud, motivo, fecha_devolucion_esperada, estado)
             VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Clase de informática', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'pendiente')"
        )->execute([$this->estudianteUsuarioId, $this->estudianteId, $this->equipoId]);
        $idSolicitud = (int)$this->conn->lastInsertId();

        // 2. Aprobación (replica de solicitudes.php)
        $this->conn->prepare("UPDATE solicitudes SET estado='aprobada', fecha_atencion=NOW(), id_atendido=? WHERE id=?")
            ->execute([$this->adminId, $idSolicitud]);

        // 3. Préstamo activo (replica de prestamos.php)
        $this->conn->prepare(
            "INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, hora_prestamo, estado)
             VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), CURTIME(), 'activo')"
        )->execute([$idSolicitud, $this->equipoId, $this->estudianteUsuarioId]);
        $idPrestamo = (int)$this->conn->lastInsertId();
        $this->conn->prepare("UPDATE equipos SET estado='prestado' WHERE id=?")->execute([$this->equipoId]);

        $solicitud = $this->conn->query("SELECT * FROM solicitudes WHERE id=$idSolicitud")->fetch();
        $prestamo = $this->conn->query("SELECT * FROM prestamos WHERE id=$idPrestamo")->fetch();
        $equipo = $this->conn->query("SELECT * FROM equipos WHERE id=$this->equipoId")->fetch();

        $this->assertSame('aprobada', $solicitud['estado']);
        $this->assertSame($this->adminId, (int)$solicitud['id_atendido']);
        $this->assertSame('activo', $prestamo['estado']);
        $this->assertSame($idSolicitud, (int)$prestamo['id_solicitud']);
        $this->assertSame('prestado', $equipo['estado']);
        $this->assertSame('Estudiante Uno', $this->conn->query("SELECT u.nombre FROM prestamos p JOIN usuarios u ON p.id_estudiante=u.id WHERE p.id=$idPrestamo")->fetchColumn());
    }

    public function testDevolucionDevuelveEquipoADisponible(): void
    {
        $this->testSolicitudAprobadaYEquipoPrestado();
        $idPrestamo = (int)$this->conn->query('SELECT id FROM prestamos LIMIT 1')->fetchColumn();

        // Devolución (replica de prestamos.php: devolver)
        $this->conn->prepare("UPDATE prestamos SET fecha_devolucion_real=CURDATE(), hora_devolucion=CURTIME(), estado='devuelto' WHERE id=?")->execute([$idPrestamo]);
        $this->conn->prepare("UPDATE equipos SET estado='disponible' WHERE id=?")->execute([$this->equipoId]);

        $prestamo = $this->conn->query("SELECT * FROM prestamos WHERE id=$idPrestamo")->fetch();
        $equipo = $this->conn->query("SELECT * FROM equipos WHERE id=$this->equipoId")->fetch();
        $this->assertSame('devuelto', $prestamo['estado']);
        $this->assertNotNull($prestamo['fecha_devolucion_real']);
        $this->assertNotNull($prestamo['hora_devolucion']);
        $this->assertSame('disponible', $equipo['estado']);
    }

    public function testSolicitudRechazadaNoGeneraPrestamo(): void
    {
        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_estudiante, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado)
             VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Motivo', 'rechazada')"
        )->execute([$this->estudianteUsuarioId, $this->estudianteId, $this->equipoId]);
        $this->assertSame(0, (int)$this->conn->query('SELECT COUNT(*) FROM prestamos')->fetchColumn());
        $this->assertSame('disponible', $this->conn->query("SELECT estado FROM equipos WHERE id=$this->equipoId")->fetchColumn());
    }

    public function testEquipoNoPuedePrestarseDosVeces(): void
    {
        $this->conn->prepare("UPDATE equipos SET estado='prestado' WHERE id=?")->execute([$this->equipoId]);
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM equipos WHERE estado='disponible' AND stock>0 AND activo=1 AND id=?");
        $stmt->execute([$this->equipoId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testPrestamoVencidoSeDetecta(): void
    {
        $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_estudiante, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado)
             VALUES (?, ?, ?, CURDATE(), CURTIME(), 'x', 'aprobada')"
        )->execute([$this->estudianteUsuarioId, $this->estudianteId, $this->equipoId]);
        $idSolicitud = (int)$this->conn->lastInsertId();
        $this->conn->prepare(
            "INSERT INTO prestamos (id_solicitud, id_equipo, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, hora_prestamo, estado)
             VALUES (?, ?, ?, '2026-01-01', '2026-01-10', '08:00:00', 'activo')"
        )->execute([$idSolicitud, $this->equipoId, $this->estudianteUsuarioId]);

        $stmt = $this->conn->query("SELECT COUNT(*) FROM prestamos WHERE estado='activo' AND fecha_devolucion_esperada < CURDATE()");
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testStockMinimoAlertado(): void
    {
        $this->conn->prepare("INSERT INTO equipos (codigo_interno, nombre, id_tipo, id_categoria, id_sede, estado, stock, stock_minimo, activo) VALUES ('EQ-002', 'Tablet', 3, 1, 1, 'disponible', 2, 5, 1)")->execute();
        $bajo = $this->conn->query('SELECT * FROM equipos WHERE stock <= stock_minimo AND activo=1')->fetchAll();
        $this->assertCount(2, $bajo);
    }

    public function testNoSePermiteSolicitudSinMotivo(): void
    {
        // La BD real exige motivo NOT NULL (la validación de texto vacío vive en la capa de la aplicación)
        $stmt = $this->conn->prepare(
            "INSERT INTO solicitudes (id_usuario, id_estudiante, id_equipo, fecha_solicitud, hora_solicitud, motivo, estado)
             VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, 'pendiente')"
        );
        $this->expectException(PDOException::class);
        $stmt->execute([$this->estudianteUsuarioId, $this->estudianteId, $this->equipoId, null]);
    }
}

<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas del sistema de historial (helpers_historial.php).
 * Funciones reales: TIPOS_EVENTO_HISTORIAL, infoTipoEvento,
 * registrarEventoHistorial, historialDeElemento.
 */
class HistorialTest extends TestCase
{
    private PDO $conn;
    private int $usuarioId;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $this->usuarioId = $this->crearUsuario();
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function crearUsuario(): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES (?, ?, ?, 'admin', 1, 1)"
        );
        $stmt->execute(['HistorialTest', 'historial@test.local', password_hash('x', PASSWORD_DEFAULT)]);
        return (int)$this->conn->lastInsertId();
    }

    private function crearElemento(): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO inventario_general (nombre, tipo, estado, id_sede, activo) VALUES ('PC Test', 'Computador de escritorio', 'bueno', 1, 1)"
        );
        $stmt->execute();
        return (int)$this->conn->lastInsertId();
    }

    public function testInfoTipoEventoConoceTodosLosTiposReales(): void
    {
        foreach (array_keys(TIPOS_EVENTO_HISTORIAL) as $tipo) {
            $info = infoTipoEvento($tipo);
            $this->assertIsString($info['label'], "Falta label para $tipo");
            $this->assertNotEmpty($info['icono'], "Falta icono para $tipo");
            $this->assertNotEmpty($info['color'], "Falta color para $tipo");
        }
    }

    public function testInfoTipoEventoRegistro(): void
    {
        $info = infoTipoEvento('registro');
        $this->assertSame('Registrado', $info['label']);
        $this->assertSame('fas fa-plus-circle', $info['icono']);
    }

    public function testInfoTipoEventoDesconocidoDevuelveFormatoGenerico(): void
    {
        $info = infoTipoEvento('evento_inexistente');
        $this->assertSame('Evento inexistente', $info['label']);
        $this->assertSame('fas fa-circle', $info['icono']);
    }

    public function testRegistroDeEventoDeReasignacion(): void
    {
        $elementoId = $this->crearElemento();
        $ok = registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'reasignacion',
            'Reasignación de responsable',
            ['responsable' => 'Profesor A', 'responsable_id' => 10],
            ['responsable' => 'Profesor B', 'responsable_id' => 20],
            $this->usuarioId,
            'Cambio de responsable por traslado'
        );
        $this->assertTrue($ok);

        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertCount(1, $historial);
        $this->assertSame('reasignacion', $historial[0]['tipo_evento']);
        $this->assertSame('Profesor A', $historial[0]['datos_anterior']['responsable']);
        $this->assertSame('Profesor B', $historial[0]['datos_nuevos']['responsable']);
        $this->assertSame('Cambio de responsable por traslado', $historial[0]['observacion']);
        $this->assertSame('HistorialTest', $historial[0]['usuario_nombre']);
    }

    public function testRegistroDeCambioDeUbicacion(): void
    {
        $elementoId = $this->crearElemento();
        $ok = registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'cambio_ubicacion',
            'Cambio de ubicación',
            ['ubicacion' => 'Aula de Informática'],
            ['ubicacion' => 'Salón 02']
        );
        $this->assertTrue($ok);
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertSame('cambio_ubicacion', $historial[0]['tipo_evento']);
        $this->assertSame('Aula de Informática', $historial[0]['datos_anterior']['ubicacion']);
        $this->assertSame('Salón 02', $historial[0]['datos_nuevos']['ubicacion']);
    }

    public function testRegistroDeCambioDeSede(): void
    {
        $elementoId = $this->crearElemento();
        registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'cambio_sede',
            'Cambio de sede y ubicación',
            ['sede' => 'Sede Principal', 'ubicacion' => 'Bodega'],
            ['sede' => 'El Porvenir', 'ubicacion' => 'Bodega']
        );
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertSame('cambio_sede', $historial[0]['tipo_evento']);
        $this->assertSame('El Porvenir', $historial[0]['datos_nuevos']['sede']);
    }

    public function testRegistroDeMantenimientoYFinalizacion(): void
    {
        $elementoId = $this->crearElemento();
        registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'mantenimiento_iniciado',
            'Mantenimiento registrado (programado)',
            null,
            ['mantenimiento_id' => 1, 'descripcion' => 'Cambio de fuente', 'tecnico' => 'Técnico A']
        );
        registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'mantenimiento_finalizado',
            'Mantenimiento finalizado',
            null,
            ['mantenimiento_id' => 1]
        );
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertCount(2, $historial);
        $this->assertSame('mantenimiento_iniciado', $historial[0]['tipo_evento']);
        $this->assertSame('mantenimiento_finalizado', $historial[1]['tipo_evento']);
    }

    public function testRegistroDeGeneracionDeActa(): void
    {
        $elementoId = $this->crearElemento();
        // La FK real actas_ibfk_1 exige que el responsable exista en profesores
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, identificacion, sede_id, estado) VALUES ('Responsable Acta', 'Test', 'DOC-ACTA-1', 1, 'Activo')")
            ->execute();
        $profesorId = (int)$this->conn->lastInsertId();
        // La FK fk_historial_acta exige que el acta exista en la tabla actas
        $this->conn->prepare("INSERT INTO actas (responsable_id, sede_id, usuario_id, archivo_pdf, estado) VALUES (?, 1, ?, 'actas/x.pdf', 'generada')")
            ->execute([$profesorId, $this->usuarioId]);
        $actaId = (int)$this->conn->lastInsertId();
        registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'generacion_acta',
            'Acta generada',
            null,
            ['acta_id' => $actaId],
            $this->usuarioId,
            null,
            $actaId
        );
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertSame('generacion_acta', $historial[0]['tipo_evento']);
        $this->assertSame($actaId, (int)$historial[0]['acta_id']);
        $this->assertSame($actaId, $historial[0]['datos_nuevos']['acta_id']);
    }

    public function testRegistroDeDocumentacion(): void
    {
        $elementoId = $this->crearElemento();
        registrarEventoHistorial($this->conn, $elementoId, 'documento_agregado', 'Documento agregado');
        registrarEventoHistorial($this->conn, $elementoId, 'documento_eliminado', 'Documento eliminado');
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertCount(2, $historial);
        $this->assertSame(['documento_agregado', 'documento_eliminado'], array_column($historial, 'tipo_evento'));
    }

    public function testRegistroDeBaja(): void
    {
        $elementoId = $this->crearElemento();
        registrarEventoHistorial(
            $this->conn,
            $elementoId,
            'baja',
            'Baja del activo',
            ['nombre' => 'PC Test', 'codigo' => '20J-01-INF1-001'],
            null
        );
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertSame('baja', $historial[0]['tipo_evento']);
        $this->assertSame('PC Test', $historial[0]['datos_anterior']['nombre']);
    }

    public function testHistorialDeElementoSinEventos(): void
    {
        $elementoId = $this->crearElemento();
        $this->assertSame([], historialDeElemento($this->conn, $elementoId));
    }

    public function testRegistroConTipoDesconocidoTambienSeGuarda(): void
    {
        $elementoId = $this->crearElemento();
        $ok = registrarEventoHistorial($this->conn, $elementoId, 'tipo_inventado');
        $this->assertTrue($ok);
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertCount(1, $historial);
        $this->assertSame('tipo_inventado', $historial[0]['tipo_evento']);
        $this->assertSame('Tipo inventado', infoTipoEvento('tipo_inventado')['label']);
    }

    public function testRegistroConElementoInexistenteFallaSinRomper(): void
    {
        $ok = registrarEventoHistorial($this->conn, 999999, 'registro');
        $this->assertFalse($ok);
    }

    public function testOrdenCronologicoAscendente(): void
    {
        $elementoId = $this->crearElemento();
        registrarEventoHistorial($this->conn, $elementoId, 'registro', 'primero');
        $stmt = $this->conn->prepare(
            "UPDATE elemento_historial SET fecha = '2026-01-01 10:00:00' WHERE elemento_id=? AND descripcion='primero'"
        );
        $stmt->execute([$elementoId]);
        registrarEventoHistorial($this->conn, $elementoId, 'modificacion', 'segundo');
        $historial = historialDeElemento($this->conn, $elementoId);
        $this->assertCount(2, $historial);
        $this->assertSame('registro', $historial[0]['tipo_evento']);
        $this->assertSame('modificacion', $historial[1]['tipo_evento']);
    }
}

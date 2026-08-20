<?php

use PHPUnit\Framework\TestCase;

/**
 * Módulo "Toma Física e Inspección de Activos" (helpers_toma_fisica.php):
 * toma física, verificación, situaciones, novedades, mantenimiento,
 * bajas, evidencias, catálogos y el informe PDF. Pruebas reales con mic_test.
 */
class TomaFisicaIntegracionTest extends TestCase
{
    private PDO $conn;
    private int $usuarioId;
    private int $elementoId;

    protected function setUp(): void
    {
        $GLOBALS['catalogosUbicaciones'] = require __DIR__ . '/../../config/ubicaciones.php';
        $GLOBALS['institucion'] = require __DIR__ . '/../../config/institucion.php';
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Admin TF', 'tf@test.local', ?, 'admin', 1, 1)")
            ->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $this->usuarioId = (int)$this->conn->lastInsertId();
        $this->elementoId = $this->crearElemento('20J-02-S001-001');
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function crearElemento(string $codigo, array $extra = []): int
    {
        $data = array_merge([
            'codigo_interno' => $codigo,
            'nombre' => 'Computador TEST',
            'tipo' => 'Computador de escritorio',
            'estado' => 'bueno',
            'ubicacion' => 'Salón 01',
            'id_sede' => 2,
            'activo' => 1,
        ], $extra);
        $cols = array_keys($data);
        $sql = 'INSERT INTO inventario_general (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->conn->prepare($sql)->execute(array_values($data));
        return (int)$this->conn->lastInsertId();
    }

    private function crearToma(): int
    {
        return iniciarTomaFisica($this->conn, 2, 'Salón 01', $this->usuarioId);
    }

    /* ============ QR / BÚSQUEDA ============ */

    public function testParsearCodigoQRExtraeCodigoDeUrl(): void
    {
        $this->assertSame('20J-02-S001-001', parsearCodigoQR('http://localhost/mic/ver_articulo.php?codigo=20J-02-S001-001'));
        $this->assertSame('20J-02-S001-001', parsearCodigoQR('20J-02-S001-001'));
        $this->assertNull(parsearCodigoQR('   '));
    }

    public function testBuscarElementoPorCodigo(): void
    {
        $el = buscarElementoPorCodigo($this->conn, '20J-02-S001-001');
        $this->assertNotNull($el);
        $this->assertSame($this->elementoId, (int)$el['id']);
        $this->assertSame('El Porvenir', $el['sede_nombre']);
        $this->assertNull(buscarElementoPorCodigo($this->conn, '20J-99-XXXX-999'));
    }

    /* ============ TOMA FÍSICA ============ */

    public function testIniciarTomaFisicaCreaCabeceraYDetalle(): void
    {
        $this->crearElemento('20J-02-S001-002');
        $tomaId = $this->crearToma();
        $toma = $this->conn->query("SELECT * FROM tomas_fisicas WHERE id=$tomaId")->fetch();
        $this->assertSame('en_progreso', $toma['estado']);
        $this->assertSame(2, (int)$toma['sede_id']);
        $this->assertSame('Salón 01', $toma['ubicacion']);
        $this->assertSame(2, (int)$toma['total_esperados']);
        $det = $this->conn->query("SELECT * FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId ORDER BY id")->fetchAll();
        $this->assertCount(2, $det);
        $this->assertSame('bueno', $det[0]['estado_registrado']);
        $this->assertNull($det[0]['verificada_en']);
    }

    public function testIniciarTomaRechazaUbicacionDeOtraSede(): void
    {
        $this->expectException(RuntimeException::class);
        iniciarTomaFisica($this->conn, 2, 'Piscina', $this->usuarioId);
    }

    public function testIniciarTomaRechazaSedeInexistente(): void
    {
        $this->expectException(RuntimeException::class);
        iniciarTomaFisica($this->conn, 999, 'Salón 01', $this->usuarioId);
    }

    public function testIniciarTomaRechazaUbicacionSinActivos(): void
    {
        $this->expectException(RuntimeException::class);
        iniciarTomaFisica($this->conn, 1, 'Bodega Central', $this->usuarioId);
    }

    public function testVerificarElementoEncontrado(): void
    {
        $tomaId = $this->crearToma();
        $detalleId = (int)$this->conn->query("SELECT id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId LIMIT 1")->fetchColumn();
        verificarElementoEnToma($this->conn, $detalleId, [
            'encontrado' => true,
            'estado_encontrado' => 'bueno',
            'coincide_codigo' => true,
            'coincide_sede' => true,
            'coincide_ubicacion' => true,
            'coincide_responsable' => true,
        ], $this->usuarioId);

        $det = $this->conn->query("SELECT * FROM tomas_fisicas_detalle WHERE id=$detalleId")->fetch();
        $this->assertSame(1, (int)$det['encontrado']);
        $this->assertNotNull($det['verificada_en']);
        $this->assertSame('bueno', $det['estado_encontrado']);

        $hist = historialDeElemento($this->conn, $this->elementoId);
        $this->assertSame('inspeccion_fisica', $hist[0]['tipo_evento']);
        $this->assertSame('Admin TF', $hist[0]['usuario_nombre']);
    }

    public function testVerificarElementoNoEncontradoCambiaSituacion(): void
    {
        $tomaId = $this->crearToma();
        $detalleId = (int)$this->conn->query("SELECT id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId LIMIT 1")->fetchColumn();
        verificarElementoEnToma($this->conn, $detalleId, ['encontrado' => false, 'observacion' => 'No estaba en el salón'], $this->usuarioId);

        $situacion = $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$this->elementoId")->fetchColumn();
        $this->assertSame('no_encontrado', $situacion);
        $det = $this->conn->query("SELECT * FROM tomas_fisicas_detalle WHERE id=$detalleId")->fetch();
        $this->assertSame(0, (int)$det['encontrado']);
        $this->assertSame('No estaba en el salón', $det['observacion']);
    }

    public function testVerificarElementoConCambioDeEstado(): void
    {
        $tomaId = $this->crearToma();
        $detalleId = (int)$this->conn->query("SELECT id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId LIMIT 1")->fetchColumn();
        verificarElementoEnToma($this->conn, $detalleId, [
            'encontrado' => true,
            'estado_encontrado' => 'dañado',
            'cambiar_estado' => true,
        ], $this->usuarioId);

        $estado = $this->conn->query("SELECT estado FROM inventario_general WHERE id=$this->elementoId")->fetchColumn();
        $this->assertSame('dañado', $estado);
        $hist = historialDeElemento($this->conn, $this->elementoId);
        $this->assertStringContainsString('estado actualizado', $hist[0]['descripcion']);
    }

    public function testVerificarElementoConSituacionInvalida(): void
    {
        $tomaId = $this->crearToma();
        $detalleId = (int)$this->conn->query("SELECT id FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId LIMIT 1")->fetchColumn();
        $this->expectException(RuntimeException::class);
        verificarElementoEnToma($this->conn, $detalleId, ['encontrado' => true, 'situacion_despues' => 'inexistente'], $this->usuarioId);
    }

    public function testCambiarSituacionRegistraHistorial(): void
    {
        cambiarSituacion($this->conn, $this->elementoId, 'en_investigacion', $this->usuarioId, 'Se está investigando');
        $this->assertSame('en_investigacion', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$this->elementoId")->fetchColumn());
        $hist = historialDeElemento($this->conn, $this->elementoId);
        $this->assertSame('cambio_situacion', $hist[0]['tipo_evento']);

        cambiarSituacion($this->conn, $this->elementoId, 'en_investigacion', $this->usuarioId);
        $this->assertCount(1, historialDeElemento($this->conn, $this->elementoId));
    }

    public function testCambiarSituacionInvalidaLanzaExcepcion(): void
    {
        $this->expectException(RuntimeException::class);
        cambiarSituacion($this->conn, $this->elementoId, 'roto', $this->usuarioId);
    }

    public function testFinalizarTomaComputaResumen(): void
    {
        $tomaId = $this->crearToma();
        $detalles = $this->conn->query("SELECT * FROM tomas_fisicas_detalle WHERE toma_fisica_id=$tomaId ORDER BY id")->fetchAll();
        verificarElementoEnToma($this->conn, (int)$detalles[0]['id'], [
            'encontrado' => true, 'estado_encontrado' => 'dañado', 'cambiar_estado' => true,
        ], $this->usuarioId);
        finalizarTomaFisica($this->conn, $tomaId, 'Toma de prueba');

        $toma = $this->conn->query("SELECT * FROM tomas_fisicas WHERE id=$tomaId")->fetch();
        $this->assertSame('finalizada', $toma['estado']);
        $this->assertSame(1, (int)$toma['encontrados']);
        $this->assertSame(0, (int)$toma['no_encontrados']);
        $this->assertSame(1, (int)$toma['danados']);
        $this->assertNotNull($toma['finalizada_en']);
        $this->assertSame('Toma de prueba', $toma['observaciones']);
    }

    public function testFinalizarTomaSoloEnProgreso(): void
    {
        $tomaId = $this->crearToma();
        finalizarTomaFisica($this->conn, $tomaId);
        $this->expectException(RuntimeException::class);
        finalizarTomaFisica($this->conn, $tomaId);
    }

    public function testCancelarTomaSoloPorSuAutor(): void
    {
        $tomaId = $this->crearToma();
        $this->conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES ('Otro', 'otro@test.local', ?, 'admin', 1, 1)")
            ->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $otro = (int)$this->conn->lastInsertId();
        try {
            cancelarTomaFisica($this->conn, $tomaId, $otro);
            $this->fail('No debió cancelar la toma de otro usuario');
        } catch (RuntimeException $e) {
            $this->assertSame('en_progreso', $this->conn->query("SELECT estado FROM tomas_fisicas WHERE id=$tomaId")->fetchColumn());
        }
        cancelarTomaFisica($this->conn, $tomaId, $this->usuarioId);
        $this->assertSame('cancelada', $this->conn->query("SELECT estado FROM tomas_fisicas WHERE id=$tomaId")->fetchColumn());
    }

    /* ============ NOVEDADES ============ */

    public function testRegistrarNovedad(): void
    {
        $tomaId = $this->crearToma();
        $novedadId = registrarNovedad($this->conn, $this->elementoId, 'Daño físico', 'Pantalla rota', $this->usuarioId, $tomaId);
        $nov = $this->conn->query("SELECT * FROM novedades WHERE id=$novedadId")->fetch();
        $this->assertSame('Daño físico', $nov['tipo']);
        $this->assertSame('Pantalla rota', $nov['descripcion']);
        $this->assertSame('abierta', $nov['estado']);
        $this->assertSame($tomaId, (int)$nov['toma_fisica_id']);
        $hist = historialDeElemento($this->conn, $this->elementoId);
        $this->assertSame('novedad_registrada', $hist[0]['tipo_evento']);
    }

    public function testRegistrarNovedadSinDescripcionLanzaExcepcion(): void
    {
        $this->expectException(RuntimeException::class);
        registrarNovedad($this->conn, $this->elementoId, 'Daño físico', '  ', $this->usuarioId);
    }

    /* ============ MANTENIMIENTO ============ */

    public function testEnviarAMantenimiento(): void
    {
        $mtoId = enviarAMantenimiento($this->conn, $this->elementoId, [
            'descripcion' => 'Cambio de pantalla',
            'proveedor' => 'TecnoServicios',
            'tecnico' => 'Juan',
            'costo' => 250000,
        ], $this->usuarioId);

        $mto = $this->conn->query("SELECT * FROM mantenimiento WHERE id=$mtoId")->fetch();
        $this->assertSame($this->elementoId, (int)$mto['elemento_id']);
        $this->assertNull($mto['id_equipo']);
        $this->assertSame('programado', $mto['estado']);
        $this->assertSame('Cambio de pantalla', $mto['descripcion_trabajo']);
        $this->assertSame('TecnoServicios', $mto['proveedor']);
        $this->assertSame('en_mantenimiento', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$this->elementoId")->fetchColumn());
        $this->assertSame('mantenimiento_iniciado', historialDeElemento($this->conn, $this->elementoId)[0]['tipo_evento']);
    }

    public function testEnviarAMantenimientoActivoDadoDeBajaRechazado(): void
    {
        $this->conn->prepare("UPDATE inventario_general SET situacion='dado_de_baja' WHERE id=?")->execute([$this->elementoId]);
        $this->expectException(RuntimeException::class);
        enviarAMantenimiento($this->conn, $this->elementoId, ['descripcion' => 'x'], $this->usuarioId);
    }

    public function testFinalizarMantenimientoReparado(): void
    {
        $mtoId = enviarAMantenimiento($this->conn, $this->elementoId, ['descripcion' => 'Reparación'], $this->usuarioId);
        $this->conn->prepare("UPDATE inventario_general SET estado='dañado', situacion='en_mantenimiento' WHERE id=?")->execute([$this->elementoId]);

        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'Reparado', 'costo' => 120000], $this->usuarioId);

        $mto = $this->conn->query("SELECT * FROM mantenimiento WHERE id=$mtoId")->fetch();
        $this->assertSame('completado', $mto['estado']);
        $this->assertSame('Reparado', $mto['resultado']);
        $this->assertNotNull($mto['fecha_fin']);
        $el = $this->conn->query("SELECT estado, situacion FROM inventario_general WHERE id=$this->elementoId")->fetch();
        $this->assertSame('bueno', $el['estado']);
        $this->assertSame('disponible', $el['situacion']);
        $histFinal = historialDeElemento($this->conn, $this->elementoId);
        $this->assertSame('mantenimiento_finalizado', end($histFinal)['tipo_evento']);
    }

    public function testFinalizarMantenimientoNoReparable(): void
    {
        $mtoId = enviarAMantenimiento($this->conn, $this->elementoId, ['descripcion' => 'x'], $this->usuarioId);
        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'No reparable'], $this->usuarioId);
        $el = $this->conn->query("SELECT estado, situacion FROM inventario_general WHERE id=$this->elementoId")->fetch();
        $this->assertSame('dañado', $el['estado']);
        $this->assertSame('disponible', $el['situacion']);
    }

    public function testFinalizarMantenimientoResultadoInvalido(): void
    {
        $mtoId = enviarAMantenimiento($this->conn, $this->elementoId, ['descripcion' => 'x'], $this->usuarioId);
        $this->expectException(RuntimeException::class);
        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'A medio arreglar'], $this->usuarioId);
    }

    public function testFinalizarMantenimientoDosVecesRechazado(): void
    {
        $mtoId = enviarAMantenimiento($this->conn, $this->elementoId, ['descripcion' => 'x'], $this->usuarioId);
        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'Reparado'], $this->usuarioId);
        $this->expectException(RuntimeException::class);
        finalizarMantenimientoToma($this->conn, $mtoId, ['resultado' => 'Reparado'], $this->usuarioId);
    }

    /* ============ BAJAS ============ */

    public function testSolicitarBaja(): void
    {
        $bajaId = solicitarBaja($this->conn, $this->elementoId, 'Obsolescencia', date('Y-m-d'), 'Ya cumplió su vida útil', $this->usuarioId);
        $baja = $this->conn->query("SELECT * FROM bajas WHERE id=$bajaId")->fetch();
        $this->assertSame('solicitada', $baja['estado']);
        $this->assertSame('Obsolescencia', $baja['motivo']);
        $this->assertSame($this->usuarioId, (int)$baja['usuario_solicita']);
        $this->assertSame('baja_solicitada', historialDeElemento($this->conn, $this->elementoId)[0]['tipo_evento']);
        $this->assertSame('disponible', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$this->elementoId")->fetchColumn());
    }

    public function testSolicitarBajaDuplicadaRechazada(): void
    {
        solicitarBaja($this->conn, $this->elementoId, 'Obsolescencia', date('Y-m-d'), '', $this->usuarioId);
        $this->expectException(RuntimeException::class);
        solicitarBaja($this->conn, $this->elementoId, 'Pérdida', date('Y-m-d'), '', $this->usuarioId);
    }

    public function testSolicitarBajaMotivoInvalido(): void
    {
        $this->expectException(RuntimeException::class);
        solicitarBaja($this->conn, $this->elementoId, 'Porque sí', date('Y-m-d'), '', $this->usuarioId);
    }

    public function testAprobarBajaNoEliminaElActivo(): void
    {
        $bajaId = solicitarBaja($this->conn, $this->elementoId, 'Hurto', date('Y-m-d'), '', $this->usuarioId);
        aprobarBaja($this->conn, $bajaId, $this->usuarioId, 'Se autoriza');

        $baja = $this->conn->query("SELECT * FROM bajas WHERE id=$bajaId")->fetch();
        $this->assertSame('aprobada', $baja['estado']);
        $this->assertSame($this->usuarioId, (int)$baja['aprobado_por']);
        $this->assertSame('Se autoriza', $baja['observacion_aprobacion']);
        $this->assertSame('dado_de_baja', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$this->elementoId")->fetchColumn());
        $this->assertSame(1, (int)$this->conn->query("SELECT COUNT(*) FROM inventario_general WHERE id=$this->elementoId AND activo=1")->fetchColumn());
        $hist = historialDeElemento($this->conn, $this->elementoId);
        $this->assertSame('baja_aprobada', end($hist)['tipo_evento']);
    }

    public function testRechazarBaja(): void
    {
        $bajaId = solicitarBaja($this->conn, $this->elementoId, 'Donación', date('Y-m-d'), '', $this->usuarioId);
        rechazarBaja($this->conn, $bajaId, $this->usuarioId, 'Falta documentación');
        $this->assertSame('rechazada', $this->conn->query("SELECT estado FROM bajas WHERE id=$bajaId")->fetchColumn());
        $this->assertSame('disponible', $this->conn->query("SELECT situacion FROM inventario_general WHERE id=$this->elementoId")->fetchColumn());
        $hist = historialDeElemento($this->conn, $this->elementoId);
        $this->assertSame('baja_rechazada', end($hist)['tipo_evento']);
    }

    public function testAprobarBajaYaResueltaRechazada(): void
    {
        $bajaId = solicitarBaja($this->conn, $this->elementoId, 'Pérdida', date('Y-m-d'), '', $this->usuarioId);
        aprobarBaja($this->conn, $bajaId, $this->usuarioId);
        $this->expectException(RuntimeException::class);
        aprobarBaja($this->conn, $bajaId, $this->usuarioId);
    }

    /* ============ EVIDENCIAS ============ */

    public function testValidarEvidenciaArchivoValido(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ev_') . '.png';
        file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $res = validarEvidenciaSubida(['name' => 'foto.png', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
        $this->assertTrue($res['ok']);
        $this->assertSame('image/png', $res['mime']);
        unlink($tmp);
    }

    public function testValidarEvidenciaExtensionYFormatoRechazados(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ev_') . '.php';
        file_put_contents($tmp, '<?php echo "x"; ?>');
        $res = validarEvidenciaSubida(['name' => 'malo.php', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
        $this->assertFalse($res['ok']);
        unlink($tmp);

        $tmp2 = tempnam(sys_get_temp_dir(), 'ev_') . '.txt';
        file_put_contents($tmp2, 'hola');
        $res2 = validarEvidenciaSubida(['name' => 'doc.txt', 'tmp_name' => $tmp2, 'size' => filesize($tmp2), 'error' => UPLOAD_ERR_OK]);
        $this->assertFalse($res2['ok']);
        unlink($tmp2);
    }

    public function testValidarEvidenciaSuperaTamanoMaximo(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ev_') . '.png';
        file_put_contents($tmp, str_repeat('a', MAX_EVIDENCIA_SIZE + 1));
        $res = validarEvidenciaSubida(['name' => 'grande.png', 'tmp_name' => $tmp, 'size' => MAX_EVIDENCIA_SIZE + 1, 'error' => UPLOAD_ERR_OK]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('8 MB', $res['error']);
        unlink($tmp);
    }

    public function testRegistrarEvidenciasGuardaEnBD(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ev_') . '.png';
        file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $archivos = [
            'error' => [0 => UPLOAD_ERR_OK],
            'name' => [0 => 'foto.png'],
            'tmp_name' => [0 => $tmp],
            'size' => [0 => filesize($tmp)],
        ];
        $res = registrarEvidencias($this->conn, $archivos, 'inspeccion', 1, $this->usuarioId);
        $this->assertTrue($res['ok']);
        $this->assertSame(1, $res['guardadas']);
        $evs = evidenciasDeEntidad($this->conn, 'inspeccion', 1);
        $this->assertCount(1, $evs);
        $this->assertSame('evidencias/', substr($evs[0]['archivo'], 0, 11));
        $this->assertSame('Admin TF', $evs[0]['usuario_nombre']);
        @unlink($tmp);
    }

    public function testEliminarEvidencia(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ev_') . '.png';
        file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $archivos = [
            'error' => [0 => UPLOAD_ERR_OK],
            'name' => [0 => 'foto.png'],
            'tmp_name' => [0 => $tmp],
            'size' => [0 => filesize($tmp)],
        ];
        $res = registrarEvidencias($this->conn, $archivos, 'inspeccion', 1, $this->usuarioId);
        $evs = evidenciasDeEntidad($this->conn, 'inspeccion', 1);
        $archivoRuta = $evs[0]['archivo'];
        eliminarEvidencia($this->conn, (int)$evs[0]['id']);
        $this->assertCount(0, evidenciasDeEntidad($this->conn, 'inspeccion', 1));
        $this->assertFileDoesNotExist(__DIR__ . '/../../uploads/' . $archivoRuta);
        @unlink($tmp);
    }

    /* ============ CATÁLOGOS ============ */

    public function testCrearCategoriaTipoYEstado(): void
    {
        $nombre = 'Categoría TF Test ' . uniqid();
        $catId = crearCategoria($this->conn, $nombre);
        $this->assertGreaterThan(0, $catId);
        $this->assertSame($nombre, $this->conn->query("SELECT nombre FROM categorias WHERE id=$catId")->fetchColumn());

        $tipoId = crearTipo($this->conn, 'Tipo TF Test ' . uniqid(), 'Desc', $catId);
        $this->assertSame($catId, (int)$this->conn->query("SELECT categoria_id FROM tipo_equipo WHERE id=$tipoId")->fetchColumn());

        $nombreEstado = 'en prueba ' . uniqid();
        $estadoId = crearEstado($this->conn, $nombreEstado);
        $this->assertSame($nombreEstado, $this->conn->query("SELECT nombre FROM estados WHERE id=$estadoId")->fetchColumn());
    }

    public function testCrearCategoriaDuplicadaRechazada(): void
    {
        $nombre = 'Categoría Única ' . uniqid();
        crearCategoria($this->conn, $nombre);
        $this->expectException(RuntimeException::class);
        crearCategoria($this->conn, $nombre);
    }

    public function testCrearTipoConCategoriaInexistenteRechazado(): void
    {
        $this->expectException(RuntimeException::class);
        crearTipo($this->conn, 'Tipo Sin Categoría Válida', null, 999999);
    }

    public function testObtenerCatalogoInventarioBD(): void
    {
        $nombreCat = 'Cat Catálogo TF ' . uniqid();
        $catId = crearCategoria($this->conn, $nombreCat);
        crearTipo($this->conn, 'Tipo Catálogo TF ' . uniqid(), null, $catId);
        $catalogo = obtenerCatalogoInventarioBD($this->conn);
        $this->assertIsArray($catalogo);
        $this->assertArrayHasKey($nombreCat, $catalogo);
        $this->assertNotEmpty($catalogo[$nombreCat]);
    }

    /* ============ INFORME PDF ============ */

    public function testConstruirInformeTomaHTML(): void
    {
        $tomaId = $this->crearToma();
        $toma = obtenerToma($this->conn, $tomaId);
        $detalles = obtenerDetallesToma($this->conn, $tomaId);
        $html = construirInformeTomaHTML($toma, $detalles, [], $GLOBALS['institucion'], '');

        $this->assertStringContainsString('Informe de Toma Física e Inspección de Activos', $html);
        $this->assertStringContainsString('Institución Educativa 20 de Julio', $html);
        $this->assertStringContainsString('El Porvenir', $html);
        $this->assertStringContainsString('Salón 01', $html);
        $this->assertStringContainsString('Admin TF', $html);
        $this->assertStringContainsString('20J-02-S001-001', $html);
        $this->assertStringContainsString('firma-linea', $html);
        $this->assertStringContainsString('Encontrados', $html);
    }
}

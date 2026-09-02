<?php

/**
 * Manejo de la base de datos de pruebas (mic_test).
 * NUNCA conecta a la base de datos real "mic".
 */

final class TestDatabase
{
    private static ?PDO $pdo = null;

    public static function host(): string
    {
        return getenv('MIC_TEST_DB_HOST') ?: '127.0.0.1';
    }

    public static function dbName(): string
    {
        return getenv('MIC_TEST_DB_NAME') ?: 'mic_test';
    }

    public static function user(): string
    {
        return getenv('MIC_TEST_DB_USER') ?: 'root';
    }

    public static function pass(): string
    {
        return (string)getenv('MIC_TEST_DB_PASS');
    }

    /**
     * Conexión PDO exclusiva para pruebas (mic_test).
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = 'mysql:host=' . self::host() . ';dbname=' . self::dbName() . ';charset=utf8mb4';
        self::$pdo = new PDO($dsn, self::user(), self::pass(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }

    /**
     * Crea la base mic_test (si no existe) y carga el esquema real de MIC.
     * Se invoca una única vez desde tests/bootstrap.php.
     */
    public static function preparar(): void
    {
        $servidor = new PDO(
            'mysql:host=' . self::host() . ';charset=utf8mb4',
            self::user(),
            self::pass(),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $db = self::dbName();
        $existe = $servidor->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " . $servidor->quote($db))->fetchColumn();

        if (!$existe) {
            $servidor->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $pdo = self::pdo();
        $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        if (count($tablas) === 0) {
            self::cargarEsquema();
        }

        self::insertarDatosBase();
    }

    private static function cargarEsquema(): void
    {
        $archivo = __DIR__ . '/../../database/testing/esquema_mic_test.sql';
        if (!is_file($archivo)) {
            throw new RuntimeException('No existe el esquema de pruebas: database/testing/esquema_mic_test.sql');
        }
        $sql = file_get_contents($archivo);

        $mysqli = mysqli_connect(self::host(), self::user(), self::pass(), self::dbName());
        if (!$mysqli) {
            throw new RuntimeException('No se pudo conectar a mic_test para cargar el esquema: ' . mysqli_connect_error());
        }
        mysqli_set_charset($mysqli, 'utf8mb4');
        mysqli_query($mysqli, 'SET FOREIGN_KEY_CHECKS=0');
        if (!mysqli_multi_query($mysqli, $sql)) {
            throw new RuntimeException('Error cargando esquema: ' . mysqli_error($mysqli));
        }
        while (mysqli_more_results($mysqli)) {
            mysqli_next_result($mysqli);
        }
        mysqli_query($mysqli, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_close($mysqli);
    }

    /**
     * Datos base idénticos a la estructura real de MIC (sedes, roles,
     * configuración, catálogos) para que las pruebas tengan contexto real.
     */
    private static function insertarDatosBase(): void
    {
        $pdo = self::pdo();

        $sedes = $pdo->query('SELECT COUNT(*) FROM sedes')->fetchColumn();
        if ((int)$sedes === 0) {
            $pdo->exec("INSERT INTO sedes (id, codigo, nombre, direccion, capacidad, activo) VALUES
                (1, '01', 'Sede Principal', 'Calle 50 #40-20, Centro', 500, 1),
                (2, '02', 'El Porvenir', 'Carrera 45 #67-89, El Porvenir', 300, 1),
                (3, '03', 'El Progreso', 'Avenida 68 #12-34, El Progreso', 250, 1),
                (4, '04', 'Los Comodatos', 'Diagonal 23 #45-67, Los Comodatos', 200, 1),
                (5, '05', 'La Paz', '', 0, 1)");
        }

        $roles = $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
        if ((int)$roles === 0) {
            $pdo->exec("INSERT INTO roles (id, nombre, descripcion) VALUES
                (1, 'admin', 'Administrador del sistema - acceso total'),
                (2, 'coordinador', 'Coordinador - puede gestionar inventario'),
                (3, 'docente', 'Docente - puede solicitar equipos'),
                (4, 'estudiante', 'Estudiante - puede solicitar equipos')");
        }

        $config = $pdo->query('SELECT COUNT(*) FROM configuracion')->fetchColumn();
        if ((int)$config === 0) {
            $pdo->exec("INSERT INTO configuracion (clave, valor, tipo, descripcion) VALUES
                ('dias_prestamo_docente', '7', 'numero', 'Días máximos de préstamo para docentes'),
                ('dias_prestamo_estudiante', '3', 'numero', 'Días máximos de préstamo para estudiantes'),
                ('max_equipos_docente', '5', 'numero', 'Máximo de equipos que puede solicitar un docente'),
                ('max_equipos_estudiante', '2', 'numero', 'Máximo de equipos que puede solicitar un estudiante'),
                ('multa_dia_retraso', '2000', 'moneda', 'Valor de multa por día de retraso'),
                ('dias_alerta_garantia', '30', 'entero', 'Días previos al vencimiento de garantía para generar alerta')");
        }

        $tipos = $pdo->query('SELECT COUNT(*) FROM tipo_equipo')->fetchColumn();
        if ((int)$tipos === 0) {
            $pdo->exec("INSERT INTO tipo_equipo (id, nombre_tipo, descripcion) VALUES
                (1, 'Computador de Escritorio', 'Equipos de cómputo de escritorio'),
                (2, 'Portátil', 'Computadores portátiles'),
                (3, 'Tablet', 'Dispositivos móviles tipo tablet'),
                (4, 'Proyector', 'Equipos de proyección y video beam'),
                (5, 'Impresora', 'Equipos de impresión multifuncional')");
        }

        $categorias = $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
        if ((int)$categorias === 0) {
            $pdo->exec("INSERT INTO categorias (id, nombre, descripcion) VALUES
                (1, 'Académico', 'Equipos para uso académico en aulas'),
                (2, 'Administrativo', 'Equipos para uso administrativo'),
                (3, 'Laboratorio', 'Equipos para laboratorios especializados'),
                (4, 'Biblioteca', 'Equipos para sala de informática')");
        }

        $estados = $pdo->query('SELECT COUNT(*) FROM estados')->fetchColumn();
        if ((int)$estados === 0) {
            $pdo->exec("INSERT INTO estados (nombre, descripcion) VALUES
                ('bueno', 'El activo se encuentra en buen estado físico'),
                ('regular', 'El activo presenta desgaste o deterioro leve'),
                ('dañado', 'El activo presenta daños que requieren mantenimiento o evaluación'),
                ('fuera de servicio', 'El activo no puede ser utilizado'),
                ('nuevo', 'El activo es nuevo, sin uso'),
                ('malo', 'El activo presenta daños graves')");
        }
    }

    /**
     * Limpia todas las tablas de datos de prueba (mantiene los datos base).
     * Usado por la suite de integración entre pruebas.
     */
    public static function limpiarTablasTransaccionales(): void
    {
        $pdo = self::pdo();
        $tablas = [
            'auditoria', 'elemento_historial', 'acta_elementos', 'actas', 'inventario_general',
            'proveedores', 'profesores', 'mantenimiento', 'prestamos', 'prestamo_elementos', 'solicitudes', 'solicitud_elementos',
            'movimientos', 'equipos', 'notificaciones', 'feedback', 'inventario_dañados',
            'estudiantes', 'usuarios', 'tomas_fisicas_detalle', 'tomas_fisicas',
            'evidencias', 'novedades', 'bajas', 'prestamo_recordatorios'
        ];
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tablas as $t) {
            $pdo->exec("DELETE FROM `$t`");
            try {
                $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT=1");
            } catch (Throwable $e) {
                // vistas u otras tablas no soportan auto_increment; se ignora
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

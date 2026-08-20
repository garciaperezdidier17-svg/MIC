<?php

/**
 * Base común para pruebas de integración con base de datos.
 * Configura la conexión de pruebas (mic_test) y provee helpers
 * para crear datos reales del sistema MIC.
 */

abstract class TestCaseBase extends PHPUnit\Framework\TestCase
{
    protected PDO $conn;
    protected static bool $inicializado = false;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
    }

    /**
     * Crea un usuario (rol real: admin/profesor/estudiante) en mic_test.
     */
    protected function crearUsuario(string $nombre, string $rol = 'admin', int $rolId = 1): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, rol, rol_id, activo) VALUES (?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([$nombre, $nombre . '@test.local', password_hash('Test1234', PASSWORD_DEFAULT), $rol, $rolId]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Crea una sede de prueba o devuelve una existente por nombre.
     */
    protected function crearSede(string $nombre, string $codigo = null): int
    {
        $stmt = $this->conn->prepare('SELECT id FROM sedes WHERE nombre=? LIMIT 1');
        $stmt->execute([$nombre]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $stmt = $this->conn->prepare('INSERT INTO sedes (nombre, codigo, activo) VALUES (?, ?, 1)');
        $stmt->execute([$nombre, $codigo ?: '99']);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Crea un profesor activo vinculado a una sede.
     */
    protected function crearProfesor(string $nombre, string $apellido, int $sedeId, string $identificacion = null): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO profesores (nombre, apellido, identificacion, correo, sede_id, estado) VALUES (?, ?, ?, ?, ?, 'Activo')"
        );
        $stmt->execute([$nombre, $apellido, $identificacion, strtolower($nombre . $apellido) . '@test.edu.co', $sedeId]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Crea un proveedor activo.
     */
    protected function crearProveedor(string $nombre, string $nit = null, string $estado = 'Activo'): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO proveedores (nombre, nit, telefono, correo, direccion, estado) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nombre, $nit, '3000000000', strtolower(str_replace(' ', '', $nombre)) . '@test.com', 'Calle Test 123', $estado]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Crea un elemento en inventario_general con los campos reales del sistema.
     */
    protected function crearElemento(array $datos = []): int
    {
        $defaults = [
            'codigo_interno' => null,
            'nombre' => 'Computador de escritorio TEST',
            'tipo' => 'Computador de escritorio',
            'categoria' => 'Equipos de Cómputo',
            'marca' => 'Lenovo',
            'modelo' => 'T490',
            'numero_serie' => 'SN-' . random_int(100000, 999999),
            'estado' => 'bueno',
            'ubicacion' => 'Aula de Informática',
            'id_sede' => 1,
            'profesor_id' => null,
            'origen_bien' => 'Compra',
            'documento_no_disponible' => 0,
            'valor_compra' => 1000000.00,
            'vr_comercial' => 1000000.00,
            'vida_util' => 5,
            'fecha_ingreso' => date('Y-m-d'),
            'activo' => 1,
        ];
        $data = array_merge($defaults, $datos);

        $cols = ['codigo_interno','nombre','tipo','categoria','marca','modelo','numero_serie','estado','ubicacion',
                 'id_sede','profesor_id','origen_bien','documento_no_disponible','valor_compra','vr_comercial',
                 'vida_util','fecha_ingreso','activo'];
        $ins = [];
        $vals = [];
        foreach ($cols as $c) {
            $ins[] = $c;
            $vals[] = $data[$c];
        }
        $sql = 'INSERT INTO inventario_general (' . implode(',', $ins) . ') VALUES (' . implode(',', array_fill(0, count($ins), '?')) . ')';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($vals);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Crea un registro en equipos (módulo de préstamos).
     */
    protected function crearEquipo(array $datos = []): int
    {
        $defaults = [
            'codigo_interno' => 'EQ-' . random_int(1000, 9999),
            'nombre' => 'Portátil TEST',
            'numero_serie' => 'SER-' . random_int(100000, 999999),
            'estado' => 'disponible',
            'id_sede' => 1,
            'stock' => 1,
            'stock_minimo' => 1,
            'activo' => 1,
        ];
        $data = array_merge($defaults, $datos);
        $stmt = $this->conn->prepare(
            "INSERT INTO equipos (codigo_interno, nombre, numero_serie, estado, id_sede, stock, stock_minimo, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$data['codigo_interno'], $data['nombre'], $data['numero_serie'], $data['estado'], $data['id_sede'], $data['stock'], $data['stock_minimo'], $data['activo']]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Crea un estudiante (usuario + registro estudiantes).
     */
    protected function crearEstudiante(string $nombre): array
    {
        $usuarioId = $this->crearUsuario($nombre, 'estudiante', 4);
        $stmt = $this->conn->prepare(
            "INSERT INTO estudiantes (id_usuario, codigo_estudiante, grado, grupo, jornada, activo) VALUES (?, ?, 9, 'A', 'mañana', 1)"
        );
        $stmt->execute([$usuarioId, 'EST-' . random_int(1000, 9999)]);
        return ['usuario_id' => $usuarioId, 'estudiante_id' => (int)$this->conn->lastInsertId()];
    }
}

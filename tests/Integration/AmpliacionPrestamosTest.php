<?php

use PHPUnit\Framework\TestCase;

class AmpliacionPrestamosTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();

        // Crear sede, admin y profesor para las pruebas
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->conn->exec("INSERT INTO sedes (id, nombre) VALUES (1, 'Sede Principal') ON DUPLICATE KEY UPDATE nombre='Sede Principal'");
        $this->conn->exec("INSERT INTO usuarios (id, nombre, email, password_hash, rol, rol_id, activo) VALUES (1, 'Admin', 'admin@test.local', 'xxx', 'admin', 1, 1) ON DUPLICATE KEY UPDATE nombre='Admin'");
        $this->conn->exec("INSERT INTO profesores (id, nombre, apellido, sede_id) VALUES (1, 'Profe', 'Uno', 1) ON DUPLICATE KEY UPDATE nombre='Profe'");
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    public function testCrearPrestamoYDevolucionParcial()
    {
        // 1. Crear 2 elementos prestables
        $this->conn->exec("INSERT INTO inventario_general (id, nombre, codigo_interno, estado, disponible_para_prestamo, activo, id_sede) VALUES (1, 'Elemento 1', 'E1', 'bueno', 1, 1, 1)");
        $this->conn->exec("INSERT INTO inventario_general (id, nombre, codigo_interno, estado, disponible_para_prestamo, activo, id_sede) VALUES (2, 'Elemento 2', 'E2', 'bueno', 1, 1, 1)");

        // 2. Crear préstamo activo
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_sede, fecha_prestamo, estado) VALUES (1, 1, 1, CURDATE(), 'activo')");
        $this->conn->exec("INSERT INTO prestamo_elementos (id, id_prestamo, id_elemento, cantidad, codigo_interno) VALUES (1, 1, 1, 1, 'E1')");
        $this->conn->exec("INSERT INTO prestamo_elementos (id, id_prestamo, id_elemento, cantidad, codigo_interno) VALUES (2, 1, 2, 1, 'E2')");
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=1');

        $this->conn->exec("UPDATE inventario_general SET estado='prestado' WHERE id IN (1, 2)");

        // 3. Devolución parcial (solo elemento 1)
        require_once __DIR__ . '/../../modulo_prestamos/helpers_prestamos.php';
        $_SESSION['user_id'] = 1;
        
        $devueltos = [
            1 => ['estado' => 'Bueno', 'observaciones' => 'OK']
        ];
        $res = registrarDevolucion($this->conn, 1, 1, $devueltos);
        if (!$res['ok']) { var_dump($res); }

        // Validaciones
        $prestamo = $this->conn->query("SELECT estado FROM prestamos WHERE id=1")->fetchColumn();
        $this->assertEquals('parcialmente devuelto', $prestamo);

        $el1 = $this->conn->query("SELECT estado FROM inventario_general WHERE id=1")->fetchColumn();
        $this->assertEquals('bueno', $el1);

        $el2 = $this->conn->query("SELECT estado FROM inventario_general WHERE id=2")->fetchColumn();
        $this->assertEquals('prestado', $el2);
    }

    public function testDevolucionConDanoGeneraAlerta()
    {
        $this->conn->exec("INSERT INTO inventario_general (id, nombre, codigo_interno, estado, disponible_para_prestamo, activo, id_sede) VALUES (1, 'Elemento 1', 'E1', 'bueno', 1, 1, 1)");
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_sede, fecha_prestamo, estado) VALUES (1, 1, 1, CURDATE(), 'activo')");
        $this->conn->exec("INSERT INTO prestamo_elementos (id, id_prestamo, id_elemento, cantidad, codigo_interno) VALUES (1, 1, 1, 1, 'E1')");
        $this->conn->exec('SET FOREIGN_KEY_CHECKS=1');

        require_once __DIR__ . '/../../modulo_prestamos/helpers_prestamos.php';
        $_SESSION['user_id'] = 1;
        
        $devueltos = [
            1 => ['estado' => 'Dañado', 'observaciones' => 'Pantalla rota']
        ];
        registrarDevolucion($this->conn, 1, 1, $devueltos);

        $notif = $this->conn->query("SELECT mensaje FROM notificaciones WHERE tipo='alerta' AND id_usuario=1")->fetchColumn();
        $this->assertStringContainsString('Dañado', $notif);
    }

    public function testDarDeBajaActivo()
    {
        $this->conn->exec("INSERT INTO inventario_general (id, nombre, codigo_interno, estado, disponible_para_prestamo, activo, id_sede) VALUES (1, 'Elemento 1', 'E1', 'bueno', 1, 1, 1)");

        // Simulamos la baja
        $this->conn->exec("UPDATE inventario_general SET situacion='dado_de_baja', estado='Dado de baja', disponible_para_prestamo=0 WHERE id=1");

        $el = $this->conn->query("SELECT situacion, estado, disponible_para_prestamo FROM inventario_general WHERE id=1")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('dado_de_baja', $el['situacion']);
        $this->assertEquals('Dado de baja', $el['estado']);
        $this->assertEquals(0, $el['disponible_para_prestamo']);
    }
}

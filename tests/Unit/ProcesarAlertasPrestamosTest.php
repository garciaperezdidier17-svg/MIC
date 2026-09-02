<?php

use PHPUnit\Framework\TestCase;

class ProcesarAlertasPrestamosTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        date_default_timezone_set('America/Bogota');
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    public function testProcesoGeneraAlertasYActualizaEstado()
    {
        // 1. Preparar datos (préstamos en diferentes estados)
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Prestamo que vence en 3 dias (id 1)
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, estado) 
            VALUES (1, 1, NULL, '2026-08-01', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'activo')");
            
        // Prestamo que vence mañana (id 2)
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, estado) 
            VALUES (2, 1, NULL, '2026-08-01', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'activo')");
            
        // Prestamo que vence hoy (id 3)
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, estado) 
            VALUES (3, 1, NULL, '2026-08-01', CURDATE(), 'activo')");
            
        // Prestamo vencido ayer (id 4)
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, estado) 
            VALUES (4, 1, NULL, '2026-08-01', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'activo')");
            
        // Prestamo ya devuelto (debería ignorarse) (id 5)
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, estado) 
            VALUES (5, 1, NULL, '2026-08-01', CURDATE(), 'devuelto')");
            
        // Prestamo cancelado (debería ignorarse) (id 6)
        $this->conn->exec("INSERT INTO prestamos (id, id_profesor, id_estudiante, fecha_prestamo, fecha_devolucion_esperada, estado) 
            VALUES (6, 1, NULL, '2026-08-01', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'cancelado')");


        // 2. Ejecutar la función
        require_once __DIR__ . '/../../modulo_prestamos/helpers_prestamos.php';
        $res = procesarAlertasAutomaticasPrestamos($this->conn);

        // 3. Afirmaciones
        $this->assertEquals(4, $res['alertas_generadas']);
        $this->assertEquals(1, $res['prestamos_vencidos']);

        // Verificar prestamo_recordatorios
        $stmt = $this->conn->query("SELECT id_prestamo, tipo FROM prestamo_recordatorios ORDER BY id_prestamo");
        $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertCount(4, $recordatorios);
        $this->assertEquals(['id_prestamo' => 1, 'tipo' => '3_dias'], $recordatorios[0]);
        $this->assertEquals(['id_prestamo' => 2, 'tipo' => '1_dia'], $recordatorios[1]);
        $this->assertEquals(['id_prestamo' => 3, 'tipo' => 'hoy'], $recordatorios[2]);
        $this->assertEquals(['id_prestamo' => 4, 'tipo' => 'vencido'], $recordatorios[3]);
        
        // Verificar que el prestamo 4 cambió a 'vencido'
        $estadoP4 = $this->conn->query("SELECT estado FROM prestamos WHERE id = 4")->fetchColumn();
        $this->assertEquals('vencido', $estadoP4);
        
        // Verificar que los ignorados sigan igual
        $estadoP5 = $this->conn->query("SELECT estado FROM prestamos WHERE id = 5")->fetchColumn();
        $this->assertEquals('devuelto', $estadoP5);
        $estadoP6 = $this->conn->query("SELECT estado FROM prestamos WHERE id = 6")->fetchColumn();
        $this->assertEquals('cancelado', $estadoP6);

        
        // 4. Ejecutar de nuevo para ver duplicados
        $res2 = procesarAlertasAutomaticasPrestamos($this->conn);
        
        $this->assertEquals(0, $res2['alertas_generadas'], "No debe generar nuevas alertas");
        $this->assertEquals(0, $res2['prestamos_vencidos'], "No debe volver a marcar préstamos");
        
        $count = $this->conn->query("SELECT COUNT(*) FROM prestamo_recordatorios")->fetchColumn();
        $this->assertEquals(4, $count, "El total de recordatorios no debe aumentar");
    }
}

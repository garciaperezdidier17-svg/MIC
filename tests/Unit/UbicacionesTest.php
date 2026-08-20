<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de ubicaciones y pertenencia sede (helpers_inventario.php).
 * Funciones reales: ubicacionPerteneceSede, ubicacionValidaEnSede,
 * profesorPerteneceSede.
 */
class UbicacionesTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
    }

    protected function tearDown(): void
    {
        TestDatabase::limpiarTablasTransaccionales();
    }

    public function testUbicacionPerteneceASedePrincipal(): void
    {
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', 'Rectoría'));
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', 'Auditorio'));
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', 'Aula de Informática 2'));
    }

    public function testUbicacionNoPerteneceASede(): void
    {
        $this->assertFalse(ubicacionPerteneceSede('El Progreso', 'Aula de Informática 1'));
        $this->assertFalse(ubicacionPerteneceSede('La Paz', 'Cancha'));
    }

    public function testUbicacionPermitidaEnUnaSedePeroNoEnOtra(): void
    {
        $this->assertTrue(ubicacionPerteneceSede('El Porvenir', 'Aula de Informática'));
        $this->assertFalse(ubicacionPerteneceSede('Sede Principal', 'Aula de Informática'));
    }

    public function testUbicacionVaciaSiemprePertenece(): void
    {
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', ''));
        $this->assertTrue(ubicacionPerteneceSede('Sede Principal', null));
    }

    public function testUbicacionValidaEnSede(): void
    {
        $this->assertTrue(ubicacionValidaEnSede('Sede Principal', 'Bodega'));
        $this->assertFalse(ubicacionValidaEnSede('Sede Principal', 'Piscina'));
        $this->assertFalse(ubicacionValidaEnSede('Sede Principal', ''));
    }

    public function testProfesorPerteneceSede(): void
    {
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, identificacion, sede_id, estado) VALUES ('Ana', 'Lopez', '123', 1, 'Activo')")->execute();
        $idAna = (int)$this->conn->lastInsertId();
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, identificacion, sede_id, estado) VALUES ('Beto', 'Rios', '456', 2, 'Activo')")->execute();
        $idBeto = (int)$this->conn->lastInsertId();
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, identificacion, sede_id, estado) VALUES ('Caro', 'Diaz', '789', 3, 'Inactivo')")->execute();

        $this->assertTrue(profesorPerteneceSede($this->conn, $idAna, 1));
        $this->assertFalse(profesorPerteneceSede($this->conn, $idBeto, 1));
        $this->assertTrue(profesorPerteneceSede($this->conn, $idBeto, 2));
        $this->assertFalse(profesorPerteneceSede($this->conn, $idAna, 2));
    }

    public function testProfesorInactivoNoPertenece(): void
    {
        $this->conn->prepare("INSERT INTO profesores (nombre, apellido, identificacion, sede_id, estado) VALUES ('Caro', 'Diaz', '789', 3, 'Inactivo')")->execute();
        $id = (int)$this->conn->lastInsertId();
        $this->assertFalse(profesorPerteneceSede($this->conn, $id, 3));
    }

    public function testProfesorInexistenteNoPertenece(): void
    {
        $this->assertFalse(profesorPerteneceSede($this->conn, 99999, 1));
    }
}

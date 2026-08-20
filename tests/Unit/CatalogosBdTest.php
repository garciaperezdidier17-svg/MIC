<?php

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de catálogos administrables en BD (config/helpers_catalogos.php):
 * crear, editar y activar/desactivar categorías, tipos y estados; validación
 * de duplicados; pertenencia de tipos a categorías y mapa categoría → tipos.
 * Nunca se elimina físicamente un registro desactivado.
 */
class CatalogosBdTest extends TestCase
{
    private PDO $conn;
    private array $creados = ['categorias' => [], 'tipo_equipo' => [], 'estados' => []];

    protected function setUp(): void
    {
        $this->conn = TestDatabase::pdo();
        TestDatabase::limpiarTablasTransaccionales();
        $_SESSION = [];
        $this->creados = ['categorias' => [], 'tipo_equipo' => [], 'estados' => []];
    }

    protected function tearDown(): void
    {
        $pdo = $this->conn;
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->creados['estados'] as $id) {
            $pdo->prepare('DELETE FROM estados WHERE id=?')->execute([$id]);
        }
        foreach ($this->creados['tipo_equipo'] as $id) {
            $pdo->prepare('DELETE FROM tipo_equipo WHERE id=?')->execute([$id]);
        }
        foreach ($this->creados['categorias'] as $id) {
            $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$id]);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        TestDatabase::limpiarTablasTransaccionales();
    }

    private function rastrear(string $tabla, int $id): void
    {
        $this->creados[$tabla][] = $id;
    }

    public function testCatalogoTieneDatos(): void
    {
        $this->assertTrue(catalogoTieneDatos($this->conn));
    }

    public function testMapaTiposPorCategoriaTieneFormaCorrecta(): void
    {
        $mapa = catalogoMapaTiposPorCategoria($this->conn);
        $this->assertIsArray($mapa);
        $this->assertNotEmpty($mapa);
        foreach ($mapa as $categoria => $tipos) {
            $this->assertIsString($categoria);
            $this->assertIsArray($tipos);
            $this->assertNotEmpty($tipos);
        }
    }

    public function testCrearCategoriaInsertaYRechazaDuplicado(): void
    {
        $id = crearCategoriaCatalogo($this->conn, 'Categoría QA', 'Descripción QA');
        $this->rastrear('categorias', $id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('Categoría QA', $this->conn->query("SELECT nombre FROM categorias WHERE id=$id")->fetchColumn());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe');
        crearCategoriaCatalogo($this->conn, 'Categoría QA');
    }

    public function testCrearCategoriaInactivaSeReactivaAlRecrearla(): void
    {
        $id = crearCategoriaCatalogo($this->conn, 'Categoría QA Reactiva');
        $this->rastrear('categorias', $id);
        toggleCategoriaCatalogo($this->conn, $id, false);
        $this->assertSame(0, (int)$this->conn->query("SELECT activo FROM categorias WHERE id=$id")->fetchColumn());

        $mismoId = crearCategoriaCatalogo($this->conn, 'Categoría QA Reactiva');
        $this->assertSame($id, $mismoId);
        $this->assertSame(1, (int)$this->conn->query("SELECT activo FROM categorias WHERE id=$id")->fetchColumn());
    }

    public function testCrearCategoriaSinNombreRechazada(): void
    {
        $this->expectException(RuntimeException::class);
        crearCategoriaCatalogo($this->conn, '   ');
    }

    public function testCrearTipoPerteneceACategoria(): void
    {
        $catId = (int)$this->conn->query('SELECT id FROM categorias ORDER BY id LIMIT 1')->fetchColumn();
        $nombreTipo = 'Tipo QA ' . random_int(100, 999);
        $tipoId = crearTipoCatalogo($this->conn, $nombreTipo, $catId, 'Tipo de prueba');
        $this->rastrear('tipo_equipo', $tipoId);
        $this->assertSame($catId, (int)$this->conn->query("SELECT categoria_id FROM tipo_equipo WHERE id=$tipoId")->fetchColumn());

        $this->expectException(RuntimeException::class);
        crearTipoCatalogo($this->conn, $nombreTipo, $catId);
    }

    public function testCrearTipoConCategoriaInexistenteRechazado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no existe');
        crearTipoCatalogo($this->conn, 'Tipo QA Sin Categoria', 999999);
    }

    public function testCrearEstadoInsertaYRechazaDuplicado(): void
    {
        $id = crearEstadoCatalogo($this->conn, 'estado-qa-' . random_int(100, 999), 'Estado de prueba');
        $this->rastrear('estados', $id);
        $this->assertGreaterThan(0, $id);
        $nombre = $this->conn->query("SELECT nombre FROM estados WHERE id=$id")->fetchColumn();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe');
        crearEstadoCatalogo($this->conn, $nombre);
    }

    public function testEditarCategoriaActualizaYRechazaDuplicado(): void
    {
        $a = crearCategoriaCatalogo($this->conn, 'Categoría QA A');
        $b = crearCategoriaCatalogo($this->conn, 'Categoría QA B');
        $this->rastrear('categorias', $a);
        $this->rastrear('categorias', $b);

        editarCategoriaCatalogo($this->conn, $a, 'Categoría QA A Renombrada', 'Nueva descripción');
        $this->assertSame('Categoría QA A Renombrada', $this->conn->query("SELECT nombre FROM categorias WHERE id=$a")->fetchColumn());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe otra');
        editarCategoriaCatalogo($this->conn, $b, 'Categoría QA A Renombrada');
    }

    public function testEditarCategoriaInexistenteRechazada(): void
    {
        $this->expectException(RuntimeException::class);
        editarCategoriaCatalogo($this->conn, 999999, 'Cualquiera');
    }

    public function testToggleDesactivaSinEliminar(): void
    {
        $id = crearCategoriaCatalogo($this->conn, 'Categoría QA Toggle');
        $this->rastrear('categorias', $id);

        toggleCategoriaCatalogo($this->conn, $id, false);
        $this->assertSame(0, (int)$this->conn->query("SELECT activo FROM categorias WHERE id=$id")->fetchColumn());
        $this->assertNotNull($this->conn->query("SELECT id FROM categorias WHERE id=$id")->fetchColumn());

        $activas = array_column(catalogoCategorias($this->conn, true), 'id');
        $this->assertNotContains($id, $activas);
        $todas = array_column(catalogoCategorias($this->conn, false), 'id');
        $this->assertContains($id, $todas);

        toggleCategoriaCatalogo($this->conn, $id, true);
        $activas = array_column(catalogoCategorias($this->conn, true), 'id');
        $this->assertContains($id, $activas);
    }

    public function testEstadosInactivosNoAparecenEnSoloActivas(): void
    {
        $id = crearEstadoCatalogo($this->conn, 'estado-qa-toggle', 'Estado a desactivar');
        $this->rastrear('estados', $id);

        toggleEstadoCatalogo($this->conn, $id, false);
        $activos = array_column(catalogoEstados($this->conn, true), 'id');
        $this->assertNotContains($id, $activos);
        $todos = array_column(catalogoEstados($this->conn, false), 'id');
        $this->assertContains($id, $todos);
    }

    public function testTiposFiltradosPorCategoria(): void
    {
        $catId = (int)$this->conn->query('SELECT id FROM categorias ORDER BY id LIMIT 1')->fetchColumn();
        $tipoId = crearTipoCatalogo($this->conn, 'Tipo QA Filtro', $catId);
        $this->rastrear('tipo_equipo', $tipoId);

        $ids = array_column(catalogoTipos($this->conn, $catId, false), 'id');
        $this->assertContains($tipoId, $ids);
        $this->assertSame([], catalogoTipos($this->conn, 999999, false));
    }

    public function testMapaIncluyeTipoBajoSuCategoria(): void
    {
        $nombreCat = 'Categoría QA Mapa';
        $nombreTipo = 'Tipo QA Mapa';
        $catId = crearCategoriaCatalogo($this->conn, $nombreCat, 'Para prueba de mapa');
        $this->rastrear('categorias', $catId);
        $tipoId = crearTipoCatalogo($this->conn, $nombreTipo, $catId);
        $this->rastrear('tipo_equipo', $tipoId);

        $mapa = catalogoMapaTiposPorCategoria($this->conn);
        $this->assertIsArray($mapa[$nombreCat] ?? null, "La categoría '$nombreCat' debería existir en el mapa");
        $this->assertContains($nombreTipo, $mapa[$nombreCat]);
    }
}
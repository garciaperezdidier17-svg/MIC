<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/error_handler.php';

$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if (basename($scriptDir) === 'actions') { $scriptDir = dirname($scriptDir); }
elseif (basename($scriptDir) === 'modulo_inventario' || basename($scriptDir) === 'modulo_inventario_general' || basename($scriptDir) === 'modulo_prestamos' || basename($scriptDir) === 'modulo_dashboard' || basename($scriptDir) === 'modulo_login' || basename($scriptDir) === 'modulo_reportes' || basename($scriptDir) === 'modulo_usuarios' || basename($scriptDir) === 'modulo_sedes' || basename($scriptDir) === 'modulo_toma_fisica' || basename($scriptDir) === 'modulo_auditoria') {
    $scriptDir = dirname($scriptDir);
}
define('BASE_URL', rtrim($scriptDir, '/') . '/');

$host = 'localhost';
$dbname = 'mic';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    logError("Error de conexión BD: " . $e->getMessage());
    mostrarErrorAmigable(
        'Error de Conexión',
        'No se pudo conectar a la base de datos. Por favor intenta más tarde o contacta al administrador.'
    );
}

function estaLogueado() {
    return isset($_SESSION['user_id']);
}

function esAdmin() {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin';
}

function obtenerUsuarioActual() {
    if (!estaLogueado()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_nombre'],
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

function contarRegistros($tabla, $where = '') {
    global $conn;
    $tablasPermitidas = ['equipos', 'prestamos', 'usuarios', 'solicitudes', 'inventario_general'];
    if (!in_array($tabla, $tablasPermitidas)) {
        return 0;
    }
    try {
        return $conn->query("SELECT COUNT(*) FROM `$tabla` $where")->fetchColumn();
    } catch(Exception $e) {
        return 0;
    }
}

function generarTokenCSRF() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function validarTokenCSRF($token) {
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function campoCSRF() {
    return '<input type="hidden" name="_csrf_token" value="' . generarTokenCSRF() . '">';
}

function verificarCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? '';
        if (!validarTokenCSRF($token)) {
            logError("Intento de CSRF detectado");
            header('HTTP/1.1 403 Forbidden');
            die('Error de seguridad: Token CSRF inválido.');
        }
    }
}

function obtenerLogo() {
    $ruta = __DIR__ . '/../uploads/';
    $archivos = glob($ruta . 'logo.*');
    if (is_array($archivos)) {
        foreach ($archivos as $f) {
            $ext = pathinfo($f, PATHINFO_EXTENSION);
            return 'uploads/logo.' . $ext;
        }
    }
    return null;
}

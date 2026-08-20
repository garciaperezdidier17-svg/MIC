<?php

/**
 * Réplicas EXACTAS de las funciones de sesión y CSRF definidas en
 * config/conexion.php. NO se incluye config/conexion.php porque conecta
 * a la base de datos real "mic"; estas réplicas mantienen la misma lógica
 * para permitir pruebas de permisos sin tocar producción.
 */

function estaLogueado(): bool
{
    return isset($_SESSION['user_id']);
}

function esAdmin(): bool
{
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin';
}

function generarTokenCSRF(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function validarTokenCSRF(string $token): bool
{
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function campoCSRF(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . generarTokenCSRF() . '">';
}

function verificarCSRF(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $token = $_POST['_csrf_token'] ?? '';
        if (!validarTokenCSRF($token)) {
            logError('Intento de CSRF detectado');
            header('HTTP/1.1 403 Forbidden');
            throw new RuntimeException('Error de seguridad: Token CSRF inválido.');
        }
    }
}

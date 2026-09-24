<?php
/**
 * Catálogo de ubicaciones por sede desde la Base de Datos.
 */
require_once __DIR__ . '/conexion.php';

$ubicacionesArray = [];

try {
    // Si la conexión existe y las tablas ya fueron migradas
    global $conn;
    if ($conn) {
        $stmt = $conn->query("SHOW TABLES LIKE 'ubicaciones'");
        if ($stmt->rowCount() > 0) {
            $stmtSedes = $conn->query("SELECT id, nombre FROM sedes");
            $sedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sedes as $sede) {
                $stmtUbi = $conn->prepare("SELECT codigo, nombre FROM ubicaciones WHERE id_sede = ? AND estado = 1");
                $stmtUbi->execute([$sede['id']]);
                $ubis = $stmtUbi->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($ubis)) {
                    $ubicacionesArray[$sede['nombre']] = [
                        'codigo' => str_pad($sede['id'], 2, '0', STR_PAD_LEFT), // fallback code
                        'ubicaciones' => $ubis
                    ];
                }
            }
            return $ubicacionesArray;
        }
    }
} catch (Exception $e) {
    // Fallback if not configured
}

return $ubicacionesArray;

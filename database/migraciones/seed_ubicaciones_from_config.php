<?php
require_once __DIR__ . '/../../config/conexion.php';

$ubicacionesConfig = require __DIR__ . '/../../config/ubicaciones.php';

try {
    $conn->beginTransaction();

    foreach ($ubicacionesConfig as $sedeNombre => $datos) {
        // Buscar si existe la sede
        $stmtSede = $conn->prepare("SELECT id FROM sedes WHERE nombre = ?");
        $stmtSede->execute([$sedeNombre]);
        $sedeId = $stmtSede->fetchColumn();

        if (!$sedeId) {
            // Crear la sede si no existe
            $stmtSedeInsert = $conn->prepare("INSERT INTO sedes (nombre) VALUES (?)");
            $stmtSedeInsert->execute([$sedeNombre]);
            $sedeId = $conn->lastInsertId();
        }

        // Insertar ubicaciones
        foreach ($datos['ubicaciones'] as $ubicacion) {
            // Verificar si ya existe
            $stmtUbicacion = $conn->prepare("SELECT id FROM ubicaciones WHERE id_sede = ? AND codigo = ?");
            $stmtUbicacion->execute([$sedeId, $ubicacion['codigo']]);
            
            if (!$stmtUbicacion->fetchColumn()) {
                $stmtUbiInsert = $conn->prepare("INSERT INTO ubicaciones (id_sede, codigo, nombre) VALUES (?, ?, ?)");
                $stmtUbiInsert->execute([$sedeId, $ubicacion['codigo'], $ubicacion['nombre']]);
            }
        }
    }

    $conn->commit();
    echo "Migración de ubicaciones completada exitosamente.\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Error en la migración: " . $e->getMessage() . "\n";
}

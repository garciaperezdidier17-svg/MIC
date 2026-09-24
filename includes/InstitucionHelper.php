<?php
require_once __DIR__ . '/../config/conexion.php';

class InstitucionHelper {
    private static $institucion = null;

    public static function get() {
        if (self::$institucion === null) {
            global $conn;
            if (!$conn) {
                // Si conn no está globalmente disponible, intentar recrearlo
                include __DIR__ . '/../config/conexion.php';
            }

            try {
                // Verificar si la tabla existe primero (para no romper tests/instalación)
                $stmt = $conn->query("SHOW TABLES LIKE 'instituciones'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $conn->query("SELECT * FROM instituciones LIMIT 1");
                    self::$institucion = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                // Ignorar error de BD si no está configurada aún
            }

            // Fallback si no existe en BD
            if (!self::$institucion) {
                self::$institucion = [
                    'nombre' => 'MIC - Configuración Pendiente',
                    'nombre_corto' => 'MIC',
                    'nit' => '',
                    'logo' => 'assets/img/logo.png',
                    'prefijo_inventario' => 'MIC',
                    'formato_codigo' => '{INSTITUCION}-{SEDE}-{UBICACION}-{CONSECUTIVO}'
                ];
            }
        }
        return self::$institucion;
    }

    public static function getPrefijo() {
        $inst = self::get();
        return $inst['prefijo_inventario'] ?? 'MIC';
    }

    public static function generarCodigoInventario($idSede = null, $idUbicacion = null) {
        $inst = self::get();
        $formato = $inst['formato_codigo'] ?? '{INSTITUCION}-{SEDE}-{UBICACION}-{CONSECUTIVO}';
        
        global $conn;
        if (!$conn) {
            include __DIR__ . '/../config/conexion.php';
        }

        // Obtener códigos de sede y ubicación
        $sedeCode = '00';
        $ubiCode = '00';

        if ($idSede) {
            $s = $conn->prepare("SELECT id FROM sedes WHERE id = ?");
            $s->execute([$idSede]);
            $res = $s->fetchColumn();
            if ($res) $sedeCode = str_pad($res, 2, '0', STR_PAD_LEFT);
        }

        if ($idUbicacion) {
            $u = $conn->prepare("SELECT codigo FROM ubicaciones WHERE id = ?");
            $u->execute([$idUbicacion]);
            $res = $u->fetchColumn();
            if ($res) $ubiCode = $res;
        }

        // Obtener siguiente consecutivo general o por sede
        $c = $conn->query("SELECT MAX(id) FROM equipos");
        $maxId = $c->fetchColumn();
        $consecutivo = str_pad(($maxId ? $maxId + 1 : 1), 4, '0', STR_PAD_LEFT);

        $codigo = str_replace(
            ['{INSTITUCION}', '{SEDE}', '{UBICACION}', '{CONSECUTIVO}'],
            [$inst['prefijo_inventario'] ?? 'MIC', $sedeCode, $ubiCode, $consecutivo],
            $formato
        );

        return $codigo;
    }
}

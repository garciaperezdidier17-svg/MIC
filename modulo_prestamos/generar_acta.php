<?php
require_once '../config/conexion.php';
require_once '../vendor/autoload.php';
if (!estaLogueado() || !esAdmin()) { header('Location: ../modulo_login/index.php'); exit; }

use Mpdf\Mpdf;
require_once __DIR__ . '/helpers_actas.php';
require_once __DIR__ . '/helpers_prestamos.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = isset($_GET['tipo']) && $_GET['tipo'] === 'devolucion' ? 'devolucion' : 'entrega';

if (!$id) { die('ID no válido'); }

$stmt = $conn->prepare("SELECT p.*, pr.nombre as profesor_nombre, pr.apellido as profesor_apellido, s.nombre as sede_nombre, u.nombre as estudiante_nombre
                            FROM prestamos p
                            LEFT JOIN profesores pr ON p.id_profesor = pr.id
                            LEFT JOIN sedes s ON p.id_sede = s.id
                            LEFT JOIN usuarios u ON p.id_estudiante = u.id
                            WHERE p.id = ?");
$stmt->execute([$id]);
$prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prestamo) {
    die('Préstamo no encontrado.');
}

$elementos = elementosDePrestamo($conn, $id);

$institucion = require __DIR__ . '/../config/institucion.php';
$logoPath = function_exists('obtenerLogo') && obtenerLogo() ? __DIR__ . '/../' . obtenerLogo() : '';

$mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 14, 'margin_right' => 14]);
$html = construirActaPrestamoHTML($institucion, $prestamo, $elementos, $logoPath, $tipo === 'devolucion');
$mpdf->WriteHTML($html);

$nombrePdf = $tipo === 'devolucion' ? 'Acta_Devolucion_Prestamo_' . $id . '.pdf' : 'Acta_Entrega_Prestamo_' . $id . '.pdf';
$mpdf->Output($nombrePdf, \Mpdf\Output\Destination::INLINE);

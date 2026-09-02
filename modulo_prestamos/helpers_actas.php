<?php
function construirActaPrestamoHTML($institucion, $prestamo, $elementos, $logoPath, $esDevolucion = false) {
    $titulo = $esDevolucion ? 'Acta de Devolución de Bienes' : 'Acta de Préstamo de Bienes';
    
    $html = '<html><head><meta charset="utf-8"><style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #1e293b; }
        .encabezado { width: 100%; border-bottom: 3px solid #1a237e; padding-bottom: 10px; margin-bottom: 14px; }
        .encabezado table { width: 100%; }
        .logo { width: 70px; }
        .inst-nombre { font-size: 15pt; font-weight: bold; color: #1a237e; }
        .inst-codigo { font-size: 9pt; color: #555; }
        h1.titulo { font-size: 13pt; text-align: center; color: #1a237e; margin: 18px 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        .subtitulo { text-align: center; font-size: 9pt; color: #555; margin-bottom: 18px; }
        .seccion { background: #1a237e; color: #fff; font-weight: bold; font-size: 10pt; padding: 4px 8px; margin: 14px 0 8px; }
        .datos td { padding: 4px 6px; vertical-align: top; }
        .etiqueta { color: #64748b; font-size: 8.5pt; width: 130px; font-weight:bold;}
        table.activos { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.activos th { background: #eef2ff; color: #1a237e; font-size: 8.5pt; padding: 5px 6px; border: 1px solid #c7d2fe; text-align: left; }
        table.activos td { font-size: 8.5pt; padding: 4px 6px; border: 1px solid #dde3f5; }
        .firmas { margin-top: 50px; width: 100%; }
        .firmas td { width: 50%; text-align: center; font-size: 9pt; }
        .firma-linea { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; display:inline-block; width:250px;}
        .footer { margin-top: 30px; font-size: 7.5pt; color: #94a3b8; text-align: center; }
    </style></head><body>';

    $html .= '<div class="encabezado"><table><tr>';
    if ($logoPath) { $html .= '<td style="width:80px;"><img class="logo" src="' . $logoPath . '"></td>'; }
    $html .= '<td><div class="inst-nombre">' . htmlspecialchars($institucion['nombre']) . '</div>';
    $html .= '<div class="inst-codigo">Código de la institución: ' . htmlspecialchars($institucion['codigo']) . '</div></td>';
    $html .= '</tr></table></div>';

    $html .= '<h1 class="titulo">' . $titulo . '</h1>';
    $html .= '<div class="subtitulo">Préstamo #' . $prestamo['id'] . ' — Documento generado el ' . date('d/m/Y H:i') . '</div>';

    $html .= '<div class="seccion">DATOS DEL PRÉSTAMO</div>';
    $html .= '<table class="datos">';
    $html .= '<tr><td class="etiqueta">Responsable:</td><td>' . htmlspecialchars(trim(($prestamo['profesor_nombre'] ?? '') . ' ' . ($prestamo['profesor_apellido'] ?? '')) ?: ($prestamo['estudiante_nombre'] ?? '')) . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Sede:</td><td>' . htmlspecialchars($prestamo['sede_nombre'] ?? 'No disponible') . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Motivo:</td><td>' . htmlspecialchars($prestamo['motivo']) . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Fecha préstamo:</td><td>' . date('d/m/Y', strtotime($prestamo['fecha_prestamo'])) . '</td></tr>';
    
    if ($esDevolucion && !empty($prestamo['fecha_devolucion_real'])) {
        $html .= '<tr><td class="etiqueta">Fecha devolución real:</td><td>' . date('d/m/Y', strtotime($prestamo['fecha_devolucion_real'])) . '</td></tr>';
    } else {
        $html .= '<tr><td class="etiqueta">Devolución esperada:</td><td>' . ($prestamo['fecha_devolucion_esperada'] ? date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])) : 'No especificada') . '</td></tr>';
    }
    
    $html .= '</table>';

    $html .= '<div class="seccion">ELEMENTOS ' . ($esDevolucion ? 'DEVUELTOS' : 'PRESTADOS') . ' (' . count($elementos) . ')</div>';
    $html .= '<table class="activos"><thead><tr><th>Código</th><th>Nombre</th><th>Estado Asignación</th>';
    if ($esDevolucion) {
        $html .= '<th>Estado Devolución</th><th>Observaciones</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach ($elementos as $el) {
        // Skip elements not returned if it's a return receipt
        if ($esDevolucion && empty($el['estado_devolucion'])) { continue; }
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($el['codigo_ig'] ?? ('#' . $el['id_elemento'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($el['nombre']) . '</td>';
        $html .= '<td>' . htmlspecialchars(ucfirst($el['estado_activo'] ?? 'Bueno')) . '</td>';
        
        if ($esDevolucion) {
            $html .= '<td>' . htmlspecialchars($el['estado_devolucion'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($el['observaciones_devolucion'] ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $html .= '<table class="firmas"><tr>';
    $html .= '<td><div class="firma-linea">Recibe<br>' . ($esDevolucion ? 'Administrador de Inventario' : htmlspecialchars(trim(($prestamo['profesor_nombre'] ?? '') . ' ' . ($prestamo['profesor_apellido'] ?? '')))) . '</div></td>';
    $html .= '<td><div class="firma-linea">Entrega<br>' . ($esDevolucion ? htmlspecialchars(trim(($prestamo['profesor_nombre'] ?? '') . ' ' . ($prestamo['profesor_apellido'] ?? ''))) : 'Administrador de Inventario') . '</div></td>';
    $html .= '</tr></table>';

    $html .= '<div class="footer">Sistema MIC — ' . htmlspecialchars($institucion['nombre']) . ' — Documento generado el ' . date('d/m/Y H:i:s') . '</div>';
    $html .= '</body></html>';
    return $html;
}

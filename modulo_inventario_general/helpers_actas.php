<?php
function ubicacionPerteneceSedeActa($catalogosUbicaciones, $sedeNombre, $ubicacion) {
    if (!$ubicacion) return true;
    $data = $catalogosUbicaciones[$sedeNombre] ?? null;
    if (!$data) return true;
    foreach ($data['ubicaciones'] as $u) {
        if ($u['nombre'] === $ubicacion) return true;
    }
    return false;
}

function construirActaHTML($institucion, $profesor, $sedeNombre, $elementos, $elementosUbicaciones, $logoPath) {
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
        .datos td { padding: 2px 6px; vertical-align: top; }
        .etiqueta { color: #64748b; font-size: 8.5pt; width: 130px; }
        table.activos { width: 100%; border-collapse: collapse; }
        table.activos th { background: #eef2ff; color: #1a237e; font-size: 8.5pt; padding: 5px 6px; border: 1px solid #c7d2fe; text-align: left; }
        table.activos td { font-size: 8.5pt; padding: 4px 6px; border: 1px solid #dde3f5; }
        table.activos tr:nth-child(even) td { background: #f8fafc; }
        .qr-grid td { padding: 6px; text-align: center; border: 1px solid #e2e8f0; }
        .qr-grid img { width: 22mm; }
        .qr-grid .qr-codigo { font-size: 7pt; font-weight: bold; }
        .firmas { margin-top: 34px; width: 100%; }
        .firmas td { width: 50%; text-align: center; font-size: 9pt; }
        .firma-linea { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
        .footer { margin-top: 20px; font-size: 7.5pt; color: #94a3b8; text-align: center; }
    </style></head><body>';

    $html .= '<div class="encabezado"><table><tr>';
    if ($logoPath) {
        $html .= '<td style="width:80px;"><img class="logo" src="' . $logoPath . '"></td>';
    }
    $html .= '<td><div class="inst-nombre">' . htmlspecialchars($institucion['nombre']) . '</div>';
    $html .= '<div class="inst-codigo">Código de la institución: ' . htmlspecialchars($institucion['codigo']) . '</div></td>';
    $html .= '</tr></table></div>';

    $html .= '<h1 class="titulo">Acta de Entrega y Responsabilidad de Bienes</h1>';
    $html .= '<div class="subtitulo">Documento generado por el Sistema de Inventario y Control (MIC) — ' . date('d/m/Y H:i') . '</div>';

    $html .= '<div class="seccion">DATOS DE LA SEDE</div>';
    $html .= '<table class="datos"><tr><td class="etiqueta">Sede:</td><td>' . htmlspecialchars($sedeNombre) . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Ubicación(es):</td><td>' . htmlspecialchars($elementosUbicaciones ? implode(' — ', array_unique($elementosUbicaciones)) : 'No registrada') . '</td></tr></table>';

    $html .= '<div class="seccion">DATOS DEL RESPONSABLE</div>';
    $html .= '<table class="datos">';
    $html .= '<tr><td class="etiqueta">Nombre completo:</td><td>' . htmlspecialchars(trim($profesor['nombre'] . ' ' . $profesor['apellido'])) . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Documento de identidad:</td><td>' . (!empty($profesor['identificacion']) ? htmlspecialchars($profesor['identificacion']) : 'No disponible') . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Correo:</td><td>' . (!empty($profesor['correo']) ? htmlspecialchars($profesor['correo']) : 'No disponible') . '</td></tr>';
    $html .= '<tr><td class="etiqueta">Sede:</td><td>' . htmlspecialchars($sedeNombre) . '</td></tr>';
    $html .= '</table>';

    $html .= '<div class="seccion">DATOS DE LOS ACTIVOS (' . count($elementos) . ')</div>';
    $html .= '<table class="activos"><thead><tr><th>Código</th><th>Elemento</th><th>Marca</th><th>Serial</th><th>Estado</th><th>Valor</th></tr></thead><tbody>';
    foreach ($elementos as $el) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($el['codigo_interno'] ?? ('#' . $el['id'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($el['nombre']);
        if (!empty($el['tipo'])) $html .= '<br><small style="color:#64748b;">' . htmlspecialchars($el['tipo']) . ' · ' . htmlspecialchars($el['categoria'] ?? 'Sin categoría') . '</small>';
        $html .= '</td>';
        $html .= '<td>' . htmlspecialchars($el['marca'] ?? 'No disponible') . '</td>';
        $html .= '<td>' . htmlspecialchars($el['numero_serie'] ?? 'No disponible') . '</td>';
        $html .= '<td>' . htmlspecialchars(ucfirst($el['estado'] ?? '')) . '</td>';
        $html .= '<td>' . ($el['vr_comercial'] ? '$' . number_format((float)$el['vr_comercial'], 0) : 'No disponible') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $html .= '<div class="seccion">DATOS DE ADQUISICIÓN</div>';
    foreach ($elementos as $el) {
        $origen = $el['origen_bien'] ?? null;
        $html .= '<table class="datos" style="border:1px solid #e2e8f0;margin-bottom:6px;">';
        $html .= '<tr><td class="etiqueta">Elemento:</td><td><strong>' . htmlspecialchars($el['codigo_interno'] ?? ('#' . $el['id'])) . '</strong> — ' . htmlspecialchars($el['nombre']) . '</td></tr>';
        $html .= '<tr><td class="etiqueta">Origen del bien:</td><td>' . ($origen ? htmlspecialchars($origen) : 'No registrado') . '</td></tr>';
        if ($origen === 'Compra') {
            $html .= '<tr><td class="etiqueta">Proveedor:</td><td>' . (!empty($el['proveedor_nombre']) ? htmlspecialchars($el['proveedor_nombre']) : 'No aplica') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">NIT del proveedor:</td><td>' . (!empty($el['proveedor_nit']) ? htmlspecialchars($el['proveedor_nit']) : 'No aplica') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">Número de factura:</td><td>' . (!empty($el['numero_factura']) ? htmlspecialchars($el['numero_factura']) : 'No disponible') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">Fecha de compra:</td><td>' . (!empty($el['fecha_compra']) ? date('d/m/Y', strtotime($el['fecha_compra'])) : 'No disponible') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">Valor de compra:</td><td>' . ($el['valor_compra'] ? '$' . number_format((float)$el['valor_compra'], 0) : 'No disponible') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">Garantía (vence):</td><td>' . (!empty($el['fecha_garantia']) ? date('d/m/Y', strtotime($el['fecha_garantia'])) : 'No disponible') . '</td></tr>';
        } elseif ($origen === 'Donación') {
            $html .= '<tr><td class="etiqueta">Donante:</td><td>' . (!empty($el['donante_nombre']) ? htmlspecialchars($el['donante_nombre']) : 'No disponible') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">Fecha de donación:</td><td>' . (!empty($el['fecha_donacion']) ? date('d/m/Y', strtotime($el['fecha_donacion'])) : 'No disponible') . '</td></tr>';
        } elseif ($origen === 'Transferencia') {
            $html .= '<tr><td class="etiqueta">Institución de origen:</td><td>' . (!empty($el['institucion_origen']) ? htmlspecialchars($el['institucion_origen']) : 'No disponible') . '</td></tr>';
            $html .= '<tr><td class="etiqueta">Fecha de transferencia:</td><td>' . (!empty($el['fecha_transferencia']) ? date('d/m/Y', strtotime($el['fecha_transferencia'])) : 'No disponible') . '</td></tr>';
        } elseif ($origen === 'Otro') {
            $html .= '<tr><td class="etiqueta">Descripción del origen:</td><td>' . (!empty($el['descripcion_origen']) ? htmlspecialchars($el['descripcion_origen']) : 'No disponible') . '</td></tr>';
        }
        $estadoDoc = !empty($el['documento_adquisicion']) ? 'Disponible' : 'No disponible';
        $html .= '<tr><td class="etiqueta">Estado del documento:</td><td>' . $estadoDoc . '</td></tr>';
        $html .= '</table>';
    }

    $html .= '<div class="seccion">CÓDIGOS QR DE LOS ACTIVOS</div>';
    $html .= '<table class="qr-grid"><tr>';
    $count = 0;
    foreach ($elementos as $el) {
        $qr = !empty($el['qr_path']) ? (__DIR__ . '/../assets/' . $el['qr_path']) : '';
        $html .= '<td>';
        if ($qr && is_file($qr)) {
            $html .= '<img src="' . $qr . '"><br>';
        }
        $html .= '<span class="qr-codigo">' . htmlspecialchars($el['codigo_interno'] ?? ('#' . $el['id'])) . '</span></td>';
        $count++;
        if ($count % 4 === 0) $html .= '</tr><tr>';
    }
    $html .= '</tr></table>';

    $html .= '<table class="firmas"><tr>';
    $html .= '<td><div class="firma-linea">Responsable<br>' . htmlspecialchars(trim($profesor['nombre'] . ' ' . $profesor['apellido'])) . '</div></td>';
    $html .= '<td><div class="firma-linea">Administrador del inventario</div></td>';
    $html .= '</tr></table>';

    $html .= '<div class="footer">Sistema MIC — ' . htmlspecialchars($institucion['nombre']) . ' — Documento generado el ' . date('d/m/Y H:i:s') . '</div>';
    $html .= '</body></html>';
    return $html;
}

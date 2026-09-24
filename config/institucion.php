<?php
require_once __DIR__ . '/../includes/InstitucionHelper.php';
$inst = InstitucionHelper::get();
return [
    'nombre' => $inst['nombre'] ?? 'MIC',
    'codigo' => $inst['nombre_corto'] ?? 'MIC',
    'logo' => $inst['logo'] ?? 'assets/img/logo.png',
    'url' => 'http://localhost/mic/', // or from env if applicable
];

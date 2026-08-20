<?php
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0777, true);
}

function logError($mensaje) {
    global $logsDir;
    $archivo = $logsDir . '/error.log';
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
    file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
}

function mostrarErrorAmigable($titulo = 'Error', $detalle = 'Ocurrió un error inesperado.') {
    logError("$titulo: $detalle");
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - MIC</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'Plus Jakarta Sans',sans-serif; background:linear-gradient(135deg,#f0f9ff,#e0f2fe); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
            .error-card { background:white; max-width:500px; width:100%; padding:40px; border-radius:24px; box-shadow:0 16px 48px rgba(0,0,0,0.12); text-align:center; animation:fadeInUp 0.6s ease; }
            .error-icon { width:80px; height:80px; background:linear-gradient(135deg,#ef4444,#dc2626); border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 24px; font-size:2.5rem; color:white; box-shadow:0 0 20px rgba(239,68,68,0.4); }
            .error-card h1 { font-size:1.8rem; margin-bottom:12px; color:#1e293b; }
            .error-card p { color:#64748b; margin-bottom:24px; line-height:1.6; }
            .error-card .btn { display:inline-block; padding:14px 28px; background:linear-gradient(135deg,#0a58ca,#3b82f6); color:white; border-radius:12px; text-decoration:none; font-weight:600; transition:all 0.3s; }
            .error-card .btn:hover { transform:translateY(-2px); box-shadow:0 0 20px rgba(59,130,246,0.4); }
            @keyframes fadeInUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h1><?php echo htmlspecialchars($titulo); ?></h1>
            <p><?php echo htmlspecialchars($detalle); ?></p>
            <a href="index.php" class="btn"><i class="fas fa-home"></i> Volver al inicio</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function manejarError($errno, $errstr, $errfile, $errline) {
    $nivel = match ($errno) {
        E_WARNING, E_USER_WARNING => 'Warning',
        E_NOTICE, E_USER_NOTICE => 'Notice',
        default => 'Error'
    };
    logError("PHP $nivel [$errno]: $errstr en $errfile línea $errline");
    return false;
}
set_error_handler('manejarError');

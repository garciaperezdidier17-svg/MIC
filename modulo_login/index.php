<?php
require_once '../config/conexion.php';

if (estaLogueado()) {
    header('Location: ../modulo_dashboard/index.php');
    exit;
}

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Completa todos los campos';
    } else {
        $stmt = $conn->prepare("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE nombre = ? AND activo = 1");
        $stmt->execute([$username]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_nombre'] = $usuario['nombre'];
            $_SESSION['user_email'] = $usuario['email'] ?? '';
            $rol = $usuario['rol'] ?? 'usuario';
            if (is_numeric($rol)) { $rol = (int)$rol === 1 ? 'admin' : 'usuario'; }
            $_SESSION['user_rol'] = $rol;
            if ($_SESSION['user_rol'] === 'admin') {
                header('Location: ../modulo_dashboard/index.php');
            } else {
                header('Location: ../modulo_prestamos/solicitudes.php');
            }
            exit;
        }

        $error = 'Usuario o contraseña incorrectos';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registro'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Completa todos los campos';
    } elseif (strlen($password) < 4) {
        $error = 'La contraseña debe tener al menos 4 caracteres';
    } elseif ($username === 'admin') {
        $error = 'Ese nombre de usuario no está disponible';
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'El usuario ya existe';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtRol = $conn->query("SELECT id FROM roles WHERE nombre = 'estudiante' LIMIT 1");
            $rolId = $stmtRol->fetchColumn() ?: 4;
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id, rol) VALUES (?, ?, ?, ?, 'usuario')");
            if ($stmt->execute([$username, $username . '@mic.local', $password_hash, $rolId])) {
                $exito = 'Cuenta creada exitosamente. Ahora inicia sesión.';
            } else {
                $error = 'Error al registrar. Intenta de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIC - Sistema de Gestión de Inventario</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-container { position: relative; width: 100%; max-width: 420px; }
        .auth-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            z-index: 0;
        }
        .auth-orb:nth-child(1) {
            width: 300px; height: 300px;
            background: #3b82f6;
            top: -100px; left: -100px;
        }
        .auth-orb:nth-child(2) {
            width: 250px; height: 250px;
            background: #8b5cf6;
            bottom: -80px; right: -80px;
        }
        .auth-card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: #fff;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }
        .auth-logo-text h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .auth-logo-text p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
            font-weight: 500;
            margin-top: 4px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .auth-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 24px;
        }
        .auth-tab {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.5);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .auth-tab.active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .auth-form { display: none; }
        .auth-form.active { display: block; }
        .input-group {
            position: relative;
            margin-bottom: 16px;
        }
        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.3);
            font-size: 16px;
            z-index: 2;
            pointer-events: none;
        }
        .input-group input {
            width: 100%;
            padding: 14px 14px 14px 42px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }
        .input-group input::placeholder { color: rgba(255, 255, 255, 0.3); }
        .input-group input:focus {
            border-color: #3b82f6;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .input-group select {
            width: 100%;
            padding: 14px 14px 14px 42px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            appearance: none;
            cursor: pointer;
        }
        .input-group select option { background: #1e293b; color: #fff; }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        .auth-footer p {
            color: rgba(255, 255, 255, 0.3);
            font-size: 12px;
            font-weight: 500;
        }
        .auth-footer p i { margin-right: 6px; }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-orb"></div>
    <div class="auth-orb"></div>

    <div class="auth-card">
        <div class="auth-logo">
            <?php $logo = obtenerLogo(); if ($logo): ?>
            <div class="auth-logo-icon" style="background:none;box-shadow:none;width:auto;height:auto;border-radius:0;">
                <img src="../<?php echo $logo; ?>" alt="MIC" style="max-height:72px;max-width:200px;">
            </div>
            <?php else: ?>
            <div class="auth-logo-icon"><i class="fas fa-cubes"></i></div>
            <?php endif; ?>
            <div class="auth-logo-text">
                <h1>MIC</h1>
                <p>Institución Educativa 20 de Julio</p>
            </div>
        </div>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if($exito): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($exito); ?></div>
        <?php endif; ?>

        <div class="auth-tabs">
            <button class="auth-tab active" onclick="mostrarTab('login')">Iniciar Sesión</button>
            <button class="auth-tab" onclick="mostrarTab('registro')">Crear Cuenta</button>
        </div>

        <div id="loginPanel" class="auth-form active">
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">

                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Usuario" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Contraseña" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>
        </div>

        <div id="registroPanel" class="auth-form">
            <form method="POST" action="">
                <input type="hidden" name="registro" value="1">

                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Elige un usuario" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Elige una contraseña" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Crear Cuenta
                </button>
            </form>
        </div>

        <div class="auth-footer">
            <p><i class="fas fa-shield-alt"></i> La mejor Institucion del Bagre</p>
        </div>
    </div>
</div>

<script>
function mostrarTab(tab) {
    document.getElementById('loginPanel').classList.toggle('active', tab === 'login');
    document.getElementById('registroPanel').classList.toggle('active', tab === 'registro');
    document.querySelectorAll('.auth-tab').forEach(function(t, i) {
        t.classList.toggle('active', (tab === 'login' && i === 0) || (tab === 'registro' && i === 1));
    });
}
</script>

</body>
</html>

<?php
session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/CSRF.php';

$db  = new Database();
$pdo = $db->conectar();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validarToken($_POST['csrf_token'] ?? null)) {
        $mensaje = 'Token CSRF inválido. Por favor recarga la página.';
    } else {
        $auth      = new Auth($pdo);
        $resultado = $auth->login($_POST['usuario'] ?? '', $_POST['password'] ?? '');

        if ($resultado['ok']) {
            header('Location: verificar_2fa.php');
            exit;
        }

        $mensaje = $resultado['mensaje'];
    }
}

$token = CSRF::generarToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Lab2FA</title>
    <link rel="stylesheet" href="../assets/styles.css?v=4">
</head>
<body>
<div class="container container-narrow">
    <h1>Iniciar Sesión</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-error"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary">Continuar</button>
    </form>

    <p class="link-alt">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
</div>
<script src="../assets/scene.js?v=4" defer></script>
</body>
</html>

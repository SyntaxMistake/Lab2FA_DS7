<?php
session_start();

if (!isset($_SESSION['pre_2fa'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/CSRF.php';

$db  = new Database();
$pdo = $db->conectar();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validarToken($_POST['csrf_token'] ?? null)) {
        $mensaje = 'Token CSRF inválido.';
    } else {
        $auth = new Auth($pdo);

        if ($auth->verificar2FA($_POST['codigo'] ?? '')) {
            header('Location: dashboard.php');
            exit;
        }

        $mensaje = 'Código 2FA incorrecto. Verifica la hora de tu dispositivo e intenta de nuevo.';
    }
}

$token = CSRF::generarToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación 2FA — Lab2FA</title>
    <link rel="stylesheet" href="../assets/styles.css?v=4">
</head>
<body>
<div class="container container-narrow">
    <h1>Verificación 2FA</h1>
    <p class="subtitle">Ingresa el código de 6 dígitos que aparece en tu Google Authenticator.</p>

    <?php if ($mensaje): ?>
        <div class="alert alert-error"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
            <label for="codigo">Código 2FA</label>
            <input type="text" id="codigo" name="codigo"
                   maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                   placeholder="000000" autocomplete="one-time-code" required>
        </div>

        <button type="submit" class="btn btn-primary">Verificar</button>
    </form>

    <p class="link-alt"><a href="login.php">← Volver al login</a></p>
</div>
<script src="../assets/scene.js?v=4" defer></script>
</body>
</html>

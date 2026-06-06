<?php
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== 'SI') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal — Lab2FA</title>
    <link rel="stylesheet" href="../assets/styles.css?v=4">
</head>
<body>
<div class="container">
    <div class="dashboard-header">
        <h1>Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario']) ?></h1>
    </div>

    <?php if (!empty($_SESSION['fase_qr_ok'])): ?>
    <div class="auth-badge">
        <div class="auth-badge-icon">&#10003;</div>
        <div class="auth-badge-body">
            <strong>Autenticación de dos factores verificada</strong>
            <span>Identidad confirmada con contraseña y código TOTP</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="dashboard-info">
        <p><strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario']) ?></p>
    </div>

    <div class="dashboard-actions">
        <a href="hash.php" class="btn btn-secondary">Probar Hash</a>
<a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
    </div>
</div>
<script src="../assets/scene.js?v=4" defer></script>
</body>
</html>

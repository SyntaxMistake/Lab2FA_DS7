<?php
session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Registro.php';
require_once __DIR__ . '/../classes/CSRF.php';
require_once __DIR__ . '/../classes/TOTP.php';

$db  = new Database();
$pdo = $db->conectar();

$mensaje = '';
$qr      = '';
$secreto = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validarToken($_POST['csrf_token'] ?? null)) {
        $mensaje = 'Token CSRF inválido. Por favor recarga la página.';
    } else {
        $registro  = new Registro($pdo);
        $resultado = $registro->registrar($_POST);
        $mensaje   = $resultado['mensaje'];

        if ($resultado['ok']) {
            $secreto = $resultado['secreto'];
            $qr      = TOTP::generarURLQR($resultado['usuario'], $secreto);
            unset($_SESSION['csrf_token']);
        }
    }
}

$token = CSRF::generarToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — Lab2FA</title>
    <link rel="stylesheet" href="../assets/styles.css?v=4">
</head>
<body>
<div class="container">
    <h1>Crear Cuenta</h1>

    <?php if ($qr): ?>
        <div class="qr-box">
            <h2>¡Registro exitoso!</h2>
            <p>Escanea este código QR con <strong>Google Authenticator</strong>.</p>
            <img src="<?= htmlspecialchars($qr) ?>" alt="Código QR 2FA" width="200" height="200">
            <p class="secret-key">Clave secreta: <code><?= htmlspecialchars($secreto) ?></code></p>
            <p class="qr-note">Guarda esta clave. La necesitarás si pierdes tu dispositivo.</p>
            <a href="login.php" class="btn btn-primary">Ir al Login</a>
        </div>
    <?php else: ?>

        <?php if ($mensaje): ?>
            <div class="alert alert-error"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form id="form-registro" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre"
                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                           autocomplete="given-name">
                </div>
                <div class="form-group">
                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido"
                           value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>"
                           autocomplete="family-name">
                </div>
            </div>

            <div class="form-group">
                <label for="sexo">Sexo</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Seleccione</option>
                    <option value="M" <?= ($_POST['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($_POST['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario"
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                           autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo"
                           value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                           autocomplete="email">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Contraseña <small>(mín. 6)</small></label>
                    <input type="password" id="password" name="password" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirmar_password">Confirmar</label>
                    <input type="password" id="confirmar_password" name="confirmar_password"
                           autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Registrarse</button>
        </form>

        <p class="link-alt">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.20.0/dist/jquery.validate.min.js"></script>
<script>
$(function () {
    $.validator.setDefaults({ errorClass: 'error-msg' });

    $('#form-registro').validate({
        rules: {
            nombre:   { required: true, minlength: 2 },
            apellido: { required: true, minlength: 2 },
            sexo:     { required: true },
            usuario: {
                required: true,
                minlength: 3,
                remote: {
                    url: 'ajax_check.php',
                    type: 'get',
                    data: {
                        campo: 'usuario',
                        valor: function () { return $('#usuario').val(); }
                    }
                }
            },
            correo: {
                required: true,
                email: true,
                remote: {
                    url: 'ajax_check.php',
                    type: 'get',
                    data: {
                        campo: 'correo',
                        valor: function () { return $('#correo').val(); }
                    }
                }
            },
            password:           { required: true, minlength: 6 },
            confirmar_password: { required: true, equalTo: '#password' }
        },
        messages: {
            nombre:   { required: 'El nombre es requerido.', minlength: 'Mínimo 2 caracteres.' },
            apellido: { required: 'El apellido es requerido.', minlength: 'Mínimo 2 caracteres.' },
            sexo:     { required: 'Selecciona una opción.' },
            usuario:  { required: 'El usuario es requerido.', minlength: 'Mínimo 3 caracteres.' },
            correo:   { required: 'El correo es requerido.', email: 'Formato de correo inválido.' },
            password: { required: 'La contraseña es requerida.', minlength: 'Mínimo 6 caracteres.' },
            confirmar_password: {
                required: 'Confirma tu contraseña.',
                equalTo:  'Las contraseñas no coinciden.'
            }
        },
        errorPlacement: function (error, element) {
            error.addClass('error-msg').insertAfter(element);
        },
        highlight:   function (el) { $(el).addClass('input-error'); },
        unhighlight: function (el) { $(el).removeClass('input-error'); }
    });
});
</script>
<script src="../assets/scene.js?v=4" defer></script>
</body>
</html>

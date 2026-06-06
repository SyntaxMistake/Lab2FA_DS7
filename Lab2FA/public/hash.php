<?php
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== 'SI') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../classes/CSRF.php';
$token = CSRF::generarToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hash — Lab2FA</title>
    <link rel="stylesheet" href="../assets/styles.css?v=4">
</head>
<body>
<div class="container container-hash">
    <h1>Generar y Verificar Hash</h1>

    <div class="hash-grid">
        <section class="hash-section">
            <h2>Generar Hash</h2>
            <form id="form-generar">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="password_gen">Contraseña a hashear</label>
                    <input type="text" id="password_gen" name="password"
                           placeholder="Escribe una contraseña" required>
                </div>
                <button type="submit" class="btn btn-secondary">Generar Hash</button>
            </form>
            <div id="resultado-generar"></div>
        </section>

        <section class="hash-section">
            <h2>Verificar Hash</h2>
            <form id="form-validar">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="password_ver">Contraseña</label>
                    <input type="text" id="password_ver" name="password"
                           placeholder="Contraseña a verificar" required>
                </div>
                <div class="form-group">
                    <label for="hash_ingresado">Hash</label>
                    <textarea id="hash_ingresado" name="hash_ingresado" rows="3"
                              placeholder="Pega aquí el hash generado..." required></textarea>
                </div>
                <button type="submit" class="btn btn-secondary">Verificar Hash</button>
            </form>
            <div id="resultado-validar"></div>
        </section>
    </div>

    <a href="dashboard.php" class="btn btn-back">← Volver</a>
</div>

<script>
(function () {
    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function bindForm(formId, accion, resultId) {
        var form   = document.getElementById(formId);
        var result = document.getElementById(resultId);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            result.innerHTML = '';

            var data = new FormData(form);
            data.append('accion', accion);

            fetch('ajax_hash.php', { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (accion === 'generar') {
                        if (res.hash) {
                            result.innerHTML =
                                '<div class="alert alert-success" style="margin-top:.8rem;margin-bottom:0">'
                                + '<code class="hash-output">' + esc(res.hash) + '</code></div>';
                        } else {
                            result.innerHTML =
                                '<div class="alert alert-error" style="margin-top:.8rem;margin-bottom:0">'
                                + esc(res.error || 'Error') + '</div>';
                        }
                    } else {
                        if (res.coincide === true) {
                            result.innerHTML =
                                '<div class="alert alert-success" style="margin-top:.8rem;margin-bottom:0">'
                                + 'El password COINCIDE con el hash.</div>';
                        } else if (res.coincide === false) {
                            result.innerHTML =
                                '<div class="alert alert-error" style="margin-top:.8rem;margin-bottom:0">'
                                + 'El password NO coincide con el hash.</div>';
                        } else {
                            result.innerHTML =
                                '<div class="alert alert-error" style="margin-top:.8rem;margin-bottom:0">'
                                + esc(res.error || 'Error') + '</div>';
                        }
                    }
                })
                .catch(function () {
                    result.innerHTML =
                        '<div class="alert alert-error" style="margin-top:.8rem;margin-bottom:0">'
                        + 'Error de conexión.</div>';
                });
        });
    }

    bindForm('form-generar', 'generar', 'resultado-generar');
    bindForm('form-validar',  'validar',  'resultado-validar');
}());
</script>

<script src="../assets/scene.js?v=4" defer></script>
</body>
</html>

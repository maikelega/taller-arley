<?php
require_once __DIR__ . '/app/includes/auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error  = '';
$email  = '';
$notice = '';

if (!empty($_GET['expired'])) {
    $notice = 'Su sesión expiró. Inicie sesión nuevamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['csrf_token'] ?? '';
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password']  ?? '';

    if (!csrf_verify($token)) {
        $error = 'Token de seguridad inválido. Recargue la página e intente de nuevo.';
    } else {
        $result = authenticate($email, $password);
        if ($result['ok']) {
            header('Location: /dashboard.php');
            exit;
        }
        $error = $result['error'];
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="brand">
            <h1>Taller Arley</h1>
            <p>Gestión de órdenes de trabajo</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php elseif (!empty($notice)): ?>
            <div class="alert alert-info" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <span><?= htmlspecialchars($notice) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <div class="input-wrapper">
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control<?= $error ? ' is-invalid' : '' ?>"
                           placeholder="usuario@tallerarley.com"
                           value="<?= htmlspecialchars($email) ?>"
                           required
                           autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control<?= $error ? ' is-invalid' : '' ?>"
                           placeholder="••••••••"
                           required>
                </div>
                <label class="show-password">
                    <input type="checkbox" onchange="togglePassword(this)">
                    <span>Mostrar contraseña</span>
                </label>
            </div>

            <button type="submit" class="btn-submit">Acceder</button>
        </form>

        <div class="login-footer-note">
            Acceso exclusivo para personal del taller.<br>
            ¿Eres cliente? Usa el link enviado por WhatsApp.
        </div>
    </div>
</div>

<script>
function togglePassword(checkbox) {
    const input = document.getElementById('password');
    if (input) input.type = checkbox.checked ? 'text' : 'password';
}
</script>

</body>
</html>

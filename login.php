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
    <link rel="icon" type="image/png" href="app/assets/images/favicon.png">
    <link rel="stylesheet" href="app/assets/css/login.css">
</head>
<body>

<div class="login-page">
<div class="login-shell">

    <aside class="brand-panel">
        <svg class="gear gear-1" viewBox="0 0 100 100" fill="currentColor"><path d="M50 35a15 15 0 100 30 15 15 0 000-30zm44.5 8.5l-7.8-1.6a35 35 0 00-3.4-8.2l4.4-6.7a2 2 0 00-.3-2.6l-6-6a2 2 0 00-2.6-.3l-6.7 4.4a35 35 0 00-8.2-3.4l-1.6-7.8A2 2 0 0060.5 10h-8.5a2 2 0 00-2 1.6l-1.6 7.8a35 35 0 00-8.2 3.4l-6.7-4.4a2 2 0 00-2.6.3l-6 6a2 2 0 00-.3 2.6l4.4 6.7a35 35 0 00-3.4 8.2l-7.8 1.6a2 2 0 00-1.6 2v8.5a2 2 0 001.6 2l7.8 1.6a35 35 0 003.4 8.2l-4.4 6.7a2 2 0 00.3 2.6l6 6a2 2 0 002.6.3l6.7-4.4a35 35 0 008.2 3.4l1.6 7.8a2 2 0 002 1.6h8.5a2 2 0 002-1.6l1.6-7.8a35 35 0 008.2-3.4l6.7 4.4a2 2 0 002.6-.3l6-6a2 2 0 00.3-2.6l-4.4-6.7a35 35 0 003.4-8.2l7.8-1.6a2 2 0 001.6-2V46a2 2 0 00-1.6-2z"/></svg>
        <svg class="gear gear-2" viewBox="0 0 100 100" fill="currentColor"><path d="M50 35a15 15 0 100 30 15 15 0 000-30zm44.5 8.5l-7.8-1.6a35 35 0 00-3.4-8.2l4.4-6.7a2 2 0 00-.3-2.6l-6-6a2 2 0 00-2.6-.3l-6.7 4.4a35 35 0 00-8.2-3.4l-1.6-7.8A2 2 0 0060.5 10h-8.5a2 2 0 00-2 1.6l-1.6 7.8a35 35 0 00-8.2 3.4l-6.7-4.4a2 2 0 00-2.6.3l-6 6a2 2 0 00-.3 2.6l4.4 6.7a35 35 0 00-3.4 8.2l-7.8 1.6a2 2 0 00-1.6 2v8.5a2 2 0 001.6 2l7.8 1.6a35 35 0 003.4 8.2l-4.4 6.7a2 2 0 00.3 2.6l6 6a2 2 0 002.6.3l6.7-4.4a35 35 0 008.2 3.4l1.6 7.8a2 2 0 002 1.6h8.5a2 2 0 002-1.6l1.6-7.8a35 35 0 008.2-3.4l6.7 4.4a2 2 0 002.6-.3l6-6a2 2 0 00.3-2.6l-4.4-6.7a35 35 0 003.4-8.2l7.8-1.6a2 2 0 001.6-2V46a2 2 0 00-1.6-2z"/></svg>
        <svg class="gear gear-3" viewBox="0 0 100 100" fill="currentColor"><path d="M50 35a15 15 0 100 30 15 15 0 000-30zm44.5 8.5l-7.8-1.6a35 35 0 00-3.4-8.2l4.4-6.7a2 2 0 00-.3-2.6l-6-6a2 2 0 00-2.6-.3l-6.7 4.4a35 35 0 00-8.2-3.4l-1.6-7.8A2 2 0 0060.5 10h-8.5a2 2 0 00-2 1.6l-1.6 7.8a35 35 0 00-8.2 3.4l-6.7-4.4a2 2 0 00-2.6.3l-6 6a2 2 0 00-.3 2.6l4.4 6.7a35 35 0 00-3.4 8.2l-7.8 1.6a2 2 0 00-1.6 2v8.5a2 2 0 001.6 2l7.8 1.6a35 35 0 003.4 8.2l-4.4 6.7a2 2 0 00.3 2.6l6 6a2 2 0 002.6.3l6.7-4.4a35 35 0 008.2 3.4l1.6 7.8a2 2 0 002 1.6h8.5a2 2 0 002-1.6l1.6-7.8a35 35 0 008.2-3.4l6.7 4.4a2 2 0 002.6-.3l6-6a2 2 0 00.3-2.6l-4.4-6.7a35 35 0 003.4-8.2l7.8-1.6a2 2 0 001.6-2V46a2 2 0 00-1.6-2z"/></svg>

        <div class="brand-top">
            <img src="app/assets/images/logo.jpeg" alt="Centro Automotriz Arley" class="brand-logo-img">
            <div class="brand-title">
                Centro Automotriz Arley
                <span>Gestión de órdenes de trabajo</span>
            </div>
        </div>

        <div class="brand-hero">
            <h1>Cada reparación, <em>bajo control.</em></h1>
            <p>Desde la recepción hasta la entrega: diagnóstico, presupuesto y seguimiento en un solo lugar para tu equipo y tus clientes.</p>

            <div class="feature-row">
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <span>Órdenes en vivo</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <span>Diagnóstico visual</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                    </div>
                    <span>Aviso al cliente</span>
                </div>
            </div>
        </div>

        <div class="brand-footer">&copy; <?= date('Y') ?> Centro Automotriz Arley — Costa Rica</div>
    </aside>

    <main class="form-panel">
        <div class="mobile-brand">
            <img src="app/assets/images/logo.jpeg" alt="Centro Automotriz Arley" class="mobile-brand-logo-img">
            <div style="font-size:14px;font-weight:700;color:white;">Centro Automotriz Arley</div>
        </div>

        <div class="welcome">
            <h2>Bienvenido de nuevo</h2>
            <p>Ingrese sus credenciales para acceder al sistema.</p>
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
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

            <button type="submit" class="btn-submit">
                Acceder
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <div class="login-footer-note">
            Acceso exclusivo para personal del taller.<br>
            ¿Eres cliente? Usa el link enviado por WhatsApp.
        </div>
    </main>

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

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_login();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Taller Arley</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        .card { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 1.5rem; max-width: 500px; }
        h1 { color: white; margin-bottom: 0.5rem; }
        .role { display: inline-block; background: #3b82f6; color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; margin-bottom: 1rem; }
        a { color: #60a5fa; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Bienvenido, <?= htmlspecialchars($user['name']) ?></h1>
        <span class="role"><?= htmlspecialchars($user['role']) ?></span>
        <p>Login funcionando correctamente ✓</p>
        <p style="margin-top: 1rem;"><a href="logout.php">Cerrar sesión</a></p>
    </div>
</body>
</html>

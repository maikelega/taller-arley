<?php
/**
 * Taller Arley — Setup Validator
 */

require_once 'app/config/config.php';
require_once 'app/includes/db.php';

$errors = [];
$success = [];

// 1. Database
try {
    $db = Database::getConnection();
    $db->query("SELECT 1");
    $success[] = "✓ Database connection OK";
} catch (Exception $e) {
    $errors[] = "✗ Database: " . $e->getMessage();
}

// 2. Config
if (defined('DB_NAME')) {
    $success[] = "✓ Configuration loaded (" . DB_NAME . ")";
} else {
    $errors[] = "✗ Configuration failed";
}

// 3. Environment
$success[] = "✓ Environment: " . (ENVIRONMENT === 'development' ? 'DEVELOPMENT' : 'PRODUCTION');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taller Arley — Setup</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1faee; padding: 2rem; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1d3557; margin-top: 0; font-size: 24px; }
        .message { padding: 12px; margin-bottom: 8px; border-radius: 4px; font-size: 14px; }
        .success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .error { background: #fee2e2; color: #7f1d1d; border-left: 3px solid #ef4444; }
        .status { font-weight: 600; margin: 1.5rem 0 1rem; color: #1d3557; }
        .status.ok { color: #10b981; }
        .status.error { color: #ef4444; }
        a { color: #457b9d; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Taller Arley</h1>
        <p>Setup Validator — Fase 1 MVP</p>

        <div class="status <?= empty($errors) ? 'ok' : 'error' ?>">
            <?= empty($errors) ? '✓ Setup OK' : '✗ Problemas detectados' ?>
        </div>

        <?php foreach ($success as $msg): ?>
            <div class="message success"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $msg): ?>
            <div class="message error"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
            <p><strong>Estado:</strong></p>
            <ul>
                <li>✓ Setup inicial completado</li>
                <li>✓ BD: <?= empty($errors) ? 'Conectada' : 'Desconectada' ?></li>
                <li>✓ Fase 1 Semana 1: Auth completo</li>
                <li><a href="login.php">→ Ir a login</a></li>
            </ul>
        </div>
    </div>
</body>
</html>

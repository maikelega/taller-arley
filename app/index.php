<?php
/**
 * Taller Arley — Index (Fase 1 Setup Validator)
 */

require_once 'config/config.php';
require_once 'includes/db.php';

// Validate setup
$errors = [];
$success = [];

// 1. Database connection
try {
    $db = Database::getConnection();
    $db->query("SELECT 1");
    $success[] = "✓ Database connection OK";
} catch (Exception $e) {
    $errors[] = "✗ Database connection failed: " . $e->getMessage();
}

// 2. Config loaded
if (defined('DB_NAME')) {
    $success[] = "✓ Configuration loaded (DB: " . DB_NAME . ")";
} else {
    $errors[] = "✗ Configuration not loaded";
}

// 3. App environment
$success[] = "✓ Environment: " . (APP_DEBUG ? "DEVELOPMENT" : "PRODUCTION");

// 4. Schema check
try {
    $tables = $db->fetchAll("SHOW TABLES FROM " . DB_NAME);
    $count = count($tables);
    if ($count > 0) {
        $success[] = "✓ Schema exists ($count tables)";
    } else {
        $errors[] = "✗ No tables found — run database/01_schema.sql";
    }
} catch (Exception $e) {
    $errors[] = "✗ Schema check failed: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taller Arley — Setup Validator</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1faee;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d3557;
            margin-top: 0;
            font-size: 24px;
        }
        .message {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            border-left: 3px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #7f1d1d;
            border-left: 3px solid #ef4444;
        }
        .status {
            font-weight: 600;
            margin: 1.5rem 0 1rem;
            color: #1d3557;
        }
        .status.ok { color: #10b981; }
        .status.error { color: #ef4444; }
        a {
            color: #457b9d;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
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
            <p><strong>Próximos pasos:</strong></p>
            <ol>
                <li>Validar config en <code>.env</code></li>
                <li>Correr <code>database/01_schema.sql</code> si falta</li>
                <li>Crear usuario admin de prueba</li>
                <li>Comenzar desarrollo de Módulo 1 (Auth + Kanban)</li>
            </ol>
            <p><a href="#docs">Ver documentación →</a></p>
        </div>
    </div>
</body>
</html>

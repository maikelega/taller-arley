<?php
/**
 * Session management + security headers
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (ENVIRONMENT !== 'development') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }

    // Revalidar en cada request: usuario activo + password_version vigente
    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT password_version, is_active, role_id FROM users WHERE id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $row = $stmt->fetch();

        $sessVersion     = (int) ($_SESSION['password_version'] ?? 0);
        $passwordChanged = $row && $sessVersion !== (int) $row['password_version'];

        if (!$row || !(bool) $row['is_active'] || $passwordChanged) {
            destroy_session();
            header('Location: /login.php?expired=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log('[require_login] ' . $e->getMessage());
        // fail open ante glitch transitorio de BD
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'      => $_SESSION['user_id'],
        'name'    => $_SESSION['user_name']  ?? '',
        'email'   => $_SESSION['user_email'] ?? '',
        'role_id' => $_SESSION['role_id']    ?? null,
        'role'    => $_SESSION['role_name']  ?? '',
    ];
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

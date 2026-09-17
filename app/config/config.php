<?php
/**
 * Configuración de la aplicación — Taller Arley
 *
 * Los secretos (DB, Twilio) se inyectan vía Apache SetEnv desde .htaccess.
 * Este archivo solo define constantes de aplicación.
 */

// Carga .env si existe (fallback para desarrollo local — PHP built-in
// server no procesa .htaccess). Prioridad: $_SERVER (Apache SetEnv) > .env > default.
(static function () {
    $envFile = __DIR__ . '/../../.env';
    if (!is_file($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        if (!isset($_SERVER[$key]) || $_SERVER[$key] === '') {
            $_SERVER[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
})();

if (!function_exists('env_get')) {
    function env_get(string $key, $default = null)
    {
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        return ($val === false || $val === '') ? $default : $val;
    }
}

// ── Database ──────────────────────────────────────────────────
define('DB_HOST', env_get('DB_HOST', 'localhost'));
define('DB_PORT', (int) env_get('DB_PORT', 3306));
define('DB_NAME', env_get('DB_NAME', ''));
define('DB_USER', env_get('DB_USER', ''));
define('DB_PASS', env_get('DB_PASS', ''));

// ── Application ───────────────────────────────────────────────
define('APP_NAME', 'Taller Arley');
define('APP_TIMEZONE', 'America/Costa_Rica');
define('APP_URL', env_get('APP_URL', 'https://tallerarley.magasoft.tech'));
define('ENVIRONMENT', env_get('APP_ENV', 'production'));

// ── Notificaciones WhatsApp (Fase 1+) ─────────────────────────
define('TWILIO_ACCOUNT_SID', env_get('TWILIO_ACCOUNT_SID', ''));
define('TWILIO_AUTH_TOKEN', env_get('TWILIO_AUTH_TOKEN', ''));
define('TWILIO_WHATSAPP_NUMBER', env_get('TWILIO_WHATSAPP_NUMBER', ''));

// ── Security — sesiones y contraseñas ─────────────────────────
define('SESSION_NAME', 'TALLER_ARLEY_SESSION');
define('SESSION_LIFETIME', (int) env_get('SESSION_LIFETIME', 1800));
define('BCRYPT_COST', 12);

date_default_timezone_set(APP_TIMEZONE);

$app_debug = env_get('APP_DEBUG', 'false') === 'true';
if ($app_debug || ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ── Sanity check ───────────────────────────────────────────────
if (DB_NAME === '' || DB_USER === '' || DB_PASS === '') {
    error_log('[config] Faltan credenciales DB — verifique SetEnv DB_* en .htaccess');
    if (ENVIRONMENT === 'development') {
        die('Configuración incompleta: las credenciales DB no llegaron desde .htaccess.');
    }
    http_response_code(500);
    die('Error de configuración del servidor.');
}

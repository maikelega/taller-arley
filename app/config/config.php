<?php
/**
 * Taller Arley — Configuration
 * Reads from .env (via .htaccess SetEnv or $_ENV)
 */

// Helper: read env var with fallback
function env_get($key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    return $value !== null ? $value : $default;
}

// Database
define('DB_HOST', env_get('DB_HOST', 'localhost'));
define('DB_PORT', env_get('DB_PORT', 3306));
define('DB_NAME', env_get('DB_NAME', 'taller_arley_dev'));
define('DB_USER', env_get('DB_USER', 'root'));
define('DB_PASS', env_get('DB_PASS', 'password'));

// App
define('APP_ENV', env_get('APP_ENV', 'development'));
define('APP_DEBUG', env_get('APP_DEBUG', 'true') === 'true');
define('APP_URL', env_get('APP_URL', 'http://localhost:8000'));

// Session
define('SESSION_LIFETIME', (int) env_get('SESSION_LIFETIME', 1800));

// Twilio WhatsApp
define('TWILIO_ACCOUNT_SID', env_get('TWILIO_ACCOUNT_SID', ''));
define('TWILIO_AUTH_TOKEN', env_get('TWILIO_AUTH_TOKEN', ''));
define('TWILIO_WHATSAPP_NUMBER', env_get('TWILIO_WHATSAPP_NUMBER', ''));

// Error handling
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// CORS / Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

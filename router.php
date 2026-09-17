<?php
/**
 * Router para PHP built-in server (solo desarrollo local).
 * No se usa en producción (Hostinger usa Apache).
 */

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

if (is_dir($file)) {
    $index = rtrim($file, '/') . '/index.php';
    if (file_exists($index)) {
        $_SERVER['SCRIPT_FILENAME'] = $index;
        require $index;
        return true;
    }
}

if (file_exists($file . '.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $file . '.php';
    require $file . '.php';
    return true;
}

http_response_code(404);
echo '404 — Página no encontrada';

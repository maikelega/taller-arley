<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/photos.php';

require_login();

$tempToken = trim($_GET['temp_token'] ?? '');
$filename = trim($_GET['filename'] ?? '');

if (!is_valid_temp_token($tempToken) || !preg_match('/^[a-z_]+_[a-f0-9]{12}\.(jpg|png|webp)$/', $filename)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$filePath = __DIR__ . '/../uploads/temp/' . $tempToken . '/' . $filename;
$realBase = realpath(__DIR__ . '/../uploads/temp');
$realFile = realpath($filePath);

if ($realFile === false || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
    http_response_code(404);
    exit('Foto no encontrada.');
}

$mime = mime_content_type($realFile) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($realFile));
readfile($realFile);

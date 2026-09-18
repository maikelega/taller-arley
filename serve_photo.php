<?php
/**
 * Sirve fotos de inspección de órdenes, gateado por autenticación.
 * Nunca se accede a app/uploads/ directamente (bloqueado en .htaccess).
 */
require_once __DIR__ . '/app/includes/auth.php';
require_login();

$orderId = (int) ($_GET['order_id'] ?? 0);
$photoId = (int) ($_GET['id'] ?? 0);

if ($orderId <= 0 || $photoId <= 0) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT photo_path FROM order_photos WHERE id = :id AND order_id = :oid LIMIT 1');
$stmt->execute([':id' => $photoId, ':oid' => $orderId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Foto no encontrada.');
}

$filePath = __DIR__ . '/app/uploads/' . $row['photo_path'];
$realBase = realpath(__DIR__ . '/app/uploads');
$realFile = realpath($filePath);

// Evita path traversal — el archivo resuelto debe seguir dentro de uploads/
if ($realFile === false || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
    http_response_code(404);
    exit('Foto no encontrada.');
}

$mime = mime_content_type($realFile) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($realFile));
readfile($realFile);

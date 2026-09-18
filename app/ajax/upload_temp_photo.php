<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/photos.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}
if (!can(PERM_ORDERS_CREATE)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
    exit;
}

$tempToken = trim($_POST['temp_token'] ?? '');
$angle = trim($_POST['angle'] ?? '');

if ($tempToken === '' || $angle === '' || empty($_FILES['photo'])) {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos.']);
    exit;
}

$result = upload_temp_photo($tempToken, $angle, $_FILES['photo']);
if ($result['ok']) {
    $result['url'] = 'app/ajax/serve_temp_photo.php?temp_token=' . urlencode($tempToken) . '&filename=' . urlencode($result['filename']);
}
echo json_encode($result);

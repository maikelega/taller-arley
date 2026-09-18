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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!csrf_verify($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
    exit;
}

$tempToken = trim($input['temp_token'] ?? '');
$filename = trim($input['filename'] ?? '');

if ($tempToken === '' || $filename === '') {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos.']);
    exit;
}

echo json_encode(delete_temp_photo($tempToken, $filename));

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
if (!can(PERM_ORDERS_CREATE) && !can(PERM_ORDERS_WORK)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
    exit;
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$angle = trim($_POST['angle'] ?? '');

if ($orderId <= 0 || $angle === '' || empty($_FILES['photo'])) {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos.']);
    exit;
}

$result = upload_order_photo($orderId, $angle, $_FILES['photo']);
if ($result['ok']) {
    $result['url'] = 'serve_photo.php?order_id=' . $orderId . '&id=' . $result['photo_id'];
}
echo json_encode($result);

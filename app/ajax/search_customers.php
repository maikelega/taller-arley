<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (mb_strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

echo json_encode(search_customers($query));

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vehicles.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$customerId = (int) ($_GET['customer_id'] ?? 0);
if ($customerId <= 0) {
    echo json_encode([]);
    exit;
}

echo json_encode(get_customer_vehicles($customerId));

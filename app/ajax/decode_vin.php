<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vehicles.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$vin = trim($_GET['vin'] ?? '');
if ($vin === '') {
    echo json_encode(['ok' => false, 'error' => 'VIN vacío.']);
    exit;
}

echo json_encode(decode_vin_nhtsa($vin));

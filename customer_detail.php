<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/customers.php';
require_login();
require_permission(PERM_ORDERS_VIEW);

$user = current_user();
$customerId = (int) ($_GET['id'] ?? 0);
$customer = get_customer_detail($customerId);

if (!$customer) {
    header('Location: customers.php');
    exit;
}

$statusColors = [
    1 => '#60a5fa', 2 => '#fcd34d', 3 => '#fcd34d', 4 => '#6ee7b7',
    5 => '#60a5fa', 6 => '#c4b5fd', 7 => '#6ee7b7', 8 => '#94a3b8',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['name']) ?> — Centro Automotriz Arley</title>
    <link rel="icon" type="image/png" href="app/assets/images/favicon.png">
    <link rel="stylesheet" href="app/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/app/includes/topbar.php'; ?>

<div class="page">
    <div class="page-header">
        <div>
            <h1><?= htmlspecialchars($customer['name']) ?></h1>
            <p><?= htmlspecialchars($customer['phone'] ?: 'Sin teléfono') ?><?= $customer['email'] ? ' · ' . htmlspecialchars($customer['email']) : '' ?></p>
        </div>
        <a href="customers.php" class="btn btn-secondary">← Volver a clientes</a>
    </div>

    <div class="detail-grid">
        <div>
            <div class="section">
                <h3>Historial de órdenes (<?= count($customer['orders']) ?>)</h3>

                <?php if (empty($customer['orders'])): ?>
                    <p style="color: #94a3b8; font-size: 13px;">Sin órdenes registradas.</p>
                <?php else: ?>
                    <?php foreach ($customer['orders'] as $o): ?>
                        <a href="order_detail.php?id=<?= (int) $o['id'] ?>" style="display: block; padding: 10px; background: rgba(59,130,246,0.05); border-radius: 8px; margin-bottom: 8px; text-decoration: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="color: white; font-size: 13px;"><?= htmlspecialchars($o['plate'] ?? 'Sin placa') ?></strong>
                                    <span style="color: #94a3b8; font-size: 12px;"> — <?= htmlspecialchars(trim(($o['vehicle_brand'] ?? '') . ' ' . ($o['vehicle_model'] ?? ''))) ?: 'Sin datos' ?></span>
                                </div>
                                <span style="color: <?= $statusColors[(int) $o['status_id']] ?? '#94a3b8' ?>; font-size: 11px; font-weight: 600;">
                                    <?= htmlspecialchars($o['status_name']) ?>
                                </span>
                            </div>
                            <div style="color: #64748b; font-size: 11px; margin-top: 4px;">
                                <?= date('d/m/Y H:i', strtotime($o['reception_date'])) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="section">
                <h3>Vehículos (<?= count($customer['vehicles']) ?>)</h3>

                <?php if (empty($customer['vehicles'])): ?>
                    <p style="color: #94a3b8; font-size: 13px;">Sin vehículos registrados.</p>
                <?php else: ?>
                    <?php foreach ($customer['vehicles'] as $v): ?>
                        <div style="padding: 10px; background: rgba(59,130,246,0.05); border-radius: 8px; margin-bottom: 8px;">
                            <strong style="color: white; font-size: 13px;"><?= htmlspecialchars($v['plate']) ?></strong>
                            <div style="color: #cbd5e1; font-size: 12px; margin-top: 2px;">
                                <?= htmlspecialchars(trim(($v['brand'] ?? '') . ' ' . ($v['model'] ?? '') . ' ' . ($v['year'] ?? ''))) ?: 'Sin datos' ?>
                            </div>
                            <?php if ($v['vin']): ?>
                                <div style="color: #64748b; font-size: 11px; margin-top: 2px;">VIN: <?= htmlspecialchars($v['vin']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>

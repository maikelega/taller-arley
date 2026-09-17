<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/orders.php';
require_login();
require_permission(PERM_ORDERS_VIEW);

$user = current_user();
$statuses = get_order_statuses();
$ordersByStatus = get_kanban_orders();

$totalActive = 0;
foreach ($ordersByStatus as $sid => $orders) {
    if ((int) $sid !== 8) {
        $totalActive += count($orders);
    }
}

// "Cerrado" (id=8) no se muestra en el tablero diario — solo estados activos
$activeStatuses = array_filter($statuses, fn($s) => (int) $s['id'] !== 8);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Centro Automotriz Arley</title>
    <link rel="icon" type="image/png" href="app/assets/images/favicon.png">
    <link rel="stylesheet" href="app/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/app/includes/topbar.php'; ?>

<div class="page">
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p><?= $totalActive ?> órdenes activas</p>
        </div>
        <?php if (can(PERM_ORDERS_CREATE)): ?>
            <a href="order_new.php" class="btn btn-primary">+ Nueva orden</a>
        <?php endif; ?>
    </div>

    <div class="kanban">
        <?php foreach ($activeStatuses as $status): ?>
            <?php $orders = $ordersByStatus[(int) $status['id']] ?? []; ?>
            <div class="kanban-col">
                <div class="kanban-col-header">
                    <span class="kanban-col-title"><?= htmlspecialchars($status['name']) ?></span>
                    <span class="kanban-col-count"><?= count($orders) ?></span>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-col">Sin órdenes</div>
                <?php endif; ?>

                <?php foreach ($orders as $order): ?>
                    <a href="order_detail.php?id=<?= (int) $order['id'] ?>" class="order-card" style="display: block;">
                        <div class="order-card-plate"><?= htmlspecialchars($order['plate']) ?></div>
                        <?php if ($order['vehicle_brand'] || $order['vehicle_model']): ?>
                            <div class="order-card-vehicle">
                                <?= htmlspecialchars(trim($order['vehicle_brand'] . ' ' . $order['vehicle_model'])) ?>
                            </div>
                        <?php endif; ?>
                        <div class="order-card-customer"><?= htmlspecialchars($order['customer_name']) ?></div>
                        <?php if ($order['mechanic_name']): ?>
                            <div class="order-card-mechanic"><?= htmlspecialchars($order['mechanic_name']) ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>

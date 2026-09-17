<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/customers.php';
require_login();
require_permission(PERM_ORDERS_VIEW);

$user = current_user();
$query = trim($_GET['q'] ?? '');
$customers = get_customer_list($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes — Taller Arley</title>
    <link rel="stylesheet" href="app/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/app/includes/topbar.php'; ?>

<div class="page">
    <div class="page-header">
        <div>
            <h1>Clientes</h1>
            <p><?= count($customers) ?> cliente<?= count($customers) !== 1 ? 's' : '' ?><?= $query ? ' encontrados' : '' ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">← Volver</a>
    </div>

    <form method="GET" action="customers.php" style="margin-bottom: 1.5rem; max-width: 400px;">
        <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, teléfono o placa..." value="<?= htmlspecialchars($query) ?>" autofocus>
    </form>

    <?php if (empty($customers)): ?>
        <div class="section">
            <p style="color: #94a3b8;">
                <?= $query ? 'Sin resultados para "' . htmlspecialchars($query) . '".' : 'Aún no hay clientes registrados.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="section" style="padding: 0;">
            <?php foreach ($customers as $c): ?>
                <a href="customer_detail.php?id=<?= (int) $c['id'] ?>" style="display: block; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(59,130,246,0.1); text-decoration: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <div style="color: white; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($c['name']) ?></div>
                            <div style="color: #94a3b8; font-size: 12px; margin-top: 2px;">
                                <?= htmlspecialchars($c['phone'] ?: 'Sin teléfono') ?>
                                <?= $c['email'] ? ' · ' . htmlspecialchars($c['email']) : '' ?>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: #94a3b8;">
                            <?= (int) $c['vehicle_count'] ?> vehículo<?= (int) $c['vehicle_count'] !== 1 ? 's' : '' ?>
                            · <?= (int) $c['order_count'] ?> orden<?= (int) $c['order_count'] !== 1 ? 'es' : '' ?>
                            <?php if ($c['last_visit']): ?>
                                <br>Última visita: <?= date('d/m/Y', strtotime($c['last_visit'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/orders.php';
require_login();
require_permission(PERM_ORDERS_VIEW);

$user = current_user();
$orderId = (int) ($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página e intente de nuevo.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_status' && can(PERM_ORDERS_WORK)) {
            $newStatus = (int) ($_POST['status_id'] ?? 0);
            $mechanicId = !empty($_POST['mechanic_id']) ? (int) $_POST['mechanic_id'] : null;
            $result = update_order_status($orderId, $newStatus, $mechanicId);
            if ($result['ok']) {
                $success = 'Estado actualizado.';
            } else {
                $error = $result['error'];
            }
        }

        if ($action === 'add_dvi' && can(PERM_ORDERS_DVI)) {
            $result = add_dvi(
                $orderId,
                $_POST['category'] ?? '',
                $_POST['severity'] ?? '',
                trim($_POST['dvi_notes'] ?? ''),
                (float) ($_POST['estimated_hours'] ?? 0)
            );
            if ($result['ok']) {
                $success = 'Diagnóstico agregado.';
            } else {
                $error = $result['error'];
            }
        }
    }
}

$order = get_order_detail($orderId);
if (!$order) {
    header('Location: dashboard.php');
    exit;
}

$statuses = get_order_statuses();
$mechanics = get_mechanics();
$csrf = csrf_token();

$severityLabels = ['green' => 'Verde', 'yellow' => 'Amarillo', 'red' => 'Rojo'];
$categoryLabels = ['mechanic' => 'Mecánica', 'electric' => 'Eléctrica'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($order['plate']) ?> — Taller Arley</title>
    <link rel="stylesheet" href="app/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/app/includes/topbar.php'; ?>

<div class="page">
    <div class="page-header">
        <div>
            <h1><?= htmlspecialchars($order['plate']) ?> — <?= htmlspecialchars(trim($order['vehicle_brand'] . ' ' . $order['vehicle_model'])) ?: 'Sin datos de vehículo' ?></h1>
            <p>Cliente: <?= htmlspecialchars($order['customer_name']) ?> <?= $order['customer_phone'] ? '· ' . htmlspecialchars($order['customer_phone']) : '' ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">← Volver al Kanban</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <div>
            <!-- Estado -->
            <div class="section">
                <h3>Estado actual: <?= htmlspecialchars($order['status_name']) ?></h3>
                <?php if (can(PERM_ORDERS_WORK)): ?>
                    <form method="POST" action="order_detail.php?id=<?= $orderId ?>" class="status-select-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="update_status">

                        <select name="status_id" class="form-control" style="max-width: 220px;">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (int) $s['id'] === (int) $order['status_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="mechanic_id" class="form-control" style="max-width: 200px;">
                            <option value="">Sin asignar</option>
                            <?php foreach ($mechanics as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= (int) $m['id'] === (int) $order['assigned_mechanic_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- DVI -->
            <div class="section">
                <h3>Diagnóstico visual (DVI)</h3>

                <?php if (empty($order['dvi'])): ?>
                    <p style="color: #94a3b8; font-size: 13px; margin-bottom: 1rem;">Sin diagnóstico registrado aún.</p>
                <?php else: ?>
                    <div style="margin-bottom: 1rem;">
                        <?php foreach ($order['dvi'] as $dvi): ?>
                            <div style="padding: 10px; background: rgba(59,130,246,0.05); border-radius: 8px; margin-bottom: 8px;">
                                <span class="severity-badge severity-<?= $dvi['severity'] ?>">
                                    <?= $severityLabels[$dvi['severity']] ?>
                                </span>
                                <strong style="margin-left: 8px; color: white;"><?= $categoryLabels[$dvi['category']] ?></strong>
                                <?php if ($dvi['estimated_hours']): ?>
                                    <span style="color: #94a3b8; font-size: 12px;"> · Est. <?= $dvi['estimated_hours'] ?>h</span>
                                <?php endif; ?>
                                <?php if ($dvi['notes']): ?>
                                    <p style="color: #cbd5e1; font-size: 13px; margin-top: 4px;"><?= htmlspecialchars($dvi['notes']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (can(PERM_ORDERS_DVI)): ?>
                    <form method="POST" action="order_detail.php?id=<?= $orderId ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="add_dvi">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Categoría</label>
                                <select name="category" class="form-control">
                                    <option value="mechanic">Mecánica</option>
                                    <option value="electric">Eléctrica</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Severidad</label>
                                <select name="severity" class="form-control">
                                    <option value="green">🟢 Verde</option>
                                    <option value="yellow">🟡 Amarillo</option>
                                    <option value="red">🔴 Rojo</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Horas estimadas</label>
                            <input type="number" step="0.5" name="estimated_hours" class="form-control" placeholder="2.5" style="max-width: 150px;">
                        </div>

                        <div class="form-group">
                            <label>Notas técnicas</label>
                            <textarea name="dvi_notes" class="form-control" placeholder="Detalle del diagnóstico"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">+ Agregar diagnóstico</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($order['notes']): ?>
                <div class="section">
                    <h3>Notas de recepción</h3>
                    <p style="color: #cbd5e1; font-size: 13px;"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <!-- Resumen -->
            <div class="section">
                <h3>Resumen</h3>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-label">Recibido</div>
                        <div class="stat-value" style="font-size: 13px;"><?= date('d/m', strtotime($order['reception_date'])) ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Mecánico</div>
                        <div class="stat-value" style="font-size: 13px;"><?= htmlspecialchars($order['mechanic_name'] ?? 'Sin asignar') ?></div>
                    </div>
                </div>
            </div>

            <?php if ($order['budget']): ?>
                <div class="section">
                    <h3>Presupuesto</h3>
                    <p style="color: white; font-size: 20px; font-weight: 700;">₡<?= number_format($order['budget']['total'], 0) ?></p>
                    <p style="color: #94a3b8; font-size: 12px; margin-top: 4px;">Estado: <?= htmlspecialchars($order['budget']['approval_status']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['work_logs'])): ?>
                <div class="section">
                    <h3>Registro de trabajo</h3>
                    <?php foreach ($order['work_logs'] as $log): ?>
                        <div style="font-size: 12px; color: #cbd5e1; margin-bottom: 8px;">
                            <strong style="color: white;"><?= htmlspecialchars($log['mechanic_name']) ?></strong><br>
                            <?= $log['start_time'] ? date('d/m H:i', strtotime($log['start_time'])) : '' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/orders.php';
require_once __DIR__ . '/app/includes/photos.php';
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
$photos = get_order_photos($orderId);
$photosByAngle = [];
foreach ($photos as $p) {
    $photosByAngle[$p['angle']][] = $p;
}
$csrf = csrf_token();

$severityLabels = ['green' => 'Verde', 'yellow' => 'Amarillo', 'red' => 'Rojo'];
$categoryLabels = ['mechanic' => 'Mecánica', 'electric' => 'Eléctrica'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($order['plate'] ?? 'Sin placa') ?> — Centro Automotriz Arley</title>
    <link rel="icon" type="image/png" href="app/assets/images/favicon.png">
    <link rel="stylesheet" href="app/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/app/includes/topbar.php'; ?>

<div class="page">
    <div class="page-header">
        <div>
            <h1><?= htmlspecialchars($order['plate'] ?? 'Sin placa') ?> — <?= htmlspecialchars(trim(($order['vehicle_brand'] ?? '') . ' ' . ($order['vehicle_model'] ?? ''))) ?: 'Sin datos de vehículo' ?></h1>
            <p>Dueño: <?= htmlspecialchars($order['customer_name']) ?>
                <?= $order['customer_cedula'] ? '· Céd. ' . htmlspecialchars($order['customer_cedula']) : '' ?>
                <?= $order['customer_phone'] ? '· ' . htmlspecialchars($order['customer_phone']) : '' ?>
                <?php if ($order['vin']): ?> · VIN: <?= htmlspecialchars($order['vin']) ?><?php endif; ?>
            </p>
            <p style="color: #94a3b8; font-size: 12px;">
                <?= implode(' · ', array_filter([
                    $order['color'] ? 'Color: ' . htmlspecialchars($order['color']) : null,
                    $order['engine'] ? 'Motor: ' . htmlspecialchars($order['engine']) : null,
                    $order['mileage'] ? number_format($order['mileage']) . ' km' : null,
                    $order['fuel_type'] ? htmlspecialchars($order['fuel_type']) : null,
                ])) ?: 'Sin datos adicionales del vehículo' ?>
            </p>
            <?php if (!empty($order['dropoff_name'])): ?>
                <p style="color: #fcd34d;">Entregó: <?= htmlspecialchars($order['dropoff_name']) ?> <?= $order['dropoff_phone'] ? '· ' . htmlspecialchars($order['dropoff_phone']) : '' ?></p>
            <?php endif; ?>
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

            <!-- Inspección visual (fotos de ingreso) -->
            <div class="section">
                <h3>Inspección visual — fotos de ingreso</h3>
                <p style="color: #94a3b8; font-size: 12px; margin-bottom: 1rem;">Toca cada punto del diagrama para tomar o subir la foto de esa parte del vehículo.</p>

                <div class="car-diagram-wrapper">
                    <svg class="car-diagram" viewBox="0 0 300 520" xmlns="http://www.w3.org/2000/svg">
                        <rect x="70" y="40" width="160" height="440" rx="40" fill="rgba(59,130,246,0.08)" stroke="rgba(59,130,246,0.4)" stroke-width="2"/>
                        <rect x="95" y="130" width="110" height="260" rx="16" fill="rgba(59,130,246,0.05)" stroke="rgba(59,130,246,0.25)" stroke-width="1.5"/>
                        <circle cx="60" cy="110" r="16" fill="rgba(15,23,42,0.6)" stroke="rgba(59,130,246,0.3)"/>
                        <circle cx="240" cy="110" r="16" fill="rgba(15,23,42,0.6)" stroke="rgba(59,130,246,0.3)"/>
                        <circle cx="60" cy="410" r="16" fill="rgba(15,23,42,0.6)" stroke="rgba(59,130,246,0.3)"/>
                        <circle cx="240" cy="410" r="16" fill="rgba(15,23,42,0.6)" stroke="rgba(59,130,246,0.3)"/>

                        <?php foreach ([
                            'front'    => [150, 55],
                            'back'     => [150, 465],
                            'left'     => [55, 260],
                            'right'    => [245, 260],
                            'roof'     => [150, 180],
                            'interior' => [150, 340],
                            'wheel_fl' => [60, 110],
                            'wheel_fr' => [240, 110],
                            'wheel_rl' => [60, 410],
                            'wheel_rr' => [240, 410],
                        ] as $angle => [$cx, $cy]): ?>
                            <g class="photo-point" data-angle="<?= $angle ?>" data-has-photo="<?= isset($photosByAngle[$angle]) ? '1' : '0' ?>">
                                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="14" class="photo-point-circle"/>
                                <text x="<?= $cx ?>" y="<?= $cy + 4 ?>" class="photo-point-icon" text-anchor="middle"><?= isset($photosByAngle[$angle]) ? '✓' : '+' ?></text>
                            </g>
                        <?php endforeach; ?>
                    </svg>

                    <div class="car-diagram-legend">
                        <?php foreach (ORDER_PHOTO_ANGLES as $angle => $label): ?>
                            <?php if ($angle === 'extra') continue; ?>
                            <div class="legend-item" data-angle="<?= $angle ?>">
                                <span class="legend-dot <?= isset($photosByAngle[$angle]) ? 'filled' : '' ?>"></span>
                                <?= htmlspecialchars($label) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <input type="file" id="photo_file_input" accept="image/*" capture="environment" style="display:none;">

                <div class="photo-thumbnails" id="photo_thumbnails">
                    <?php foreach ($photos as $p): ?>
                        <div class="photo-thumb" data-photo-id="<?= (int) $p['id'] ?>">
                            <img src="serve_photo.php?order_id=<?= $orderId ?>&id=<?= (int) $p['id'] ?>" alt="<?= htmlspecialchars(ORDER_PHOTO_ANGLES[$p['angle']] ?? $p['angle']) ?>">
                            <span class="photo-thumb-label"><?= htmlspecialchars(ORDER_PHOTO_ANGLES[$p['angle']] ?? $p['angle']) ?></span>
                            <?php if (can(PERM_ORDERS_CREATE) || can(PERM_ORDERS_WORK)): ?>
                                <button type="button" class="photo-thumb-delete" data-photo-id="<?= (int) $p['id'] ?>">×</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-secondary" id="add_extra_photo_btn" style="margin-top: 1rem;">+ Agregar foto adicional</button>
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

            <?php if ($order['requested_repairs']): ?>
                <div class="section">
                    <h3>Reparaciones solicitadas</h3>
                    <p style="color: #cbd5e1; font-size: 13px;"><?= nl2br(htmlspecialchars($order['requested_repairs'])) ?></p>
                </div>
            <?php endif; ?>

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

<script>
    const ORDER_DETAIL_CSRF = <?= json_encode($csrf) ?>;
    const ORDER_DETAIL_ID = <?= (int) $orderId ?>;
</script>
<script src="app/assets/js/order_detail.js"></script>

</body>
</html>

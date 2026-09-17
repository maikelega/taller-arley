<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/orders.php';
require_login();
require_permission(PERM_ORDERS_CREATE);

$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página e intente de nuevo.';
    } else {
        $result = create_order($_POST);
        if ($result['ok']) {
            header('Location: order_detail.php?id=' . $result['order_id']);
            exit;
        }
        $error = $result['error'];
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepción de vehículo — Taller Arley</title>
    <link rel="stylesheet" href="app/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/app/includes/topbar.php'; ?>

<div class="page">
    <div class="page-header">
        <div>
            <h1>Recepción de vehículo</h1>
            <p>Registra una nueva orden de trabajo</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">← Volver</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="order_new.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Cliente <span class="required">*</span></label>
                    <input type="text" name="customer_name" class="form-control" placeholder="Nombre completo" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="customer_phone" class="form-control" placeholder="8888-8888" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Correo (opcional)</label>
                <input type="email" name="customer_email" class="form-control" placeholder="cliente@correo.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Placa <span class="required">*</span></label>
                    <input type="text" name="plate" class="form-control" placeholder="SJN 1234" required value="<?= htmlspecialchars($_POST['plate'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Año</label>
                    <input type="number" name="vehicle_year" class="form-control" placeholder="2020" min="1980" max="2030" value="<?= htmlspecialchars($_POST['vehicle_year'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="vehicle_brand" class="form-control" placeholder="Toyota" value="<?= htmlspecialchars($_POST['vehicle_brand'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="vehicle_model" class="form-control" placeholder="Camry" value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Notas de recepción</label>
                <textarea name="notes" class="form-control" placeholder="Daños visibles, motivo de la visita, etc."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Registrar orden</button>
        </form>
    </div>
</div>

</body>
</html>

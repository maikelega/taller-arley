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
    <title>Recepción de vehículo — Centro Automotriz Arley</title>
    <link rel="icon" type="image/png" href="app/assets/images/favicon.png">
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
        <form method="POST" action="order_new.php" novalidate id="order-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="customer_id" id="customer_id" value="">

            <div class="form-group autocomplete-wrapper">
                <label>Cliente <span class="required">*</span></label>
                <input type="text" name="customer_name" id="customer_name" class="form-control"
                       placeholder="Empieza a escribir el nombre..." autocomplete="off" required
                       value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                <div class="autocomplete-dropdown" id="customer_dropdown"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="customer_phone" id="customer_phone" class="form-control" placeholder="8888-8888" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Correo (opcional)</label>
                    <input type="email" name="customer_email" id="customer_email" class="form-control" placeholder="cliente@correo.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
                </div>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" id="is_owner_checkbox" checked>
                <span>Quien entrega el vehículo es el dueño</span>
            </label>

            <div class="dropoff-fields" id="dropoff_fields">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre de quien entrega</label>
                        <input type="text" name="dropoff_name" class="form-control" placeholder="Nombre completo">
                    </div>
                    <div class="form-group">
                        <label>Teléfono de contacto</label>
                        <input type="tel" name="dropoff_phone" class="form-control" placeholder="8888-8888">
                    </div>
                </div>
            </div>

            <div class="form-group autocomplete-wrapper">
                <label>Placa <span class="required">*</span></label>
                <input type="text" name="plate" id="plate" class="form-control"
                       placeholder="SJN 1234" autocomplete="off" required
                       value="<?= htmlspecialchars($_POST['plate'] ?? '') ?>">
                <div class="autocomplete-dropdown" id="plate_dropdown"></div>
                <div class="field-hint" id="vehicle_found_hint" style="display:none;">✓ Vehículo encontrado — datos autocompletados</div>
            </div>

            <div class="form-group">
                <label>VIN / Número de chasis</label>
                <div class="input-with-btn">
                    <input type="text" name="vin" id="vin" class="form-control" placeholder="17 caracteres" maxlength="17" style="text-transform: uppercase;" value="<?= htmlspecialchars($_POST['vin'] ?? '') ?>">
                    <button type="button" class="btn-icon" id="open_scanner_btn">📷 Escanear</button>
                    <button type="button" class="btn-icon" id="decode_vin_btn">🔍 Decodificar</button>
                </div>
                <div class="field-hint" id="vin_decode_status"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="vehicle_brand" id="vehicle_brand" class="form-control" placeholder="Toyota" value="<?= htmlspecialchars($_POST['vehicle_brand'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="vehicle_model" id="vehicle_model" class="form-control" placeholder="Camry" value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Año</label>
                <input type="number" name="vehicle_year" id="vehicle_year" class="form-control" placeholder="2020" min="1980" max="2030" style="max-width: 150px;" value="<?= htmlspecialchars($_POST['vehicle_year'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Notas de recepción</label>
                <textarea name="notes" class="form-control" placeholder="Daños visibles, motivo de la visita, etc."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Registrar orden</button>
        </form>
    </div>
</div>

<!-- ── Modal escáner VIN ─────────────────────────────────── -->
<div class="scanner-modal-backdrop" id="scanner_backdrop">
    <div class="scanner-modal">
        <h3>Escanear VIN</h3>
        <div class="scanner-tabs">
            <div class="scanner-tab active" id="scanner_tab_barcode">Código de barras</div>
            <div class="scanner-tab" id="scanner_tab_text">Texto (OCR)</div>
        </div>
        <video id="scanner_video" autoplay playsinline muted></video>
        <div class="scanner-status" id="scanner_status"></div>
        <div class="scanner-actions">
            <button type="button" class="btn btn-primary" id="capture_text_btn" style="display:none;">Capturar</button>
            <button type="button" class="btn btn-secondary" id="close_scanner_btn">Cerrar</button>
        </div>
    </div>
</div>

<script src="app/assets/js/order_new.js"></script>

</body>
</html>

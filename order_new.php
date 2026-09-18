<?php
require_once __DIR__ . '/app/includes/auth.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/orders.php';
require_once __DIR__ . '/app/includes/photos.php';
require_login();
require_permission(PERM_ORDERS_CREATE);

$user = current_user();
$error = '';

// Token de fotos temporales — se reusa si el form se re-muestra por un
// error de validación (para no perder las fotos ya subidas), o se genera
// uno nuevo al cargar la página por primera vez.
$tempToken = $_POST['temp_token'] ?? '';
if (!is_valid_temp_token($tempToken)) {
    $tempToken = bin2hex(random_bytes(16));
}

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
            <input type="hidden" name="temp_token" value="<?= htmlspecialchars($tempToken) ?>">
            <input type="hidden" name="customer_id" id="customer_id" value="">
            <input type="hidden" name="vehicle_id" id="vehicle_id" value="">

            <div class="form-group autocomplete-wrapper">
                <label>Cliente <span class="required">*</span></label>
                <input type="text" name="customer_name" id="customer_name" class="form-control"
                       placeholder="Empieza a escribir el nombre..." autocomplete="off" required
                       value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                <div class="autocomplete-dropdown" id="customer_dropdown"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Cédula de identidad</label>
                    <input type="text" name="customer_cedula" id="customer_cedula" class="form-control" placeholder="1-2345-6789" value="<?= htmlspecialchars($_POST['customer_cedula'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="customer_phone" id="customer_phone" class="form-control" placeholder="8888-8888" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Correo (opcional)</label>
                <input type="email" name="customer_email" id="customer_email" class="form-control" placeholder="cliente@correo.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
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

            <!-- Vehículos ya asociados al cliente elegido — aparece solo si el cliente tiene alguno -->
            <div class="form-group" id="customer_vehicles_wrapper" style="display:none;">
                <label>Vehículos de este cliente</label>
                <div id="customer_vehicles_list"></div>
                <div class="field-hint">Elige uno, o ingresa los datos abajo para un vehículo nuevo.</div>
            </div>

            <div class="form-row">
                <div class="form-group autocomplete-wrapper">
                    <label>Placa</label>
                    <input type="text" name="plate" id="plate" class="form-control"
                           placeholder="SJN 1234" autocomplete="off"
                           value="<?= htmlspecialchars($_POST['plate'] ?? '') ?>">
                    <div class="autocomplete-dropdown" id="plate_dropdown"></div>
                </div>
                <div class="form-group">
                    <label>VIN / Número de chasis</label>
                    <div class="input-with-btn">
                        <input type="text" name="vin" id="vin" class="form-control" placeholder="17 caracteres" maxlength="17" style="text-transform: uppercase;" value="<?= htmlspecialchars($_POST['vin'] ?? '') ?>">
                        <button type="button" class="btn-icon" id="open_scanner_btn">📷</button>
                        <button type="button" class="btn-icon" id="decode_vin_btn">🔍</button>
                    </div>
                </div>
            </div>
            <div class="field-hint" id="plate_vin_hint">Ingresa al menos la placa o el VIN.</div>
            <div class="field-hint" id="vehicle_found_hint" style="display:none;">✓ Vehículo encontrado — datos autocompletados</div>
            <div class="field-hint" id="vin_decode_status"></div>

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

            <div class="form-row">
                <div class="form-group">
                    <label>Año</label>
                    <input type="number" name="vehicle_year" id="vehicle_year" class="form-control" placeholder="2020" min="1980" max="2030" value="<?= htmlspecialchars($_POST['vehicle_year'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="color" id="color" class="form-control" placeholder="Blanco" value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Motor</label>
                    <input type="text" name="engine" id="engine" class="form-control" placeholder="4 cil 2.0L" value="<?= htmlspecialchars($_POST['engine'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Kilometraje</label>
                    <input type="number" name="mileage" id="mileage" class="form-control" placeholder="85000" min="0" value="<?= htmlspecialchars($_POST['mileage'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Combustible</label>
                <select name="fuel_type" id="fuel_type" class="form-control" style="max-width: 220px;">
                    <option value="">Sin especificar</option>
                    <option value="Gasoline">Gasolina</option>
                    <option value="Diesel">Diésel</option>
                    <option value="Hybrid">Híbrido</option>
                    <option value="Electric">Eléctrico</option>
                    <option value="GLP">GLP</option>
                </select>
            </div>

            <div class="section" style="padding: 0; background: none; border: none; margin: 1.5rem 0;">
                <h3 style="margin-bottom: 0.5rem;">Inspección visual — fotos de ingreso</h3>
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
                            <g class="photo-point" data-angle="<?= $angle ?>" data-has-photo="0">
                                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="14" class="photo-point-circle"/>
                                <text x="<?= $cx ?>" y="<?= $cy + 4 ?>" class="photo-point-icon" text-anchor="middle">+</text>
                            </g>
                        <?php endforeach; ?>
                    </svg>

                    <div class="car-diagram-legend">
                        <?php foreach (ORDER_PHOTO_ANGLES as $angle => $label): ?>
                            <?php if ($angle === 'extra') continue; ?>
                            <div class="legend-item" data-angle="<?= $angle ?>">
                                <span class="legend-dot"></span>
                                <?= htmlspecialchars($label) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <input type="file" id="photo_file_input" accept="image/*" capture="environment" style="display:none;">

                <div class="photo-thumbnails" id="photo_thumbnails"></div>

                <button type="button" class="btn btn-secondary" id="add_extra_photo_btn" style="margin-top: 1rem;">+ Agregar foto adicional</button>
            </div>

            <div class="form-group">
                <label>Reparaciones solicitadas</label>
                <textarea name="requested_repairs" class="form-control" placeholder="Qué pide el cliente que se revise o repare"><?= htmlspecialchars($_POST['requested_repairs'] ?? '') ?></textarea>
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

<script>
    const ORDER_NEW_CSRF = <?= json_encode($csrf) ?>;
    const ORDER_NEW_TEMP_TOKEN = <?= json_encode($tempToken) ?>;
</script>
<script src="app/assets/js/order_new.js"></script>

</body>
</html>

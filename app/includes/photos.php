<?php
/**
 * Fotos de inspección de órdenes — Centro Automotriz Arley
 *
 * Nunca se sirven directo desde /app/uploads/ (privacidad — igual que
 * fotos de FHJ). Siempre vía serve_photo.php, gateado por auth.
 */

require_once __DIR__ . '/db.php';

const ORDER_PHOTO_ANGLES = [
    'front'      => 'Frente',
    'back'       => 'Atrás',
    'left'       => 'Lateral izquierdo',
    'right'      => 'Lateral derecho',
    'roof'       => 'Techo / capó',
    'interior'   => 'Interior / tablero',
    'wheel_fl'   => 'Llanta del. izquierda',
    'wheel_fr'   => 'Llanta del. derecha',
    'wheel_rl'   => 'Llanta tras. izquierda',
    'wheel_rr'   => 'Llanta tras. derecha',
    'extra'      => 'Foto adicional',
];

const MAX_PHOTO_BYTES = 8 * 1024 * 1024; // 8MB
const ALLOWED_PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Guarda una foto subida (de $_FILES) para una orden, en un ángulo dado.
 * "extra" permite múltiples fotos; los demás ángulos reemplazan la foto
 * anterior si ya existía una (evita duplicados accidentales al re-tomar).
 */
function upload_order_photo(int $orderId, string $angle, array $file): array
{
    if (!array_key_exists($angle, ORDER_PHOTO_ANGLES)) {
        return ['ok' => false, 'error' => 'Ángulo de foto inválido.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir el archivo.'];
    }
    if ($file['size'] > MAX_PHOTO_BYTES) {
        return ['ok' => false, 'error' => 'La foto es demasiado grande (máx. 8MB).'];
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_PHOTO_MIMES, true)) {
        return ['ok' => false, 'error' => 'Formato de imagen no permitido (solo JPG, PNG, WEBP).'];
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $dir = __DIR__ . '/../uploads/orders/' . $orderId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('[upload_order_photo] No se pudo crear directorio: ' . $dir);
        return ['ok' => false, 'error' => 'Error al guardar la foto.'];
    }

    $filename = $angle . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        error_log('[upload_order_photo] move_uploaded_file falló: ' . $destPath);
        return ['ok' => false, 'error' => 'Error al guardar la foto.'];
    }

    $relativePath = 'orders/' . $orderId . '/' . $filename;

    try {
        $pdo = Database::getConnection();

        // Ángulos fijos (no "extra") reemplazan la foto anterior de ese
        // ángulo — evita acumular duplicados si el mecánico re-toma la foto.
        if ($angle !== 'extra') {
            $old = $pdo->prepare('SELECT id, photo_path FROM order_photos WHERE order_id = :oid AND angle = :angle LIMIT 1');
            $old->execute([':oid' => $orderId, ':angle' => $angle]);
            $oldRow = $old->fetch();
            if ($oldRow) {
                $oldFile = __DIR__ . '/../uploads/' . $oldRow['photo_path'];
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
                $pdo->prepare('DELETE FROM order_photos WHERE id = :id')->execute([':id' => $oldRow['id']]);
            }
        }

        $pdo->prepare(
            'INSERT INTO order_photos (order_id, photo_path, photo_type, angle) VALUES (:oid, :path, :type, :angle)'
        )->execute([
            ':oid'   => $orderId,
            ':path'  => $relativePath,
            ':type'  => 'entry',
            ':angle' => $angle,
        ]);

        return ['ok' => true, 'photo_id' => (int) $pdo->lastInsertId(), 'angle' => $angle];
    } catch (PDOException $e) {
        @unlink($destPath);
        error_log('[upload_order_photo] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Error al registrar la foto.'];
    }
}

/**
 * Valida que un token temporal tenga el formato esperado (32 hex chars,
 * generado por bin2hex(random_bytes(16))) — crítico para que nunca se
 * use como parte de una ruta de archivo sin sanear (path traversal).
 */
function is_valid_temp_token(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
}

/**
 * Sube una foto a un área temporal, antes de que exista la orden en BD
 * (recepción: se quiere tomar fotos en la misma pantalla del formulario,
 * pero la orden aún no tiene id). Se asocian a la orden real en
 * attach_temp_photos_to_order() una vez creada.
 */
function upload_temp_photo(string $tempToken, string $angle, array $file): array
{
    if (!is_valid_temp_token($tempToken)) {
        return ['ok' => false, 'error' => 'Sesión de fotos inválida — recarga la página.'];
    }
    if (!array_key_exists($angle, ORDER_PHOTO_ANGLES)) {
        return ['ok' => false, 'error' => 'Ángulo de foto inválido.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir el archivo.'];
    }
    if ($file['size'] > MAX_PHOTO_BYTES) {
        return ['ok' => false, 'error' => 'La foto es demasiado grande (máx. 8MB).'];
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_PHOTO_MIMES, true)) {
        return ['ok' => false, 'error' => 'Formato de imagen no permitido (solo JPG, PNG, WEBP).'];
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $dir = __DIR__ . '/../uploads/temp/' . $tempToken;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('[upload_temp_photo] No se pudo crear directorio: ' . $dir);
        return ['ok' => false, 'error' => 'Error al guardar la foto.'];
    }

    // Ángulos fijos (no "extra") reemplazan cualquier archivo anterior de
    // ese ángulo en el área temporal — mismo criterio que en órdenes reales.
    if ($angle !== 'extra') {
        foreach (glob($dir . '/' . $angle . '_*') ?: [] as $old) {
            @unlink($old);
        }
    }

    $filename = $angle . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        error_log('[upload_temp_photo] move_uploaded_file falló: ' . $destPath);
        return ['ok' => false, 'error' => 'Error al guardar la foto.'];
    }

    return ['ok' => true, 'filename' => $filename, 'angle' => $angle];
}

function delete_temp_photo(string $tempToken, string $filename): array
{
    if (!is_valid_temp_token($tempToken)) {
        return ['ok' => false, 'error' => 'Sesión de fotos inválida.'];
    }
    // El filename lo generamos nosotros (angle_hex.ext) — igual se valida
    // el patrón para no confiar ciegamente en el input del cliente.
    if (!preg_match('/^[a-z_]+_[a-f0-9]{12}\.(jpg|png|webp)$/', $filename)) {
        return ['ok' => false, 'error' => 'Nombre de archivo inválido.'];
    }

    $path = __DIR__ . '/../uploads/temp/' . $tempToken . '/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
    return ['ok' => true];
}

/**
 * Mueve todas las fotos del área temporal a la orden ya creada, y las
 * registra en order_photos. Se llama justo después de crear la orden.
 */
function attach_temp_photos_to_order(string $tempToken, int $orderId): void
{
    if (!is_valid_temp_token($tempToken)) {
        return;
    }

    $tempDir = __DIR__ . '/../uploads/temp/' . $tempToken;
    if (!is_dir($tempDir)) {
        return;
    }

    $destDir = __DIR__ . '/../uploads/orders/' . $orderId;
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        error_log('[attach_temp_photos_to_order] No se pudo crear directorio: ' . $destDir);
        return;
    }

    $pdo = Database::getConnection();

    foreach (glob($tempDir . '/*') ?: [] as $tempFile) {
        $filename = basename($tempFile);
        // El ángulo es el prefijo antes del último "_" (ej. wheel_fl_a1b2c3d4e5f6.jpg → wheel_fl)
        if (!preg_match('/^([a-z_]+)_[a-f0-9]{12}\.(jpg|png|webp)$/', $filename, $m)) {
            continue;
        }
        $angle = $m[1];
        if (!array_key_exists($angle, ORDER_PHOTO_ANGLES)) {
            continue;
        }

        $destFile = $destDir . '/' . $filename;
        if (!rename($tempFile, $destFile)) {
            continue;
        }

        try {
            $pdo->prepare(
                'INSERT INTO order_photos (order_id, photo_path, photo_type, angle) VALUES (:oid, :path, :type, :angle)'
            )->execute([
                ':oid'   => $orderId,
                ':path'  => 'orders/' . $orderId . '/' . $filename,
                ':type'  => 'entry',
                ':angle' => $angle,
            ]);
        } catch (PDOException $e) {
            error_log('[attach_temp_photos_to_order] ' . $e->getMessage());
        }
    }

    @rmdir($tempDir);
}

function get_order_photos(int $orderId): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM order_photos WHERE order_id = :oid ORDER BY uploaded_at');
    $stmt->execute([':oid' => $orderId]);
    return $stmt->fetchAll();
}

function delete_order_photo(int $photoId, int $orderId): array
{
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT photo_path FROM order_photos WHERE id = :id AND order_id = :oid LIMIT 1');
        $stmt->execute([':id' => $photoId, ':oid' => $orderId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => 'Foto no encontrada.'];
        }

        $filePath = __DIR__ . '/../uploads/' . $row['photo_path'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }
        $pdo->prepare('DELETE FROM order_photos WHERE id = :id')->execute([':id' => $photoId]);

        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('[delete_order_photo] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Error al eliminar la foto.'];
    }
}

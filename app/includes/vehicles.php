<?php
/**
 * Lógica de vehículos — Centro Automotriz Arley
 * Vehículo = entidad reutilizable entre visitas (buscable por placa/VIN).
 */

require_once __DIR__ . '/db.php';

function find_vehicle_by_plate(string $plate): ?array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email
         FROM vehicles v
         INNER JOIN customers c ON v.customer_id = c.id
         WHERE v.plate = :plate LIMIT 1'
    );
    $stmt->execute([':plate' => mb_strtoupper(trim($plate))]);
    return $stmt->fetch() ?: null;
}

function find_vehicle_by_vin(string $vin): ?array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email
         FROM vehicles v
         INNER JOIN customers c ON v.customer_id = c.id
         WHERE v.vin = :vin LIMIT 1'
    );
    $stmt->execute([':vin' => mb_strtoupper(trim($vin))]);
    return $stmt->fetch() ?: null;
}

/**
 * Búsqueda tipo autocomplete por placa (prefijo). Usada por el
 * endpoint AJAX de order_new.php.
 */
function search_vehicles(string $query, int $limit = 8): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT v.id, v.plate, v.vin, v.brand, v.model, v.year,
                c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email
         FROM vehicles v
         INNER JOIN customers c ON v.customer_id = c.id
         WHERE v.plate LIKE :q1 OR v.vin LIKE :q2
         ORDER BY v.plate
         LIMIT :lim'
    );
    $prefix = mb_strtoupper($query) . '%';
    $stmt->bindValue(':q1', $prefix);
    $stmt->bindValue(':q2', $prefix);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Crea o actualiza (si ya existe por placa) el vehículo, y lo asocia
 * al cliente indicado como dueño actual.
 */
function create_or_update_vehicle(array $data): array
{
    $plate = mb_strtoupper(trim($data['plate'] ?? ''));
    $vin = mb_strtoupper(trim($data['vin'] ?? ''));
    $customerId = (int) ($data['customer_id'] ?? 0);
    $brand = trim($data['vehicle_brand'] ?? '');
    $model = trim($data['vehicle_model'] ?? '');
    $year = (int) ($data['vehicle_year'] ?? 0);
    $engine = trim($data['engine'] ?? '');
    $fuelType = trim($data['fuel_type'] ?? '');
    $transmission = trim($data['transmission'] ?? '');

    if ($plate === '' || $customerId <= 0) {
        return ['ok' => false, 'error' => 'Placa y cliente son obligatorios.'];
    }
    if (mb_strlen($plate) > 20) {
        return ['ok' => false, 'error' => 'La placa es demasiado larga.'];
    }
    if ($vin !== '' && mb_strlen($vin) !== 17) {
        return ['ok' => false, 'error' => 'El VIN debe tener exactamente 17 caracteres.'];
    }

    try {
        $pdo = Database::getConnection();
        $existing = find_vehicle_by_plate($plate);

        if ($existing) {
            $pdo->prepare(
                'UPDATE vehicles SET customer_id = :cid, vin = COALESCE(NULLIF(:vin, \'\'), vin),
                    brand = COALESCE(NULLIF(:brand, \'\'), brand), model = COALESCE(NULLIF(:model, \'\'), model),
                    year = COALESCE(NULLIF(:year, 0), year), engine = COALESCE(NULLIF(:engine, \'\'), engine),
                    fuel_type = COALESCE(NULLIF(:fuel, \'\'), fuel_type),
                    transmission = COALESCE(NULLIF(:trans, \'\'), transmission)
                 WHERE id = :id'
            )->execute([
                ':cid' => $customerId, ':vin' => $vin, ':brand' => $brand, ':model' => $model,
                ':year' => $year, ':engine' => $engine, ':fuel' => $fuelType, ':trans' => $transmission,
                ':id' => $existing['id'],
            ]);
            return ['ok' => true, 'vehicle_id' => (int) $existing['id']];
        }

        $pdo->prepare(
            'INSERT INTO vehicles (customer_id, plate, vin, brand, model, year, engine, fuel_type, transmission)
             VALUES (:cid, :plate, :vin, :brand, :model, :year, :engine, :fuel, :trans)'
        )->execute([
            ':cid'   => $customerId,
            ':plate' => $plate,
            ':vin'   => $vin !== '' ? $vin : null,
            ':brand' => $brand !== '' ? $brand : null,
            ':model' => $model !== '' ? $model : null,
            ':year'  => $year > 0 ? $year : null,
            ':engine' => $engine !== '' ? $engine : null,
            ':fuel'   => $fuelType !== '' ? $fuelType : null,
            ':trans'  => $transmission !== '' ? $transmission : null,
        ]);

        return ['ok' => true, 'vehicle_id' => (int) $pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log('[create_or_update_vehicle] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al guardar el vehículo.'];
    }
}

/**
 * Decodifica un VIN vía NHTSA vPIC (gratis, sin API key).
 * Devuelve solo los campos que nos interesan, ya limpios.
 */
function decode_vin_nhtsa(string $vin): array
{
    $vin = mb_strtoupper(trim($vin));
    if (mb_strlen($vin) !== 17) {
        return ['ok' => false, 'error' => 'El VIN debe tener 17 caracteres.'];
    }

    $url = 'https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/' . urlencode($vin) . '?format=json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('[decode_vin_nhtsa] HTTP ' . $httpCode . ' — ' . $curlError);
        return ['ok' => false, 'error' => 'No se pudo conectar con el servicio de decodificación.'];
    }

    $data = json_decode($response, true);
    $result = $data['Results'][0] ?? null;
    if (!$result) {
        return ['ok' => false, 'error' => 'Respuesta inesperada del servicio.'];
    }

    // ErrorCode "0" = decodificado correctamente
    if (($result['ErrorCode'] ?? '') !== '0' && ($result['ErrorText'] ?? '') !== '') {
        // No es fatal — algunos VINs decodifican parcialmente. Continuamos
        // pero avisamos si no hay marca (señal de VIN inválido).
        if (empty($result['Make'])) {
            return ['ok' => false, 'error' => 'VIN no reconocido: ' . ($result['ErrorText'] ?? 'verifique el número.')];
        }
    }

    $displacement = $result['DisplacementL'] ?? '';
    $displacement = $displacement !== '' ? round((float) $displacement, 1) . 'L' : '';
    $cylinders = $result['EngineCylinders'] ?? '';
    $engine = trim(($cylinders !== '' ? $cylinders . ' cil ' : '') . $displacement);

    return [
        'ok' => true,
        'brand' => $result['Make'] ?? '',
        'model' => $result['Model'] ?? '',
        'year' => $result['ModelYear'] ?? '',
        'engine' => $engine,
        'fuel_type' => $result['FuelTypePrimary'] ?? '',
        'transmission' => $result['TransmissionStyle'] ?? '',
    ];
}

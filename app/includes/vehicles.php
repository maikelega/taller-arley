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
 * Vehículos asociados a un cliente — para que, al elegir un cliente
 * existente en la recepción, se pueda elegir cuál de sus vehículos
 * es el que trae hoy (en vez de recapturar todo a mano).
 */
function get_customer_vehicles(int $customerId): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT id, plate, vin, brand, model, year, color, mileage, engine, fuel_type, transmission
         FROM vehicles WHERE customer_id = :cid ORDER BY plate'
    );
    $stmt->execute([':cid' => $customerId]);
    return $stmt->fetchAll();
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
 * Crea o actualiza (si ya existe por placa o VIN) el vehículo, y lo
 * asocia al cliente indicado como dueño actual.
 *
 * La placa NO es obligatoria — pero se requiere placa O vin (al menos
 * uno), ya que ambos son formas válidas de identificar el vehículo
 * (útil cuando un vehículo aún no tiene placas asignadas, por ejemplo).
 */
function create_or_update_vehicle(array $data): array
{
    $plate = mb_strtoupper(trim($data['plate'] ?? ''));
    $vin = mb_strtoupper(trim($data['vin'] ?? ''));
    $customerId = (int) ($data['customer_id'] ?? 0);
    $brand = trim($data['vehicle_brand'] ?? '');
    $model = trim($data['vehicle_model'] ?? '');
    $year = (int) ($data['vehicle_year'] ?? 0);
    $color = trim($data['color'] ?? '');
    $mileage = (int) ($data['mileage'] ?? 0);
    $engine = trim($data['engine'] ?? '');
    $fuelType = trim($data['fuel_type'] ?? '');
    $transmission = trim($data['transmission'] ?? '');

    if ($customerId <= 0) {
        return ['ok' => false, 'error' => 'El cliente es obligatorio.'];
    }
    if ($plate === '' && $vin === '') {
        return ['ok' => false, 'error' => 'Ingrese al menos la placa o el VIN del vehículo.'];
    }
    if ($plate !== '' && mb_strlen($plate) > 20) {
        return ['ok' => false, 'error' => 'La placa es demasiado larga.'];
    }
    if ($vin !== '' && mb_strlen($vin) !== 17) {
        return ['ok' => false, 'error' => 'El VIN debe tener exactamente 17 caracteres.'];
    }

    try {
        $pdo = Database::getConnection();
        $existing = ($plate !== '' ? find_vehicle_by_plate($plate) : null)
            ?? ($vin !== '' ? find_vehicle_by_vin($vin) : null);

        if ($existing) {
            $pdo->prepare(
                'UPDATE vehicles SET customer_id = :cid,
                    plate = COALESCE(NULLIF(:plate, \'\'), plate),
                    vin = COALESCE(NULLIF(:vin, \'\'), vin),
                    brand = COALESCE(NULLIF(:brand, \'\'), brand), model = COALESCE(NULLIF(:model, \'\'), model),
                    year = COALESCE(NULLIF(:year, 0), year),
                    color = COALESCE(NULLIF(:color, \'\'), color),
                    mileage = COALESCE(NULLIF(:mileage, 0), mileage),
                    engine = COALESCE(NULLIF(:engine, \'\'), engine),
                    fuel_type = COALESCE(NULLIF(:fuel, \'\'), fuel_type),
                    transmission = COALESCE(NULLIF(:trans, \'\'), transmission)
                 WHERE id = :id'
            )->execute([
                ':cid' => $customerId, ':plate' => $plate, ':vin' => $vin, ':brand' => $brand, ':model' => $model,
                ':year' => $year, ':color' => $color, ':mileage' => $mileage,
                ':engine' => $engine, ':fuel' => $fuelType, ':trans' => $transmission,
                ':id' => $existing['id'],
            ]);
            return ['ok' => true, 'vehicle_id' => (int) $existing['id']];
        }

        $pdo->prepare(
            'INSERT INTO vehicles (customer_id, plate, vin, brand, model, year, color, mileage, engine, fuel_type, transmission)
             VALUES (:cid, :plate, :vin, :brand, :model, :year, :color, :mileage, :engine, :fuel, :trans)'
        )->execute([
            ':cid'   => $customerId,
            ':plate' => $plate !== '' ? $plate : null,
            ':vin'   => $vin !== '' ? $vin : null,
            ':brand' => $brand !== '' ? $brand : null,
            ':model' => $model !== '' ? $model : null,
            ':year'  => $year > 0 ? $year : null,
            ':color' => $color !== '' ? $color : null,
            ':mileage' => $mileage > 0 ? $mileage : null,
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
        // pero avisamos si no hay marca (señal de que NHTSA no pudo decodificar).
        if (empty($result['Make'])) {
            $errorText = $result['ErrorText'] ?? '';

            // NHTSA solo cubre vehículos vendidos/importados en EE.UU. — común
            // que falle con autos importados directo de Japón/Europa/Asia sin
            // pasar por EE.UU. (frecuente en Costa Rica). No es un error real
            // del sistema, así que el mensaje no debe sonar a fallo técnico.
            if (stripos($errorText, 'not registered with NHTSA') !== false) {
                return [
                    'ok' => false,
                    'warning' => true,
                    'error' => 'Este fabricante no vende en EE.UU., así que no está en la base de datos gratuita que usamos. Completa marca, modelo y año manualmente.',
                ];
            }

            return ['ok' => false, 'error' => 'VIN no reconocido — verifica el número o completa los datos manualmente.'];
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

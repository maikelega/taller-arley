<?php
/**
 * Lógica de órdenes de trabajo (Kanban) — Centro Automotriz Arley
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vehicles.php';
require_once __DIR__ . '/photos.php';

/**
 * Devuelve todas las OT activas (no cerradas) agrupadas por status_id,
 * listas para pintar el Kanban.
 */
function get_kanban_orders(): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->query(
        "SELECT o.id, v.plate, v.brand AS vehicle_brand, v.model AS vehicle_model, o.status_id,
                o.reception_date, o.estimated_completion,
                c.name AS customer_name,
                m.name AS mechanic_name,
                b.approval_status AS budget_status, b.total AS budget_total
         FROM orders o
         INNER JOIN customers c ON o.customer_id = c.id
         LEFT JOIN vehicles v ON o.vehicle_id = v.id
         LEFT JOIN users m ON o.assigned_mechanic_id = m.id
         LEFT JOIN order_budgets b ON b.order_id = o.id
         ORDER BY o.reception_date DESC"
    );
    $rows = $stmt->fetchAll();

    $byStatus = [];
    foreach ($rows as $row) {
        $byStatus[(int) $row['status_id']][] = $row;
    }
    return $byStatus;
}

function get_order_statuses(): array
{
    $pdo = Database::getConnection();
    return $pdo->query('SELECT id, name FROM order_statuses ORDER BY id')->fetchAll();
}

/**
 * Búsqueda tipo autocomplete de clientes por nombre o teléfono (prefijo).
 */
function search_customers(string $query, int $limit = 8): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT id, name, cedula, phone, email FROM customers
         WHERE name LIKE :q1 OR phone LIKE :q2
         ORDER BY name LIMIT :lim'
    );
    $like = '%' . $query . '%';
    $stmt->bindValue(':q1', $like);
    $stmt->bindValue(':q2', $like);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Busca un cliente por teléfono (llave natural para taller); si no
 * existe lo crea. Devuelve el customer_id.
 */
function find_or_create_customer(string $name, string $phone, string $email = '', string $cedula = ''): int
{
    $pdo = Database::getConnection();

    if ($phone !== '') {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (int) $existing['id'];
        }
    }

    $pdo->prepare('INSERT INTO customers (name, cedula, phone, email) VALUES (:name, :cedula, :phone, :email)')
        ->execute([
            ':name'   => $name,
            ':cedula' => $cedula !== '' ? $cedula : null,
            ':phone'  => $phone !== '' ? $phone : null,
            ':email'  => $email !== '' ? $email : null,
        ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Recepción de vehículo — crea la OT en estado "Recibido" (status_id=1).
 *
 * Cliente: si viene `customer_id` (seleccionado del autocomplete) se
 * reusa; si no, se crea uno nuevo con customer_name/phone/email/cedula.
 *
 * Vehículo: si viene `vehicle_id` (el usuario eligió uno de los
 * vehículos ya asociados al cliente) se usa directamente. Si no,
 * create_or_update_vehicle maneja "existe por placa/vin → actualiza"
 * vs "no existe → crea". Placa NO es obligatoria — se requiere placa
 * O vin (al menos uno).
 *
 * Contacto de entrega: dropoff_name/dropoff_phone son opcionales,
 * solo se llenan si quien entrega NO es el dueño (no crean un cliente).
 */
function create_order(array $data): array
{
    $customerId = (int) ($data['customer_id'] ?? 0);
    $customerName = trim($data['customer_name'] ?? '');
    $customerCedula = trim($data['customer_cedula'] ?? '');
    $customerPhone = trim($data['customer_phone'] ?? '');
    $customerEmail = trim($data['customer_email'] ?? '');
    $vehicleId = (int) ($data['vehicle_id'] ?? 0);
    $plate = mb_strtoupper(trim($data['plate'] ?? ''));
    $vin = mb_strtoupper(trim($data['vin'] ?? ''));
    $notes = trim($data['notes'] ?? '');
    $requestedRepairs = trim($data['requested_repairs'] ?? '');
    $dropoffName = trim($data['dropoff_name'] ?? '');
    $dropoffPhone = trim($data['dropoff_phone'] ?? '');

    if ($customerId <= 0 && $customerName === '') {
        return ['ok' => false, 'error' => 'El nombre del cliente es obligatorio.'];
    }
    if ($vehicleId <= 0 && $plate === '' && $vin === '') {
        return ['ok' => false, 'error' => 'Ingrese al menos la placa o el VIN del vehículo.'];
    }

    try {
        $pdo = Database::getConnection();

        // Solo bloquea si hay una OT activa (no cerrada) para ese mismo
        // vehículo — se detecta por vehicle_id si ya viene elegido, o por
        // placa/vin si es un vehículo nuevo/existente sin elegir aún.
        if ($vehicleId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM orders WHERE vehicle_id = :vid AND status_id != 8 LIMIT 1');
            $stmt->execute([':vid' => $vehicleId]);
        } else {
            // v.plate = '' / v.vin = '' nunca coincide con NULL en SQL, así que
            // pasar cadena vacía cuando el campo no viene es seguro (no genera
            // falsos positivos contra vehículos sin placa/vin registrado).
            $stmt = $pdo->prepare(
                "SELECT o.id FROM orders o
                 INNER JOIN vehicles v ON o.vehicle_id = v.id
                 WHERE o.status_id != 8 AND (v.plate = :plate OR v.vin = :vin)
                 LIMIT 1"
            );
            $stmt->execute([':plate' => $plate, ':vin' => $vin]);
        }
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Ya existe una orden activa para ese vehículo.'];
        }

        $pdo->beginTransaction();

        if ($customerId <= 0) {
            $customerId = find_or_create_customer($customerName, $customerPhone, $customerEmail, $customerCedula);
        }

        if ($vehicleId <= 0) {
            $vehicleResult = create_or_update_vehicle(array_merge($data, ['customer_id' => $customerId, 'plate' => $plate, 'vin' => $vin]));
            if (!$vehicleResult['ok']) {
                $pdo->rollBack();
                return $vehicleResult;
            }
            $vehicleId = $vehicleResult['vehicle_id'];
        }

        $pdo->prepare(
            'INSERT INTO orders (customer_id, vehicle_id, status_id, notes, requested_repairs, dropoff_name, dropoff_phone)
             VALUES (:cid, :vid, 1, :notes, :repairs, :dname, :dphone)'
        )->execute([
            ':cid'     => $customerId,
            ':vid'     => $vehicleId,
            ':notes'   => $notes !== '' ? $notes : null,
            ':repairs' => $requestedRepairs !== '' ? $requestedRepairs : null,
            ':dname'   => $dropoffName !== '' ? $dropoffName : null,
            ':dphone'  => $dropoffPhone !== '' ? $dropoffPhone : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $pdo->commit();

        // Fotos tomadas en la misma pantalla de recepción (antes de que la
        // orden existiera) — se mueven del área temporal a la orden real.
        $tempToken = trim($data['temp_token'] ?? '');
        if ($tempToken !== '') {
            attach_temp_photos_to_order($tempToken, $orderId);
        }

        return ['ok' => true, 'order_id' => $orderId];
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[create_order] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al registrar la orden.'];
    }
}

function get_order_detail(int $orderId): ?array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT o.*, c.name AS customer_name, c.cedula AS customer_cedula, c.phone AS customer_phone, c.email AS customer_email,
                v.plate, v.vin, v.brand AS vehicle_brand, v.model AS vehicle_model, v.year AS vehicle_year,
                v.color, v.mileage, v.engine, v.fuel_type, v.transmission,
                m.name AS mechanic_name, s.name AS status_name
         FROM orders o
         INNER JOIN customers c ON o.customer_id = c.id
         INNER JOIN order_statuses s ON o.status_id = s.id
         LEFT JOIN vehicles v ON o.vehicle_id = v.id
         LEFT JOIN users m ON o.assigned_mechanic_id = m.id
         WHERE o.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $dviStmt = $pdo->prepare('SELECT * FROM order_dvi WHERE order_id = :id ORDER BY id');
    $dviStmt->execute([':id' => $orderId]);
    $order['dvi'] = $dviStmt->fetchAll();

    $budgetStmt = $pdo->prepare('SELECT * FROM order_budgets WHERE order_id = :id LIMIT 1');
    $budgetStmt->execute([':id' => $orderId]);
    $order['budget'] = $budgetStmt->fetch() ?: null;

    if ($order['budget']) {
        $linesStmt = $pdo->prepare('SELECT * FROM budget_lines WHERE budget_id = :bid ORDER BY id');
        $linesStmt->execute([':bid' => $order['budget']['id']]);
        $order['budget']['lines'] = $linesStmt->fetchAll();
    }

    $logsStmt = $pdo->prepare(
        'SELECT w.*, u.name AS mechanic_name FROM work_logs w
         INNER JOIN users u ON w.mechanic_id = u.id
         WHERE w.order_id = :id ORDER BY w.start_time DESC'
    );
    $logsStmt->execute([':id' => $orderId]);
    $order['work_logs'] = $logsStmt->fetchAll();

    return $order;
}

/**
 * Mueve una OT a otro estado del Kanban. Valida transiciones básicas
 * (no saltar de Recibido directo a Cerrado, etc.) — reglas simples
 * para Fase 1, se puede endurecer después.
 */
function update_order_status(int $orderId, int $newStatusId, ?int $mechanicId = null): array
{
    try {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT status_id FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $orderId]);
        $current = $stmt->fetch();
        if (!$current) {
            return ['ok' => false, 'error' => 'Orden no encontrada.'];
        }

        $sql = 'UPDATE orders SET status_id = :status';
        $params = [':status' => $newStatusId, ':id' => $orderId];

        if ($mechanicId !== null) {
            $sql .= ', assigned_mechanic_id = :mech';
            $params[':mech'] = $mechanicId;
        }
        if ($newStatusId === 8) { // Cerrado
            $sql .= ', actual_completion = CURDATE()';
        }
        $sql .= ' WHERE id = :id';

        $pdo->prepare($sql)->execute($params);

        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('[update_order_status] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al actualizar el estado.'];
    }
}

/**
 * Registra una entrada de diagnóstico visual (DVI).
 */
function add_dvi(int $orderId, string $category, string $severity, string $notes, float $estimatedHours): array
{
    if (!in_array($category, ['mechanic', 'electric'], true)) {
        return ['ok' => false, 'error' => 'Categoría inválida.'];
    }
    if (!in_array($severity, ['green', 'yellow', 'red'], true)) {
        return ['ok' => false, 'error' => 'Severidad inválida.'];
    }

    try {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO order_dvi (order_id, category, severity, notes, estimated_hours)
             VALUES (:oid, :cat, :sev, :notes, :hours)'
        )->execute([
            ':oid'   => $orderId,
            ':cat'   => $category,
            ':sev'   => $severity,
            ':notes' => $notes !== '' ? $notes : null,
            ':hours' => $estimatedHours > 0 ? $estimatedHours : null,
        ]);
        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('[add_dvi] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al guardar el diagnóstico.'];
    }
}

function get_mechanics(): array
{
    $pdo = Database::getConnection();
    return $pdo->query(
        "SELECT id, name FROM users WHERE role_id = 2 AND is_active = 1 ORDER BY name"
    )->fetchAll();
}

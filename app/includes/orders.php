<?php
/**
 * Lógica de órdenes de trabajo (Kanban) — Taller Arley
 */

require_once __DIR__ . '/db.php';

/**
 * Devuelve todas las OT activas (no cerradas) agrupadas por status_id,
 * listas para pintar el Kanban.
 */
function get_kanban_orders(): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->query(
        "SELECT o.id, o.plate, o.vehicle_brand, o.vehicle_model, o.status_id,
                o.reception_date, o.estimated_completion,
                c.name AS customer_name,
                m.name AS mechanic_name,
                b.approval_status AS budget_status, b.total AS budget_total
         FROM orders o
         INNER JOIN customers c ON o.customer_id = c.id
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
 * Busca un cliente por teléfono (llave natural para taller); si no
 * existe lo crea. Devuelve el customer_id.
 */
function find_or_create_customer(string $name, string $phone, string $email = ''): int
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

    $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (:name, :phone, :email)')
        ->execute([
            ':name'  => $name,
            ':phone' => $phone !== '' ? $phone : null,
            ':email' => $email !== '' ? $email : null,
        ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Recepción de vehículo — crea la OT en estado "Recibido" (status_id=1).
 */
function create_order(array $data): array
{
    $customerName = trim($data['customer_name'] ?? '');
    $customerPhone = trim($data['customer_phone'] ?? '');
    $customerEmail = trim($data['customer_email'] ?? '');
    $plate = mb_strtoupper(trim($data['plate'] ?? ''));
    $brand = trim($data['vehicle_brand'] ?? '');
    $model = trim($data['vehicle_model'] ?? '');
    $year = (int) ($data['vehicle_year'] ?? 0);
    $notes = trim($data['notes'] ?? '');

    if ($customerName === '' || $plate === '') {
        return ['ok' => false, 'error' => 'Complete al menos nombre del cliente y placa.'];
    }
    if (mb_strlen($plate) > 20) {
        return ['ok' => false, 'error' => 'La placa es demasiado larga.'];
    }

    try {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM orders WHERE plate = :plate LIMIT 1');
        $stmt->execute([':plate' => $plate]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Ya existe una orden activa con esa placa.'];
        }

        $pdo->beginTransaction();

        $customerId = find_or_create_customer($customerName, $customerPhone, $customerEmail);

        $pdo->prepare(
            'INSERT INTO orders (customer_id, plate, vehicle_brand, vehicle_model, vehicle_year, status_id, notes)
             VALUES (:cid, :plate, :brand, :model, :year, 1, :notes)'
        )->execute([
            ':cid'   => $customerId,
            ':plate' => $plate,
            ':brand' => $brand !== '' ? $brand : null,
            ':model' => $model !== '' ? $model : null,
            ':year'  => $year > 0 ? $year : null,
            ':notes' => $notes !== '' ? $notes : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $pdo->commit();

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
        'SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                m.name AS mechanic_name, s.name AS status_name
         FROM orders o
         INNER JOIN customers c ON o.customer_id = c.id
         INNER JOIN order_statuses s ON o.status_id = s.id
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

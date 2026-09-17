<?php
/**
 * Listado y detalle de clientes — Centro Automotriz Arley
 */

require_once __DIR__ . '/db.php';

/**
 * Listado de clientes con conteo de vehículos y órdenes, y búsqueda
 * opcional por nombre/teléfono/placa.
 */
function get_customer_list(string $query = ''): array
{
    $pdo = Database::getConnection();
    $query = trim($query);

    $sql = 'SELECT c.id, c.name, c.phone, c.email,
                   COUNT(DISTINCT v.id) AS vehicle_count,
                   COUNT(DISTINCT o.id) AS order_count,
                   MAX(o.reception_date) AS last_visit
            FROM customers c
            LEFT JOIN vehicles v ON v.customer_id = c.id
            LEFT JOIN orders o ON o.customer_id = c.id';

    $params = [];
    if ($query !== '') {
        $sql .= ' LEFT JOIN vehicles v2 ON v2.customer_id = c.id
                  WHERE c.name LIKE :q1 OR c.phone LIKE :q2 OR v2.plate LIKE :q3';
        $like = '%' . $query . '%';
        $params = [':q1' => $like, ':q2' => $like, ':q3' => mb_strtoupper($query) . '%'];
    }

    $sql .= ' GROUP BY c.id ORDER BY c.name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Perfil completo de un cliente: datos + vehículos + historial de órdenes.
 */
function get_customer_detail(int $customerId): ?array
{
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $customerId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        return null;
    }

    $vehiclesStmt = $pdo->prepare('SELECT * FROM vehicles WHERE customer_id = :id ORDER BY plate');
    $vehiclesStmt->execute([':id' => $customerId]);
    $customer['vehicles'] = $vehiclesStmt->fetchAll();

    $ordersStmt = $pdo->prepare(
        'SELECT o.id, o.reception_date, o.status_id, s.name AS status_name,
                v.plate, v.brand AS vehicle_brand, v.model AS vehicle_model
         FROM orders o
         INNER JOIN order_statuses s ON o.status_id = s.id
         LEFT JOIN vehicles v ON o.vehicle_id = v.id
         WHERE o.customer_id = :id
         ORDER BY o.reception_date DESC'
    );
    $ordersStmt->execute([':id' => $customerId]);
    $customer['orders'] = $ordersStmt->fetchAll();

    return $customer;
}

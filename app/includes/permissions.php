<?php
/**
 * Sistema de permisos por rol — Centro Automotriz Arley
 *
 * Uso:
 *   require_once __DIR__ . '/permissions.php';
 *   if (can('orders.approve_budget')) { ... }
 *   require_permission('users.manage');
 */

define('PERM_ORDERS_VIEW',           'orders.view');            // Ver Kanban / listado de OT
define('PERM_ORDERS_CREATE',         'orders.create');          // Recepción de vehículo
define('PERM_ORDERS_DVI',            'orders.dvi');             // Registrar diagnóstico visual
define('PERM_ORDERS_WORK',           'orders.work');            // Registrar horas/repuestos de reparación
define('PERM_BUDGET_CREATE',         'budget.create');          // Armar presupuesto
define('PERM_BUDGET_APPROVE',        'budget.approve');         // Aprobar presupuesto en nombre del cliente
define('PERM_QC_REVIEW',             'qc.review');              // Revisar calidad antes de entrega
define('PERM_BILLING_MANAGE',        'billing.manage');         // Marcar como facturado
define('PERM_USERS_MANAGE',          'users.manage');           // Gestión de cuentas
define('PERM_INVENTORY_MANAGE',      'inventory.manage');       // Fase 2

// ── Matriz de permisos por role_id ──────────────────────────
//   1 = admin      (Priscilla — acceso total)
//   2 = mechanic   (Manuel, José, Michel — operativo)
//   3 = client     (portal privado — no usa esta matriz, ve solo su OT vía token)
//
// Strings literales (no constantes) para evitar bugs de OPcache.
const ROLE_PERMISSIONS = [
    1 => [ // admin
        'orders.view',
        'orders.create',
        'orders.dvi',
        'orders.work',
        'budget.create',
        'budget.approve',
        'qc.review',
        'billing.manage',
        'users.manage',
        'inventory.manage',
    ],
    2 => [ // mechanic
        'orders.view',
        'orders.create',
        'orders.dvi',
        'orders.work',
        'budget.create',
        'qc.review',
        // billing.manage y users.manage — exclusivo de admin
    ],
    3 => [], // client — acceso vía token de portal, no vía sesión de usuario
];

function can(string $permission): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $u = current_user();
    return in_array($permission, ROLE_PERMISSIONS[(int) ($u['role_id'] ?? 0)] ?? [], true);
}

function require_permission(string $permission): void
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
    if (!can($permission)) {
        header('Location: /dashboard.php?error=forbidden');
        exit;
    }
}

function is_admin(): bool
{
    $u = current_user();
    return (int) ($u['role_id'] ?? 0) === 1;
}

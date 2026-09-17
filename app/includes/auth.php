<?php
/**
 * Authentication functions — Centro Automotriz Arley
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

// ════════════════════════════════════════════════════════════
// Rate-limit helpers
// ════════════════════════════════════════════════════════════

function rate_limit_check(string $context, string $field, string $value, int $maxAttempts, int $windowMin): bool
{
    if ($value === '' || !in_array($field, ['ip_address', 'email'], true)) {
        return true;
    }
    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE attempt_context = :ctx AND $field = :val
                AND attempted_at > DATE_SUB(NOW(), INTERVAL :w MINUTE)"
        );
        $stmt->execute([':ctx' => $context, ':val' => $value, ':w' => $windowMin]);
        return (int) $stmt->fetchColumn() < $maxAttempts;
    } catch (PDOException $e) {
        error_log('[rate_limit_check] ' . $e->getMessage());
        return true; // fail open
    }
}

function rate_limit_record(string $context, string $email = ''): void
{
    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (ip_address, email, attempt_context) VALUES (:ip, :email, :ctx)'
        );
        $stmt->execute([
            ':ip'    => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':email' => mb_strtolower(trim($email)),
            ':ctx'   => $context,
        ]);
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        }
    } catch (PDOException $e) {
        error_log('[rate_limit_record] ' . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════
// Login
// ════════════════════════════════════════════════════════════

function authenticate(string $email, string $password): array
{
    if (empty($email) || empty($password)) {
        return ['ok' => false, 'error' => 'Complete todos los campos.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'El formato del correo no es válido.'];
    }

    $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $email  = mb_strtolower(trim($email));

    try {
        $pdo    = Database::getConnection();
        $window = 15;

        $chkIp = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE attempt_context = 'login' AND ip_address = :ip
                AND attempted_at > DATE_SUB(NOW(), INTERVAL :w MINUTE)"
        );
        $chkIp->execute([':ip' => $ipAddr, ':w' => $window]);
        if ((int) $chkIp->fetchColumn() >= 10) {
            return ['ok' => false, 'error' => 'Demasiados intentos desde esta red. Espere 15 minutos.'];
        }

        $chkEmail = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE attempt_context = 'login' AND email = :email
                AND attempted_at > DATE_SUB(NOW(), INTERVAL :w MINUTE)"
        );
        $chkEmail->execute([':email' => $email, ':w' => $window]);
        if ((int) $chkEmail->fetchColumn() >= 5) {
            return ['ok' => false, 'error' => 'Cuenta temporalmente bloqueada. Espere 15 minutos.'];
        }

        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password_hash, u.password_version,
                    u.is_active, u.role_id, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON u.role_id = r.id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Registrar intento fallido ANTES de revelar si el usuario existe
        // (evita timing attacks que distingan "no existe" de "clave incorrecta")
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $pdo->prepare(
                "INSERT INTO login_attempts (ip_address, email, attempt_context) VALUES (:ip, :email, 'login')"
            )->execute([':ip' => $ipAddr, ':email' => $email]);

            if (random_int(1, 100) === 1) {
                $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
            }
            return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
        }

        if (!(bool) $user['is_active']) {
            return ['ok' => false, 'error' => 'Su cuenta está inactiva. Contacte al administrador.'];
        }

        // Login exitoso — limpiar intentos previos
        $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip OR email = :email')
            ->execute([':ip' => $ipAddr, ':email' => $email]);

        session_regenerate_id(true);
        $_SESSION['user_id']          = (int) $user['id'];
        $_SESSION['user_name']        = $user['name'];
        $_SESSION['user_email']       = $user['email'];
        $_SESSION['role_id']          = (int) $user['role_id'];
        $_SESSION['role_name']        = $user['role_name'];
        $_SESSION['password_version'] = (int) $user['password_version'];
        $_SESSION['logged_in_at']     = time();

        return ['ok' => true, 'role' => $user['role_name']];
    } catch (PDOException $e) {
        error_log('[authenticate] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al iniciar sesión. Intente de nuevo.'];
    }
}

// ════════════════════════════════════════════════════════════
// CSRF
// ════════════════════════════════════════════════════════════

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ════════════════════════════════════════════════════════════
// Password
// ════════════════════════════════════════════════════════════

function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function validate_password_strength(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'La contraseña debe incluir al menos una letra y un número.';
    }
    return null;
}

// ════════════════════════════════════════════════════════════
// Gestión de usuarios (solo admin — sin auto-registro público)
// ════════════════════════════════════════════════════════════

/**
 * Crea un usuario directamente (activo de inmediato).
 * Centro Automotriz Arley tiene 4 usuarios conocidos — el admin los crea, sin
 * flujo de activación por correo.
 */
function create_user(array $data): array
{
    $name  = trim($data['name']  ?? '');
    $email = mb_strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $pass  = $data['password'] ?? '';
    $roleId = (int) ($data['role_id'] ?? 0);

    if ($name === '' || $email === '' || $pass === '' || $roleId <= 0) {
        return ['ok' => false, 'error' => 'Complete todos los campos obligatorios.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'El formato del correo no es válido.'];
    }
    if ($err = validate_password_strength($pass)) {
        return ['ok' => false, 'error' => $err];
    }

    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese correo.'];
        }

        $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, phone, role_id, is_active)
             VALUES (:email, :pass, :name, :phone, :role, 1)'
        )->execute([
            ':email' => $email,
            ':pass'  => hash_password($pass),
            ':name'  => $name,
            ':phone' => $phone !== '' ? $phone : null,
            ':role'  => $roleId,
        ]);

        return ['ok' => true, 'user_id' => (int) $pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log('[create_user] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al crear el usuario.'];
    }
}

/**
 * Cambia la contraseña de un usuario. Incrementa password_version
 * para invalidar cualquier sesión activa (defensa ante sesión robada).
 */
function change_password(int $userId, string $newPassword, string $confirmPassword): array
{
    if ($newPassword === '' || $confirmPassword === '') {
        return ['ok' => false, 'error' => 'Complete ambos campos de contraseña.'];
    }
    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'Las contraseñas no coinciden.'];
    }
    if ($err = validate_password_strength($newPassword)) {
        return ['ok' => false, 'error' => $err];
    }

    try {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'UPDATE users SET password_hash = :pass, password_version = password_version + 1
             WHERE id = :uid'
        )->execute([':pass' => hash_password($newPassword), ':uid' => $userId]);

        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('[change_password] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ocurrió un error al cambiar la contraseña.'];
    }
}

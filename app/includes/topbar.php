<?php
/**
 * Topbar reutilizable. Requiere que $user = current_user() ya esté definido.
 */
?>
<div class="topbar">
    <div class="topbar-brand">
        <img src="/app/assets/images/logo.jpeg" alt="Centro Automotriz Arley" class="topbar-logo">
    </div>
    <nav style="display: flex; gap: 16px; font-size: 13px;">
        <a href="/dashboard.php">Dashboard</a>
        <a href="/customers.php">Clientes</a>
    </nav>
    <div class="topbar-user">
        <a href="/downloads/CentroAutomotrizArley.apk" class="btn btn-secondary" style="padding: 6px 12px;" download>📱 App Android</a>
        <span><?= htmlspecialchars($user['name']) ?></span>
        <span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
        <a href="/logout.php" class="btn btn-secondary" style="padding: 6px 12px;">Salir</a>
    </div>
</div>

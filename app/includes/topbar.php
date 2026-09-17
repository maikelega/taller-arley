<?php
/**
 * Topbar reutilizable. Requiere que $user = current_user() ya esté definido.
 */
?>
<div class="topbar">
    <div class="topbar-brand">🔧 Taller Arley</div>
    <div class="topbar-user">
        <span><?= htmlspecialchars($user['name']) ?></span>
        <span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
        <a href="/logout.php" class="btn btn-secondary" style="padding: 6px 12px;">Salir</a>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/session.php';
destroy_session();
header('Location: /login.php');
exit;

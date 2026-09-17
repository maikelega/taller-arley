<?php
require_once __DIR__ . '/app/includes/session.php';
destroy_session();
header('Location: /login.php');
exit;

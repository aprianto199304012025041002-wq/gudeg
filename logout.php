<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], $_SESSION['name'], $_SESSION['email'], 'Logout');
}

session_destroy();
header("Location: /login.php");
exit;

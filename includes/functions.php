<?php
require_once __DIR__ . '/../config/db.php';

function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function require_login() {
    session_start_safe();
    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /login.php?redirect=$redirect");
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: /dashboard.php");
        exit;
    }
}

function get_current_user_data() {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name'],
        'email' => $_SESSION['email'],
        'role'  => $_SESSION['role'],
    ];
}

function log_activity($user_id, $name, $email, $activity) {
    $db = getDB();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = explode(',', $ip)[0];
    $stmt = $db->prepare("INSERT INTO user_logs (user_id, user_name, user_email, activity, ip_address) VALUES (?,?,?,?,?)");
    $stmt->execute([$user_id, $name, $email, $activity, $ip]);
}

function log_admin_action($admin_id, $admin_name, $action, $target = '-') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, admin_name, action, target_user, created_at) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$admin_id, $admin_name, $action, $target]);
}

function get_ip() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return explode(',', $ip)[0];
}

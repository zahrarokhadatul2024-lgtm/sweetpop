<?php
require_once __DIR__ . '/config.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_name() {
    return $_SESSION['user_name'] ?? 'Guest';
}

function require_login($redirect = 'login.php') {
    if (!is_logged_in()) {
        set_flash('danger', 'Silakan login terlebih dahulu untuk melanjutkan.');
        header("Location: " . $redirect);
        exit;
    }
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_admin($redirect = 'login.php') {
    if (!is_admin_logged_in()) {
        header("Location: " . $redirect);
        exit;
    }
}
?>

<?php
/**
 * Aero's Store - Auth Middleware
 */

require_once __DIR__ . '/config.php';

function requireLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: index.php?page=login');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function loginAdmin($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    return false;
}

function logoutAdmin() {
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

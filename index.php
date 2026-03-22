<?php
/**
 * Aero's Store - Main Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$page = $_GET['page'] ?? 'dashboard';

// Pages that don't need auth
$publicPages = ['login', 'logout'];

if (!in_array($page, $publicPages)) {
    requireLogin();
}

// Valid pages
$validPages = [
    'login', 'logout', 'dashboard',
    'orders', 'order_form', 'order_detail',
    'customers', 'customer_form', 'customer_detail',
    'workers', 'worker_form', 'worker_detail',
    'history', 'log_activity'
];

if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Login/Logout don't use layout
if ($page === 'login' || $page === 'logout') {
    require_once __DIR__ . '/pages/' . $page . '.php';
    exit;
}

// Render with layout
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/pages/' . $page . '.php';
require_once __DIR__ . '/includes/footer.php';

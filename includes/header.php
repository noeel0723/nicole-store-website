<?php
$currentPage = $_GET['page'] ?? 'dashboard';

// Count unassigned orders for badge
$db = getDB();
$unassignedCount = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'unassigned'")->fetchColumn();
$activeOrdersCount = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('in_progress','pending_verification')")->fetchColumn();

// Page titles
$pageTitles = [
    'dashboard' => ['Dashboard', 'Ringkasan operasional harian'],
    'orders' => ['Pesanan', 'Kelola semua pesanan joki'],
    'order_form' => [isset($_GET['id']) ? 'Edit Pesanan' : 'Tambah Pesanan', 'Isi detail pesanan baru'],
    'order_detail' => ['Detail Pesanan', 'Informasi lengkap pesanan'],
    'customers' => ['Pelanggan', 'Database pelanggan Anda'],
    'customer_form' => [isset($_GET['id']) ? 'Edit Pelanggan' : 'Tambah Pelanggan', 'Informasi pelanggan'],
    'customer_detail' => ['Profil Pelanggan', 'Riwayat dan detail pelanggan'],
    'workers' => ['Worker', 'Manajemen tim joki'],
    'worker_form' => [isset($_GET['id']) ? 'Edit Worker' : 'Tambah Worker', 'Data worker'],
    'worker_detail' => ['Profil Worker', 'Performa dan komisi worker'],
];

$pageTitle = $pageTitles[$currentPage][0] ?? 'Dashboard';
$pageSubtitle = $pageTitles[$currentPage][1] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Aero's Store - Sistem Manajemen Internal untuk Joki MLBB">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">A</div>
            <div>
                <h1><?= APP_NAME ?></h1>
                <small>Management System</small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Menu Utama</div>
                <a href="index.php?page=dashboard" class="nav-link <?= isCurrentPage('dashboard') ?>">
                    <i class='bx bxs-dashboard'></i>
                    <span>Dashboard</span>
                </a>
                <a href="index.php?page=orders" class="nav-link <?= isCurrentPage('orders') || isCurrentPage('order_form') || isCurrentPage('order_detail') ? 'active' : '' ?>">
                    <i class='bx bxs-receipt'></i>
                    <span>Pesanan</span>
                    <?php if ($unassignedCount > 0): ?>
                        <span class="nav-badge"><?= $unassignedCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Database</div>
                <a href="index.php?page=customers" class="nav-link <?= isCurrentPage('customers') || isCurrentPage('customer_form') || isCurrentPage('customer_detail') ? 'active' : '' ?>">
                    <i class='bx bxs-user-detail'></i>
                    <span>Pelanggan</span>
                </a>
                <a href="index.php?page=workers" class="nav-link <?= isCurrentPage('workers') || isCurrentPage('worker_form') || isCurrentPage('worker_detail') ? 'active' : '' ?>">
                    <i class='bx bxs-group'></i>
                    <span>Worker</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                <div class="user-info">
                    <strong><?= sanitize($_SESSION['admin_name'] ?? 'Admin') ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Area -->
    <main class="main-content">

        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class='bx bx-menu'></i>
                </button>
                <div>
                    <h2><?= $pageTitle ?></h2>
                    <p><?= $pageSubtitle ?></p>
                </div>
            </div>
            <div class="topbar-right">
                <a href="index.php?page=logout" class="btn-logout">
                    <i class='bx bx-log-out'></i>
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <?php
            // Flash messages
            if ($success = flash('success')):
            ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i> <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-danger">
                    <i class='bx bx-error-circle'></i> <?= $error ?>
                </div>
            <?php endif; ?>

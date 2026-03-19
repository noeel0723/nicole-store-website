<?php
/**
 * Aero's Store - Helper Functions
 */

function redirect($url) {
    header("Location: $url");
    exit;
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function statusLabel($status) {
    $labels = [
        'unassigned' => '<span class="badge badge-warning">Unassigned</span>',
        'in_progress' => '<span class="badge badge-info">In Progress</span>',
        'pending_verification' => '<span class="badge badge-purple">Pending Verification</span>',
        'completed' => '<span class="badge badge-success">Completed</span>',
    ];
    return $labels[$status] ?? '<span class="badge">' . $status . '</span>';
}

function paymentLabel($status) {
    $labels = [
        'unpaid' => '<span class="badge badge-danger">Belum Bayar</span>',
        'dp' => '<span class="badge badge-warning">DP</span>',
        'paid' => '<span class="badge badge-success">Lunas</span>',
    ];
    return $labels[$status] ?? '<span class="badge">' . $status . '</span>';
}

function workerStatusBadge($activeOrders) {
    if ($activeOrders == 0) return '<span class="badge badge-success">Idle</span>';
    if ($activeOrders <= 2) return '<span class="badge badge-info">Active</span>';
    return '<span class="badge badge-danger">Overloaded</span>';
}

function isCurrentPage($page) {
    $currentPage = $_GET['page'] ?? 'dashboard';
    return $currentPage === $page ? 'active' : '';
}

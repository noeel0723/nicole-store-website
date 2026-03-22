<?php
/**
 * Log Activity - Ticket Updates Timeline (My Activity Style)
 */
$db = getDB();

// Period filter
$period = $_GET['period'] ?? 'all';
$search = $_GET['search'] ?? '';

$where = '1=1';
$params = [];

if ($period === 'today') {
    $where .= " AND DATE(ol.created_at) = CURDATE()";
} elseif ($period === 'week') {
    $where .= " AND ol.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($period === 'month') {
    $where .= " AND ol.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

if ($search) {
    $where .= " AND (ol.message LIKE ? OR c.name LIKE ? OR o.id = ?)";
    $params = ["%$search%", "%$search%", (int)$search];
}

$stmt = $db->prepare("
    SELECT ol.*, o.customer_id, o.rank_from, o.rank_to, o.status as order_status,
           c.name as customer_name, w.name as worker_name
    FROM order_logs ol
    JOIN orders o ON ol.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN workers w ON o.worker_id = w.id
    WHERE $where
    ORDER BY ol.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Stats
$totalLogs = count($logs);
$todayLogs = 0;
$todayDate = date('Y-m-d');
foreach ($logs as $l) {
    if (date('Y-m-d', strtotime($l['created_at'])) === $todayDate) $todayLogs++;
}

// Group by date
$grouped = [];
foreach ($logs as $log) {
    $dateKey = date('Y-m-d', strtotime($log['created_at']));
    $grouped[$dateKey][] = $log;
}
?>

<div class="page-header">
    <div>
        <h2>Log Activity</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-top:4px;">Riwayat aktivitas dan update tiket pesanan</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="customer-stats-grid" style="margin-bottom:20px;">
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Total Log</span>
            <span class="cs-value"><?= $totalLogs ?></span>
            <span class="cs-meta"><i class='bx bx-list-check'></i> Semua aktivitas tercatat</span>
        </div>
        <div class="cs-icon" style="background:rgba(42,56,76,0.1); color:var(--primary);">
            <i class='bx bx-file'></i>
        </div>
    </div>
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Aktivitas Hari Ini</span>
            <span class="cs-value"><?= $todayLogs ?></span>
            <span class="cs-meta"><i class='bx bx-time'></i> <?= date('d M Y') ?></span>
        </div>
        <div class="cs-icon" style="background:rgba(42,56,76,0.1); color:var(--info);">
            <i class='bx bx-pulse'></i>
        </div>
    </div>
</div>

<!-- Toolbar -->
<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
    <div class="search-box" style="flex:1; min-width:200px;">
        <i class='bx bx-search'></i>
        <form action="index.php" method="GET" style="display:inline; width:100%;">
            <input type="hidden" name="page" value="log_activity">
            <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
            <input type="text" name="search" placeholder="Cari log, ID pesanan, atau pelanggan..." value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>
    <div class="period-filter" style="display:flex; gap:6px; flex-wrap:wrap;">
        <a href="index.php?page=log_activity&period=today" class="btn <?= $period === 'today' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Hari Ini</a>
        <a href="index.php?page=log_activity&period=week" class="btn <?= $period === 'week' ? 'btn-primary' : 'btn-outline' ?> btn-sm">7 Hari</a>
        <a href="index.php?page=log_activity&period=month" class="btn <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?> btn-sm">30 Hari</a>
        <a href="index.php?page=log_activity&period=all" class="btn <?= $period === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Semua</a>
    </div>
</div>

<!-- Timeline grouped by date -->
<?php if (empty($grouped)): ?>
    <div class="card" style="padding:40px; text-align:center; color:var(--text-muted);">
        <i class='bx bx-history' style="font-size:48px; margin-bottom:12px; opacity:0.5;"></i>
        <p>Belum ada aktivitas yang dicatat.</p>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $date => $entries): ?>
        <div style="margin-bottom:24px;">
            <div style="font-size:13px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px; padding-left:4px;">
                <i class='bx bx-calendar' style="margin-right:4px;"></i>
                <?= date('d M Y', strtotime($date)) ?>
                <?php if ($date === $todayDate): ?>
                    <span style="color:var(--primary); font-weight:600;">&mdash; Hari Ini</span>
                <?php endif; ?>
            </div>

            <?php foreach ($entries as $log): ?>
            <div class="card" style="padding:16px 20px; margin-bottom:8px; border-left:3px solid var(--primary); transition: transform 0.15s;">
                <div style="display:flex; align-items:flex-start; gap:14px; flex-wrap:wrap;">
                    <!-- Time -->
                    <div style="min-width:50px; text-align:center;">
                        <div style="font-size:18px; font-weight:700; color:var(--primary);"><?= date('H:i', strtotime($log['created_at'])) ?></div>
                        <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase;"><?= date('D', strtotime($log['created_at'])) ?></div>
                    </div>
                    <!-- Content -->
                    <div style="flex:1; min-width:200px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                            <a href="index.php?page=order_detail&id=<?= $log['order_id'] ?>" style="font-weight:700; color:var(--primary); font-size:14px;">
                                Pesanan #<?= $log['order_id'] ?>
                            </a>
                            <span style="font-size:12px; color:var(--text-muted);">&bull;</span>
                            <a href="index.php?page=customer_detail&id=<?= $log['customer_id'] ?>" style="color:var(--text-secondary); font-size:13px;">
                                <?= sanitize($log['customer_name']) ?>
                            </a>
                            <?php if ($log['worker_name']): ?>
                                <span style="font-size:12px; color:var(--text-muted);">&bull;</span>
                                <span style="font-size:12px; color:var(--accent);">
                                    <i class='bx bx-user' style="font-size:11px;"></i> <?= sanitize($log['worker_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:14px; color:var(--text-primary);">
                            <?= sanitize($log['message']) ?>
                        </div>
                        <?php if ($log['rank_from'] && $log['rank_to']): ?>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
                            <i class='bx bx-arrow-from-left' style="font-size:11px;"></i>
                            <?= sanitize($log['rank_from']) ?> → <?= sanitize($log['rank_to']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Status -->
                    <div style="text-align:right;">
                        <?= statusLabel($log['order_status']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

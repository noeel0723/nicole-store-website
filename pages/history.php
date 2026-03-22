<?php
/**
 * History - Revenue & Profit with Date Filter
 */
$db = getDB();

// Period filter
$period = $_GET['period'] ?? 'month';
$customDate = $_GET['date'] ?? '';

// Build date filter
if ($period === 'custom' && $customDate) {
    $dateFilter = "DATE(o.completed_at) = " . $db->quote($customDate);
    $periodLabel = date('d M Y', strtotime($customDate));
} else {
    switch ($period) {
        case 'today':
            $dateFilter = "DATE(o.completed_at) = CURDATE()";
            $periodLabel = "Hari Ini";
            break;
        case 'week':
            $dateFilter = "o.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $periodLabel = "7 Hari Terakhir";
            break;
        case 'month':
            $dateFilter = "MONTH(o.completed_at) = MONTH(CURDATE()) AND YEAR(o.completed_at) = YEAR(CURDATE())";
            $periodLabel = "Bulan Ini";
            break;
        case 'all':
            $dateFilter = "1=1";
            $periodLabel = "Semua Waktu";
            break;
        default:
            $dateFilter = "MONTH(o.completed_at) = MONTH(CURDATE()) AND YEAR(o.completed_at) = YEAR(CURDATE())";
            $periodLabel = "Bulan Ini";
            $period = 'month';
    }
}

// Sort
$sort = $_GET['sort'] ?? 'newest';
$orderBy = match($sort) {
    'oldest' => 'o.completed_at ASC',
    'price_high' => 'o.price DESC',
    'price_low' => 'o.price ASC',
    'profit_high' => '(o.price - o.worker_commission) DESC',
    'profit_low' => '(o.price - o.worker_commission) ASC',
    default => 'o.completed_at DESC',
};

// Fetch completed orders
$stmt = $db->query("
    SELECT o.*, c.name as customer_name, w.name as worker_name,
           (o.price - o.worker_commission) as net_profit
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN workers w ON o.worker_id = w.id
    WHERE o.status = 'completed' AND {$dateFilter}
    ORDER BY {$orderBy}
");
$orders = $stmt->fetchAll();

// Summary calculations
$totalGross = 0;
$totalCommission = 0;
$totalNet = 0;
foreach ($orders as $o) {
    $totalGross += $o['price'];
    $totalCommission += $o['worker_commission'];
    $totalNet += $o['net_profit'];
}
$orderCount = count($orders);
?>

<div class="page-header">
    <div>
        <!-- Left: Revenue Stats -->
        <h2>Log Transaction</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-top:4px;"><?= $periodLabel ?> &mdash; <?= $orderCount ?> pesanan selesai</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="history-summary-grid">
    <div class="history-summary-card">
        <div class="hs-icon" style="background: rgba(192, 160, 98, 0.14); color: var(--warning);">
            <i class='bx bx-coin-stack'></i>
        </div>
        <div class="hs-info">
            <span class="hs-label">Profit Kotor</span>
            <span class="hs-value"><?= formatRupiah($totalGross) ?></span>
            <span class="hs-meta"><?= $orderCount ?> pesanan</span>
        </div>
    </div>
    <div class="history-summary-card">
        <div class="hs-icon" style="background: rgba(176, 90, 74, 0.14); color: var(--danger);">
            <i class='bx bx-transfer-alt'></i>
        </div>
        <div class="hs-info">
            <span class="hs-label">Komisi Worker</span>
            <span class="hs-value" style="color: var(--danger);">-<?= formatRupiah($totalCommission) ?></span>
            <span class="hs-meta">Pengeluaran komisi</span>
        </div>
    </div>
    <div class="history-summary-card highlight">
        <div class="hs-icon" style="background: rgba(91, 140, 90, 0.14); color: var(--success);">
            <i class='bx bx-trending-up'></i>
        </div>
        <div class="hs-info">
            <span class="hs-label">Profit Bersih</span>
            <span class="hs-value" style="color: var(--success);"><?= formatRupiah($totalNet) ?></span>
            <span class="hs-meta">Setelah komisi worker</span>
        </div>
    </div>
</div>

<!-- Filter / Sort Bar -->
<div class="history-toolbar">
    <div class="filter-tabs">
        <a href="index.php?page=history&period=today&sort=<?= $sort ?>"
           class="filter-tab <?= $period === 'today' ? 'active' : '' ?>">Hari Ini</a>
        <a href="index.php?page=history&period=week&sort=<?= $sort ?>"
           class="filter-tab <?= $period === 'week' ? 'active' : '' ?>">7 Hari</a>
        <a href="index.php?page=history&period=month&sort=<?= $sort ?>"
           class="filter-tab <?= $period === 'month' ? 'active' : '' ?>">Bulan Ini</a>
        <a href="index.php?page=history&period=all&sort=<?= $sort ?>"
           class="filter-tab <?= $period === 'all' ? 'active' : '' ?>">Semua</a>
    </div>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:6px;">
            <label style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap;">Tanggal:</label>
            <input type="date" class="form-control" style="max-width:170px; font-size:13px; padding:8px 10px;"
                   value="<?= $customDate ?>"
                   onchange="if(this.value) location.href='index.php?page=history&period=custom&date='+this.value+'&sort=<?= $sort ?>'">
        </div>
        <div class="history-sort">
            <select class="filter-select" onchange="location.href='index.php?page=history&period=<?= $period ?><?= $customDate ? '&date=' . $customDate : '' ?>&sort='+this.value">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Terlama</option>
                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Harga Tertinggi</option>
                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Harga Terendah</option>
                <option value="profit_high" <?= $sort === 'profit_high' ? 'selected' : '' ?>>Profit Tertinggi</option>
                <option value="profit_low" <?= $sort === 'profit_low' ? 'selected' : '' ?>>Profit Terendah</option>
            </select>
        </div>
    </div>
</div>

<!-- Orders List Panel -->
<?php if (empty($orders)): ?>
<div class="card">
    <div class="empty-state">
        <i class='bx bx-receipt'></i>
        <h3>Belum Ada Data</h3>
        <p>Tidak ada pesanan selesai untuk periode <?= strtolower($periodLabel) ?>.</p>
    </div>
</div>
<?php else: ?>
<div class="history-panel">
    <div class="hp-header">
        <span class="hp-col hp-col-date">Tanggal</span>
        <span class="hp-col hp-col-customer">Pelanggan</span>
        <span class="hp-col hp-col-rank">Rank</span>
        <span class="hp-col hp-col-worker">Worker</span>
        <span class="hp-col hp-col-gross">Harga</span>
        <span class="hp-col hp-col-comm">Komisi</span>
        <span class="hp-col hp-col-net">Profit</span>
    </div>

    <?php foreach ($orders as $o): ?>
    <a href="index.php?page=order_detail&id=<?= $o['id'] ?>" class="hp-row">
        <span class="hp-col hp-col-date">
            <?= date('d M Y', strtotime($o['completed_at'])) ?>
            <small class="hp-time"><?= date('H:i', strtotime($o['completed_at'])) ?></small>
        </span>
        <span class="hp-col hp-col-customer">
            <strong><?= sanitize($o['customer_name']) ?></strong>
            <small>#<?= $o['id'] ?></small>
        </span>
        <span class="hp-col hp-col-rank">
            <?= sanitize($o['rank_from']) ?> → <?= sanitize($o['rank_to']) ?>
        </span>
        <span class="hp-col hp-col-worker">
            <?= $o['worker_name'] ? sanitize($o['worker_name']) : '<span style="color:var(--text-muted)">Admin</span>' ?>
        </span>
        <span class="hp-col hp-col-gross"><?= formatRupiah($o['price']) ?></span>
        <span class="hp-col hp-col-comm" style="color:var(--danger);">-<?= formatRupiah($o['worker_commission']) ?></span>
        <span class="hp-col hp-col-net" style="color:var(--success); font-weight:700;">
            <?= formatRupiah($o['net_profit']) ?>
        </span>
    </a>
    <?php endforeach; ?>

    <div class="hp-footer">
        <span class="hp-col hp-col-date"><strong>Total</strong></span>
        <span class="hp-col hp-col-customer"></span>
        <span class="hp-col hp-col-rank"></span>
        <span class="hp-col hp-col-worker"></span>
        <span class="hp-col hp-col-gross"><strong><?= formatRupiah($totalGross) ?></strong></span>
        <span class="hp-col hp-col-comm" style="color:var(--danger);"><strong>-<?= formatRupiah($totalCommission) ?></strong></span>
        <span class="hp-col hp-col-net" style="color:var(--success);"><strong><?= formatRupiah($totalNet) ?></strong></span>
    </div>
</div>
<?php endif; ?>

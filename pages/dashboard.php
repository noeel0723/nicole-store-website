<?php
/**
 * Dashboard - Pusat Komando
 */
$db = getDB();

// ======= Metrik Utama =======
// Total pesanan aktif (non-completed)
$activeOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status != 'completed'")->fetchColumn();

// Pesanan selesai bulan ini
$completedThisMonth = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed' AND MONTH(completed_at) = MONTH(CURRENT_DATE()) AND YEAR(completed_at) = YEAR(CURRENT_DATE())")->fetchColumn();

// Total pendapatan bulan ini
$revenueThisMonth = $db->query("SELECT COALESCE(SUM(price), 0) FROM orders WHERE status = 'completed' AND MONTH(completed_at) = MONTH(CURRENT_DATE()) AND YEAR(completed_at) = YEAR(CURRENT_DATE())")->fetchColumn();

// Total komisi worker bulan ini
$commissionThisMonth = $db->query("SELECT COALESCE(SUM(worker_commission), 0) FROM orders WHERE status = 'completed' AND MONTH(completed_at) = MONTH(CURRENT_DATE()) AND YEAR(completed_at) = YEAR(CURRENT_DATE())")->fetchColumn();

$profitThisMonth = $revenueThisMonth - $commissionThisMonth;

// ======= Pesanan Kritis =======
$criticalOrders = $db->query("
    SELECT o.*, c.name as customer_name, w.name as worker_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN workers w ON o.worker_id = w.id
    WHERE o.status != 'completed'
    ORDER BY
        CASE WHEN o.status = 'unassigned' THEN 0 ELSE 1 END,
        CASE WHEN o.deadline IS NOT NULL THEN o.deadline ELSE '9999-12-31' END ASC
    LIMIT 10
")->fetchAll();

// ======= Worker Radar =======
$workers = $db->query("
    SELECT w.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.worker_id = w.id AND o.status IN ('in_progress','pending_verification')) as active_orders
    FROM workers w
    WHERE w.is_active = 1
    ORDER BY active_orders ASC
")->fetchAll();

?>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon"><i class='bx bxs-receipt'></i></div>
        <div class="stat-value"><?= $activeOrders ?></div>
        <div class="stat-label">Pesanan Aktif</div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class='bx bxs-check-circle'></i></div>
        <div class="stat-value"><?= $completedThisMonth ?></div>
        <div class="stat-label">Selesai Bulan Ini</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon"><i class='bx bxs-wallet'></i></div>
        <div class="stat-value"><?= formatRupiah($revenueThisMonth) ?></div>
        <div class="stat-label">Pendapatan Bulan Ini</div>
    </div>
    <div class="stat-card info">
        <div class="stat-icon"><i class='bx bxs-bar-chart-alt-2'></i></div>
        <div class="stat-value"><?= formatRupiah($profitThisMonth) ?></div>
        <div class="stat-label">Profit Bersih</div>
    </div>
</div>

<!-- Main Grid -->
<div class="grid-3">
    <!-- Left: Critical Orders -->
    <div>
        <!-- Critical Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bx-error-circle' style="color: var(--danger);"></i> Pesanan Kritis</h3>
                <a href="index.php?page=orders" class="btn btn-sm btn-outline">Lihat Semua</a>
            </div>
            <?php if (empty($criticalOrders)): ?>
                <div class="empty-state">
                    <i class='bx bx-check-shield'></i>
                    <h3>Semua Aman!</h3>
                    <p>Tidak ada pesanan kritis saat ini.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pelanggan</th>
                                <th>Rank</th>
                                <th>Worker</th>
                                <th>Status</th>
                                <th>Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($criticalOrders as $order): ?>
                            <tr onclick="location.href='index.php?page=order_detail&id=<?= $order['id'] ?>'" style="cursor:pointer">
                                <td style="color:var(--text-muted); font-weight:600;">#<?= $order['id'] ?></td>
                                <td style="color:var(--text-primary); font-weight:600;"><?= sanitize($order['customer_name']) ?></td>
                                <td>
                                    <span style="font-size:12px;"><?= sanitize($order['rank_from']) ?> → <?= sanitize($order['rank_to']) ?></span>
                                </td>
                                <td><?= $order['worker_name'] ? sanitize($order['worker_name']) : '<span style="color:var(--danger)">Belum ada</span>' ?></td>
                                <td><?= statusLabel($order['status']) ?></td>
                                <td>
                                    <?php if ($order['deadline']): ?>
                                        <?php
                                        $deadline = new DateTime($order['deadline']);
                                        $today = new DateTime();
                                        $diff = $today->diff($deadline);
                                        $isUrgent = $deadline <= $today || $diff->days <= 2;
                                        ?>
                                        <span class="<?= $isUrgent ? 'badge badge-danger' : '' ?>" style="font-size:12px;">
                                            <?= date('d M Y', strtotime($order['deadline'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted)">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Worker Radar -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bx-radar' style="color: var(--accent);"></i> Radar Worker</h3>
                <a href="index.php?page=workers" class="btn btn-sm btn-outline">Kelola</a>
            </div>
            <?php if (empty($workers)): ?>
                <div class="empty-state">
                    <i class='bx bx-user-plus'></i>
                    <h3>Belum Ada Worker</h3>
                    <p>Tambahkan worker terlebih dahulu.</p>
                </div>
            <?php else: ?>
                <div class="worker-radar">
                    <?php foreach ($workers as $w): ?>
                    <div class="worker-radar-item">
                        <div class="wr-avatar"><?= strtoupper(substr($w['name'], 0, 1)) ?></div>
                        <div class="wr-info">
                            <strong><?= sanitize($w['name']) ?></strong>
                            <span><?= $w['active_orders'] ?> pesanan aktif · <?= sanitize($w['rank_info'] ?? '-') ?></span>
                        </div>
                        <?= workerStatusBadge($w['active_orders']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

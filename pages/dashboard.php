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
    LIMIT 100
")->fetchAll();

// ======= Worker Radar =======
$workers = $db->query("
    SELECT w.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.worker_id = w.id AND o.status IN ('in_progress','pending_verification')) as active_orders
    FROM workers w
    WHERE w.is_active = 1
    ORDER BY active_orders ASC
")->fetchAll();

// ======= Pesanan Bulan Ini (total) =======
$totalOrdersThisMonth = $db->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn();
?>

<!-- Dashboard Shell -->
<div class="dashboard-shell">

    <!-- Top Grid: Main Account + Focus -->
    <div class="dashboard-top-grid">
        <!-- Main Account Card -->
        <div class="card dashboard-balance-card">
            <div class="db-meta">Laporan Bulanan</div>
            <div class="db-title"><?= APP_NAME ?> — <?= date('F Y') ?></div>
            <div class="db-balance-row">
                <div>
                    <div class="db-balance-label">Pendapatan Bulan Ini</div>
                    <div class="db-balance-value"><?= formatRupiah($revenueThisMonth) ?></div>
                </div>
                <div>
                    <div class="db-balance-label">Profit Bersih</div>
                    <div class="db-balance-profit"><?= formatRupiah($profitThisMonth) ?></div>
                </div>
            </div>
            <div class="db-actions">
                <a href="index.php?page=orders" class="btn btn-primary btn-sm">
                    <i class='bx bx-receipt'></i> Lihat Pesanan
                </a>
                <a href="index.php?page=workers" class="btn btn-outline btn-sm">
                    <i class='bx bx-group'></i> Kelola Worker
                </a>
            </div>
        </div>

        <!-- Focus Card -->
        <div class="card dashboard-focus-card">
            <h3><i class='bx bx-target-lock'></i> Fokus Hari Ini</h3>
            <p>Pantau metrik penting untuk memastikan operasional berjalan lancar.</p>
            <div class="focus-list">
                <div class="focus-item">
                    <span>Pesanan Aktif</span>
                    <strong><?= $activeOrders ?></strong>
                </div>
                <div class="focus-item">
                    <span>Selesai Bulan Ini</span>
                    <strong><?= $completedThisMonth ?></strong>
                </div>
                <div class="focus-item">
                    <span>Total Pesanan Bulan Ini</span>
                    <strong><?= $totalOrdersThisMonth ?></strong>
                </div>
            </div>
            <a href="index.php?page=order_form" class="btn btn-outline btn-sm" style="border-color:rgba(255,255,255,0.3); color:#F0F0F0;">
                <i class='bx bx-plus'></i> Buat Pesanan Baru
            </a>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="dashboard-kpi-grid">
        <div class="kpi-box">
            <small>Pesanan Aktif</small>
            <strong><?= $activeOrders ?></strong>
        </div>
        <div class="kpi-box">
            <small>Selesai Bulan Ini</small>
            <strong><?= $completedThisMonth ?></strong>
        </div>
        <div class="kpi-box">
            <small>Pendapatan</small>
            <strong><?= formatRupiah($revenueThisMonth) ?></strong>
        </div>
        <div class="kpi-box">
            <small>Profit Bersih</small>
            <strong><?= formatRupiah($profitThisMonth) ?></strong>
        </div>
    </div>

    <!-- Main Grid: Critical Orders + Worker Radar -->
    <div class="dashboard-main-grid">
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
                        <tbody id="criticalOrdersBody">
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
                <div class="table-pagination" id="criticalOrdersPagination"></div>
            <?php endif; ?>
        </div>

        <!-- Worker Radar -->
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('criticalOrdersBody');
    const pagination = document.getElementById('criticalOrdersPagination');
    if (!tbody || !pagination) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const perPage = 10;
    const totalPages = Math.ceil(rows.length / perPage);
    let currentPage = 1;

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    function renderRows() {
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        rows.forEach((row, index) => {
            row.style.display = index >= start && index < end ? '' : 'none';
        });
    }

    function createButton(label, page, isActive, disabled) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'page-btn' + (isActive ? ' active' : '');
        btn.textContent = label;
        btn.disabled = disabled;
        if (!disabled) {
            btn.addEventListener('click', function() {
                currentPage = page;
                renderRows();
                renderPagination();
            });
        }
        return btn;
    }

    function renderPagination() {
        pagination.innerHTML = '';
        pagination.appendChild(createButton('Prev', currentPage - 1, false, currentPage === 1));

        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        for (let page = startPage; page <= endPage; page++) {
            pagination.appendChild(createButton(String(page), page, page === currentPage, false));
        }

        pagination.appendChild(createButton('Next', currentPage + 1, false, currentPage === totalPages));
    }

    renderRows();
    renderPagination();
});
</script>

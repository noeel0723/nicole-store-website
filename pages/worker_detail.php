<?php
/**
 * Worker Detail / Profile + Commission Ledger
 */
$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php?page=workers');
}

$workerId = (int)$_GET['id'];

$stmt = $db->prepare("
    SELECT w.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.worker_id = w.id AND o.status IN ('in_progress','pending_verification')) as active_orders,
        (SELECT COUNT(*) FROM orders o WHERE o.worker_id = w.id AND o.status = 'completed') as completed_orders
    FROM workers w WHERE w.id = ?
");
$stmt->execute([$workerId]);
$worker = $stmt->fetch();

if (!$worker) {
    flash('error', 'Worker tidak ditemukan.');
    redirect('index.php?page=workers');
}

// Commission data
$totalEarned = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM worker_commissions WHERE worker_id = ? AND type = 'earned'");
$totalEarned->execute([$workerId]);
$totalEarned = $totalEarned->fetchColumn();

$totalPaid = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM worker_commissions WHERE worker_id = ? AND type = 'paid'");
$totalPaid->execute([$workerId]);
$totalPaid = $totalPaid->fetchColumn();

$pendingCommission = $totalEarned - $totalPaid;

// Komisi periodik untuk evaluasi performa
$periodCommissionStmt = $db->prepare("\n    SELECT\n        COALESCE(SUM(CASE WHEN DATE(created_at) = CURRENT_DATE() THEN amount ELSE 0 END), 0) as daily_commission,\n        COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) THEN amount ELSE 0 END), 0) as weekly_commission,\n        COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END), 0) as monthly_commission\n    FROM worker_commissions\n    WHERE worker_id = ? AND type = 'earned'\n");
$periodCommissionStmt->execute([$workerId]);
$periodCommission = $periodCommissionStmt->fetch();

// Engagement periodik berbasis penyelesaian order
$periodOrdersStmt = $db->prepare("\n    SELECT\n        COALESCE(SUM(CASE WHEN DATE(completed_at) = CURRENT_DATE() THEN 1 ELSE 0 END), 0) as daily_completed,\n        COALESCE(SUM(CASE WHEN completed_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) as weekly_completed,\n        COALESCE(SUM(CASE WHEN MONTH(completed_at) = MONTH(CURRENT_DATE()) AND YEAR(completed_at) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END), 0) as monthly_completed\n    FROM orders\n    WHERE worker_id = ? AND status = 'completed'\n");
$periodOrdersStmt->execute([$workerId]);
$periodOrders = $periodOrdersStmt->fetch();

// Commission ledger entries
$ledger = $db->prepare("
    SELECT wc.*, o.id as order_number
    FROM worker_commissions wc
    LEFT JOIN orders o ON wc.order_id = o.id
    WHERE wc.worker_id = ?
    ORDER BY wc.created_at DESC
");
$ledger->execute([$workerId]);
$ledgerEntries = $ledger->fetchAll();

// Order history
$orders = $db->prepare("
    SELECT o.*, c.name as customer_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.worker_id = ?
    ORDER BY o.created_at DESC
");
$orders->execute([$workerId]);
$orderHistory = $orders->fetchAll();

// Handle pay commission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_commission'])) {
    $payAmount = (float)str_replace(['.', ','], ['', '.'], $_POST['pay_amount']);
    $payNotes = trim($_POST['pay_notes'] ?? '');

    if ($payAmount > 0 && $payAmount <= $pendingCommission) {
        $stmt = $db->prepare("INSERT INTO worker_commissions (worker_id, amount, type, notes) VALUES (?, ?, 'paid', ?)");
        $stmt->execute([$workerId, $payAmount, $payNotes ?: 'Pembayaran komisi']);
        flash('success', 'Komisi sebesar ' . formatRupiah($payAmount) . ' berhasil dibayarkan.');
    } else {
        flash('error', 'Jumlah pembayaran tidak valid.');
    }
    redirect('index.php?page=worker_detail&id=' . $workerId);
}
?>

<div class="page-header">
    <div>
        <h2>Profil Worker</h2>
        <p>Performa, riwayat pesanan, dan buku besar komisi</p>
    </div>
    <div class="btn-group">
        <a href="index.php?page=worker_form&id=<?= $worker['id'] ?>" class="btn btn-outline"><i class='bx bx-edit'></i> Edit</a>
        <a href="index.php?page=workers" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Kembali</a>
    </div>
</div>

<div class="detail-grid">
    <div>
        <!-- Commission Ledger -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class='bx bx-book' style="color:var(--warning)"></i> Buku Besar Komisi</h3>
            </div>

            <div class="period-metrics-grid" style="margin-bottom: 16px;">
                <div class="period-metric-card">
                    <span class="period-metric-label">Komisi Hari Ini</span>
                    <strong class="period-metric-value"><?= formatRupiah($periodCommission['daily_commission']) ?></strong>
                    <small class="period-metric-sub">Selesai: <?= (int)$periodOrders['daily_completed'] ?> pesanan</small>
                </div>
                <div class="period-metric-card">
                    <span class="period-metric-label">Komisi 7 Hari</span>
                    <strong class="period-metric-value"><?= formatRupiah($periodCommission['weekly_commission']) ?></strong>
                    <small class="period-metric-sub">Selesai: <?= (int)$periodOrders['weekly_completed'] ?> pesanan</small>
                </div>
                <div class="period-metric-card">
                    <span class="period-metric-label">Komisi Bulan Ini</span>
                    <strong class="period-metric-value"><?= formatRupiah($periodCommission['monthly_commission']) ?></strong>
                    <small class="period-metric-sub">Selesai: <?= (int)$periodOrders['monthly_completed'] ?> pesanan</small>
                </div>
            </div>

            <div class="ledger-summary">
                <div class="ledger-item earned">
                    <div class="ledger-value"><?= formatRupiah($totalEarned) ?></div>
                    <div class="ledger-label">Total Diperoleh</div>
                </div>
                <div class="ledger-item paid">
                    <div class="ledger-value"><?= formatRupiah($totalPaid) ?></div>
                    <div class="ledger-label">Sudah Dibayar</div>
                </div>
                <div class="ledger-item remaining">
                    <div class="ledger-value"><?= formatRupiah($pendingCommission) ?></div>
                    <div class="ledger-label">Sisa (Belum Dibayar)</div>
                </div>
            </div>

            <?php if ($pendingCommission > 0): ?>
            <form method="POST" style="padding:16px; background:var(--bg-input); border-radius:var(--radius-sm); border:1px solid var(--border); margin-bottom:20px;">
                <p style="font-size:13px; font-weight:600; color:var(--accent); margin-bottom:12px;">
                    <i class='bx bx-money-withdraw'></i> Bayar Komisi
                </p>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:8px;">
                        <input type="text" name="pay_amount" class="form-control" placeholder="Jumlah (Rp)" required>
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <input type="text" name="pay_notes" class="form-control" placeholder="Catatan (opsional)">
                    </div>
                </div>
                <button type="submit" name="pay_commission" class="btn btn-success btn-sm" onclick="return confirm('Konfirmasi pembayaran komisi?')">
                    <i class='bx bx-check'></i> Bayar
                </button>
            </form>
            <?php endif; ?>

            <?php if (empty($ledgerEntries)): ?>
                <div class="empty-state" style="padding:20px;"><p>Belum ada catatan komisi.</p></div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Jumlah</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledgerEntries as $entry): ?>
                        <tr>
                            <td style="font-size:12px; color:var(--text-muted);"><?= date('d M Y, H:i', strtotime($entry['created_at'])) ?></td>
                            <td>
                                <?php if ($entry['type'] === 'earned'): ?>
                                    <span class="badge badge-warning">Earned</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:700; color:<?= $entry['type'] === 'earned' ? 'var(--warning)' : 'var(--success)' ?>;">
                                <?= $entry['type'] === 'earned' ? '+' : '-' ?><?= formatRupiah($entry['amount']) ?>
                            </td>
                            <td style="font-size:13px;">
                                <?= sanitize($entry['notes'] ?? '-') ?>
                                <?php if ($entry['order_number']): ?>
                                    <a href="index.php?page=order_detail&id=<?= $entry['order_number'] ?>" style="color:var(--primary-light);">#<?= $entry['order_number'] ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Order History -->
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bx-history' style="color:var(--primary-light)"></i> Riwayat Pesanan</h3>
            </div>
            <?php if (empty($orderHistory)): ?>
                <div class="empty-state"><i class='bx bx-receipt'></i><p>Belum ada pesanan.</p></div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pelanggan</th>
                            <th>Rank</th>
                            <th>Harga</th>
                            <th>Komisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderHistory as $o): ?>
                        <tr onclick="location.href='index.php?page=order_detail&id=<?= $o['id'] ?>'" style="cursor:pointer;">
                            <td style="font-weight:600; color:var(--text-muted);">#<?= $o['id'] ?></td>
                            <td style="font-weight:600; color:var(--text-primary);"><?= sanitize($o['customer_name']) ?></td>
                            <td style="font-size:13px;"><?= sanitize($o['rank_from']) ?> → <?= sanitize($o['rank_to']) ?></td>
                            <td style="color:var(--success); font-weight:600;"><?= formatRupiah($o['price']) ?></td>
                            <td style="color:var(--warning);"><?= formatRupiah($o['worker_commission']) ?></td>
                            <td><?= statusLabel($o['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Card -->
    <div>
        <div class="card">
            <div style="text-align:center; padding:10px 0 20px;">
                <div style="width:64px; height:64px; background:linear-gradient(135deg, var(--primary), var(--accent)); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:24px; font-weight:700; color:white; margin-bottom:12px;">
                    <?= strtoupper(substr($worker['name'], 0, 1)) ?>
                </div>
                <h3 style="font-size:18px; margin-bottom:4px;"><?= sanitize($worker['name']) ?></h3>
                <?= workerStatusBadge($worker['active_orders']) ?>
                <?php if (!$worker['is_active']): ?>
                    <span class="badge badge-danger" style="margin-left:4px;">Non-Aktif</span>
                <?php endif; ?>
            </div>

            <div class="detail-row">
                <span class="label">Rekening / E-Wallet</span>
                <span class="value"><?= sanitize($worker['phone'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Akun Social Media</span>
                <span class="value"><?= sanitize($worker['notes'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Highest Rank</span>
                <span class="value"><?= sanitize($worker['rank_info'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">3 Role Utama</span>
                <span class="value" style="max-width:200px; text-align:right;"><?= sanitize($worker['roles'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Hero Andalan per Role</span>
                <span class="value" style="max-width:220px; text-align:right; white-space:normal;"><?= nl2br(sanitize($worker['specialization'] ?? '-')) ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Pesanan Aktif</span>
                <span class="value" style="font-weight:700;"><?= $worker['active_orders'] ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Pesanan Selesai</span>
                <span class="value"><?= $worker['completed_orders'] ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Bergabung</span>
                <span class="value" style="font-size:12px;"><?= date('d M Y', strtotime($worker['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

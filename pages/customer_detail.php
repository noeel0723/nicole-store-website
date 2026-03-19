<?php
/**
 * Customer Detail / Profile
 */
$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php?page=customers');
}

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_GET['id']]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('error', 'Pelanggan tidak ditemukan.');
    redirect('index.php?page=customers');
}

// Order history
$orders = $db->prepare("
    SELECT o.*, w.name as worker_name
    FROM orders o
    LEFT JOIN workers w ON o.worker_id = w.id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
");
$orders->execute([$customer['id']]);
$orderHistory = $orders->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Profil Pelanggan</h2>
        <p>Detail dan riwayat pesanan</p>
    </div>
    <div class="btn-group">
        <a href="index.php?page=customer_form&id=<?= $customer['id'] ?>" class="btn btn-outline"><i class='bx bx-edit'></i> Edit</a>
        <a href="index.php?page=customers" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Kembali</a>
    </div>
</div>

<div class="detail-grid">
    <div>
        <!-- Order History -->
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bx-history' style="color:var(--primary-light)"></i> Riwayat Pesanan</h3>
                <a href="index.php?page=order_form" class="btn btn-sm btn-primary"><i class='bx bx-plus'></i> Pesanan Baru</a>
            </div>

            <?php if (empty($orderHistory)): ?>
                <div class="empty-state"><i class='bx bx-receipt'></i><h3>Belum Ada Pesanan</h3></div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Rank</th>
                            <th>Worker</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderHistory as $o): ?>
                        <tr onclick="location.href='index.php?page=order_detail&id=<?= $o['id'] ?>'" style="cursor:pointer">
                            <td style="font-weight:600; color:var(--text-muted);">#<?= $o['id'] ?></td>
                            <td style="font-size:13px;"><?= sanitize($o['rank_from']) ?> → <?= sanitize($o['rank_to']) ?></td>
                            <td><?= $o['worker_name'] ? sanitize($o['worker_name']) : '-' ?></td>
                            <td style="font-weight:600; color:var(--success);"><?= formatRupiah($o['price']) ?></td>
                            <td><?= statusLabel($o['status']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
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
                    <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                </div>
                <h3 style="font-size:18px; margin-bottom:4px;"><?= sanitize($customer['name']) ?></h3>
                <?php if ($customer['total_orders'] >= 5): ?>
                    <span class="badge badge-vip"><i class='bx bx-crown' style="margin-right:4px;"></i> VIP</span>
                <?php elseif ($customer['total_orders'] >= 3): ?>
                    <span class="badge badge-warning">Loyal</span>
                <?php else: ?>
                    <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">Regular</span>
                <?php endif; ?>
            </div>

            <div class="detail-row">
                <span class="label">Kontak</span>
                <span class="value"><?= sanitize(formatCustomerContact($customer['phone'] ?? '')) ?></span>
            </div>
            <div class="detail-row">
                <span class="label">ID Game</span>
                <span class="value" style="font-family:monospace;"><?= sanitize($customer['game_id'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Total Pesanan</span>
                <span class="value"><?= $customer['total_orders'] ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Total Spent</span>
                <span class="value" style="color:var(--success);"><?= formatRupiah($customer['total_spent']) ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Bergabung</span>
                <span class="value" style="font-size:12px;"><?= date('d M Y', strtotime($customer['created_at'])) ?></span>
            </div>

            <?php if ($customer['notes']): ?>
            <div style="margin-top:16px; padding:12px; background:var(--bg-input); border-radius:var(--radius-sm); font-size:13px; color:var(--text-secondary);">
                <strong style="display:block; margin-bottom:4px; font-size:11px; color:var(--text-muted);">CATATAN</strong>
                <?= nl2br(sanitize($customer['notes'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/**
 * Workers List
 */
$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE worker_id = ? AND status != 'completed'");
    $orderCount->execute([$_GET['delete']]);
    if ($orderCount->fetchColumn() > 0) {
        flash('error', 'Tidak bisa menghapus worker yang masih memiliki pesanan aktif.');
    } else {
        $db->prepare("DELETE FROM workers WHERE id = ?")->execute([$_GET['delete']]);
        flash('success', 'Worker berhasil dihapus.');
    }
    redirect('index.php?page=workers');
}

$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE w.name LIKE ? OR w.specialization LIKE ? OR w.rank_info LIKE ? OR w.roles LIKE ? OR w.notes LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
}

$stmt = $db->prepare("
    SELECT w.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.worker_id = w.id AND o.status IN ('in_progress','pending_verification')) as active_orders,
        (SELECT COUNT(*) FROM orders o WHERE o.worker_id = w.id AND o.status = 'completed') as completed_orders,
        (SELECT COALESCE(SUM(amount),0) FROM worker_commissions wc WHERE wc.worker_id = w.id AND wc.type = 'earned' AND DATE(wc.created_at) = CURRENT_DATE()) as earned_today,
        (SELECT COALESCE(SUM(amount),0) FROM worker_commissions wc WHERE wc.worker_id = w.id AND wc.type = 'earned' AND wc.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)) as earned_week,
        (SELECT COALESCE(SUM(amount),0) FROM worker_commissions wc WHERE wc.worker_id = w.id AND wc.type = 'earned' AND MONTH(wc.created_at) = MONTH(CURRENT_DATE()) AND YEAR(wc.created_at) = YEAR(CURRENT_DATE())) as earned_month,
        (SELECT COALESCE(SUM(amount),0) FROM worker_commissions wc WHERE wc.worker_id = w.id AND wc.type = 'earned') as total_earned,
        (SELECT COALESCE(SUM(amount),0) FROM worker_commissions wc WHERE wc.worker_id = w.id AND wc.type = 'paid') as total_paid
    FROM workers w
    $where
    ORDER BY w.created_at DESC
");
$stmt->execute($params);
$workers = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Manajemen Worker</h2>
        <p>Total: <?= count($workers) ?> worker</p>
    </div>
    <a href="index.php?page=worker_form" class="btn btn-primary">
        <i class='bx bx-plus'></i> Tambah Worker
    </a>
</div>

<div class="filter-bar">
    <div class="search-box">
        <i class='bx bx-search'></i>
        <input type="text" placeholder="Cari nama, spesialisasi, atau rank..."
               value="<?= sanitize($search) ?>"
               onkeydown="if(event.key==='Enter') location.href='index.php?page=workers&search='+this.value">
    </div>
</div>

<?php if (empty($workers)): ?>
<div class="card">
    <div class="empty-state">
        <i class='bx bx-user-plus'></i>
        <h3>Belum Ada Worker</h3>
        <p>Rekrut worker pertama Anda.</p>
    </div>
</div>
<?php else: ?>
<div class="worker-list">
    <?php foreach ($workers as $w): ?>
    <?php $pendingCommission = $w['total_earned'] - $w['total_paid']; ?>
    <div class="worker-list-card">
        <div class="wlc-avatar">
            <?= strtoupper(substr($w['name'], 0, 1)) ?>
        </div>
        <div class="wlc-info">
            <span class="wlc-name"><?= sanitize($w['name']) ?></span>
            <div class="wlc-meta">
                <span class="wlc-meta-item">
                    <i class='bx bx-credit-card'></i> <?= sanitize($w['phone'] ?? '-') ?>
                </span>
                <span class="wlc-meta-item">
                    <i class='bx bx-trophy'></i> <?= sanitize($w['rank_info'] ?? '-') ?>
                </span>
                <?php if ($w['active_orders'] > 0): ?>
                <span class="wlc-meta-item">
                    <i class='bx bx-loader-alt'></i> <?= $w['active_orders'] ?> pesanan aktif
                </span>
                <?php endif; ?>
            </div>
            <?php if ($w['roles']): ?>
            <div class="wlc-tags">
                <?php foreach (array_filter(array_map('trim', explode(',', $w['roles']))) as $role): ?>
                <span class="wlc-tag"><i class='bx bx-shield-quarter'></i> <?= sanitize($role) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="wlc-right">
            <div class="wlc-commission">
                <?php if ($pendingCommission > 0): ?>
                <span class="wlc-commission-value" style="color:var(--warning);"><?= formatRupiah($pendingCommission) ?></span>
                <?php else: ?>
                <span class="wlc-commission-value" style="color:var(--text-muted);">Rp 0</span>
                <?php endif; ?>
                <span class="wlc-commission-label">Saldo Komisi</span>
            </div>
            <div class="wlc-status">
                <?= workerStatusBadge($w['active_orders']) ?>
            </div>
            <div class="wlc-actions">
                <a href="index.php?page=worker_detail&id=<?= $w['id'] ?>" class="btn-icon" title="Detail"><i class='bx bx-show'></i></a>
                <a href="index.php?page=worker_form&id=<?= $w['id'] ?>" class="btn-icon" title="Edit"><i class='bx bx-edit'></i></a>
                <a href="index.php?page=workers&delete=<?= $w['id'] ?>" class="btn-icon" title="Hapus"
                   onclick="return confirm('Yakin hapus worker ini?')"><i class='bx bx-trash' style="color:var(--danger)"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

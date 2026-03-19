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
    $where = "WHERE w.name LIKE ? OR w.specialization LIKE ? OR w.rank_info LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
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

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Worker</th>
                    <th>Rank</th>
                    <th>Spesialisasi</th>
                    <th>Komisi Hari Ini</th>
                    <th>Komisi 7 Hari</th>
                    <th>Komisi Bulan Ini</th>
                    <th>Pesanan Aktif</th>
                    <th>Selesai</th>
                    <th>Saldo Komisi</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workers)): ?>
                <tr><td colspan="11"><div class="empty-state"><i class='bx bx-user-plus'></i><h3>Belum Ada Worker</h3><p>Rekrut worker pertama Anda.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($workers as $w): ?>
                <?php $pendingCommission = $w['total_earned'] - $w['total_paid']; ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; background:linear-gradient(135deg, var(--primary), var(--accent)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:white; flex-shrink:0;">
                                <?= strtoupper(substr($w['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong style="display:block; font-size:14px; color:var(--text-primary);"><?= sanitize($w['name']) ?></strong>
                                <span style="font-size:11px; color:var(--text-muted);"><?= sanitize($w['phone'] ?? '-') ?></span>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:600; font-size:13px;"><?= sanitize($w['rank_info'] ?? '-') ?></td>
                    <td style="font-size:13px;"><?= sanitize($w['specialization'] ?? '-') ?></td>
                    <td style="font-size:13px; font-weight:600;"><?= formatRupiah($w['earned_today']) ?></td>
                    <td style="font-size:13px; font-weight:600;"><?= formatRupiah($w['earned_week']) ?></td>
                    <td style="font-size:13px; font-weight:600;"><?= formatRupiah($w['earned_month']) ?></td>
                    <td style="font-weight:700; text-align:center;"><?= $w['active_orders'] ?></td>
                    <td style="text-align:center; color:var(--text-muted);"><?= $w['completed_orders'] ?></td>
                    <td>
                        <?php if ($pendingCommission > 0): ?>
                            <span style="color:var(--warning); font-weight:700;"><?= formatRupiah($pendingCommission) ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">Rp 0</span>
                        <?php endif; ?>
                    </td>
                    <td><?= workerStatusBadge($w['active_orders']) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="index.php?page=worker_detail&id=<?= $w['id'] ?>" class="btn-icon" title="Detail"><i class='bx bx-show'></i></a>
                            <a href="index.php?page=worker_form&id=<?= $w['id'] ?>" class="btn-icon" title="Edit"><i class='bx bx-edit'></i></a>
                            <a href="index.php?page=workers&delete=<?= $w['id'] ?>" class="btn-icon" title="Hapus"
                               onclick="return confirm('Yakin hapus worker ini?')"><i class='bx bx-trash' style="color:var(--danger)"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

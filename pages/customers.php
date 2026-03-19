<?php
/**
 * Customers List
 */
$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Check if customer has orders
    $orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
    $orderCount->execute([$_GET['delete']]);
    if ($orderCount->fetchColumn() > 0) {
        flash('error', 'Tidak bisa menghapus pelanggan yang memiliki pesanan.');
    } else {
        $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$_GET['delete']]);
        flash('success', 'Pelanggan berhasil dihapus.');
    }
    redirect('index.php?page=customers');
}

$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE name LIKE ? OR phone LIKE ? OR game_id LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $db->prepare("SELECT * FROM customers $where ORDER BY created_at DESC");
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Database Pelanggan</h2>
        <p>Total: <?= count($customers) ?> pelanggan</p>
    </div>
    <a href="index.php?page=customer_form" class="btn btn-primary">
        <i class='bx bx-plus'></i> Tambah Pelanggan
    </a>
</div>

<div class="filter-bar">
    <div class="search-box">
        <i class='bx bx-search'></i>
        <input type="text" placeholder="Cari nama, HP, atau ID Game..."
               value="<?= sanitize($search) ?>"
               onkeydown="if(event.key==='Enter') location.href='index.php?page=customers&search='+this.value">
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>Kontak</th>
                    <th>ID Game</th>
                    <th>Total Order</th>
                    <th>Total Spent</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class='bx bx-user-plus'></i><h3>Belum Ada Pelanggan</h3><p>Tambahkan pelanggan pertama.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td style="color:var(--text-muted); font-weight:600;">#<?= $c['id'] ?></td>
                    <td style="font-weight:600; color:var(--text-primary);">
                        <?= sanitize($c['name']) ?>
                    </td>
                    <td><?= sanitize(formatCustomerContact($c['phone'] ?? '')) ?></td>
                    <td style="font-family:monospace; font-size:13px;"><?= sanitize($c['game_id'] ?? '-') ?></td>
                    <td style="font-weight:600;"><?= $c['total_orders'] ?></td>
                    <td style="color:var(--success); font-weight:600;"><?= formatRupiah($c['total_spent']) ?></td>
                    <td>
                        <?php if ($c['total_orders'] >= 5): ?>
                            <span class="badge badge-vip"><i class='bx bx-crown' style="margin-right:4px;"></i> VIP</span>
                        <?php elseif ($c['total_orders'] >= 3): ?>
                            <span class="badge badge-warning">Loyal</span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">Regular</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="index.php?page=customer_detail&id=<?= $c['id'] ?>" class="btn-icon" title="Detail"><i class='bx bx-show'></i></a>
                            <a href="index.php?page=customer_form&id=<?= $c['id'] ?>" class="btn-icon" title="Edit"><i class='bx bx-edit'></i></a>
                            <a href="index.php?page=customers&delete=<?= $c['id'] ?>" class="btn-icon" title="Hapus"
                               onclick="return confirm('Yakin hapus pelanggan ini?')"><i class='bx bx-trash' style="color:var(--danger)"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

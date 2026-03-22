<?php
/**
 * Customers List - Clean UI
 */
$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
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
$where = 'WHERE is_guest = 0';
$params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR phone LIKE ? OR game_id LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $db->prepare("SELECT * FROM customers $where ORDER BY created_at DESC");
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalCustomers = count($customers);
$activeCustomers = array_filter($customers, fn($c) => $c['total_orders'] > 0);
$newThisMonth = array_filter($customers, fn($c) => date('Y-m', strtotime($c['created_at'])) === date('Y-m'));

$avatarColors = [
    '#685D54', '#7D7168', '#A39382', '#6889A0', '#5B8C5A',
    '#8B7BA0', '#C0A062', '#B05A4A', '#4A4039', '#6A5E7A'
];
?>

<!-- Stat Cards -->
<div class="customer-stats-grid">
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Total Pelanggan</span>
            <span class="cs-value"><?= $totalCustomers ?></span>
            <span class="cs-meta"><i class='bx bx-trending-up'></i> Semua pelanggan terdaftar</span>
        </div>
        <div class="cs-icon" style="background:rgba(104,93,84,0.12); color:var(--primary);">
            <i class='bx bxs-group'></i>
        </div>
    </div>
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Pelanggan Aktif</span>
            <span class="cs-value"><?= count($activeCustomers) ?></span>
            <span class="cs-meta"><i class='bx bx-check-circle'></i> Pernah order</span>
        </div>
        <div class="cs-icon" style="background:rgba(104,137,160,0.14); color:var(--info);">
            <i class='bx bxs-bolt'></i>
        </div>
    </div>
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Baru Bulan Ini</span>
            <span class="cs-value"><?= count($newThisMonth) ?></span>
            <span class="cs-meta"><i class='bx bx-calendar'></i> <?= date('F Y') ?></span>
        </div>
        <div class="cs-icon" style="background:rgba(91,140,90,0.14); color:var(--success);">
            <i class='bx bxs-user-plus'></i>
        </div>
    </div>
</div>

<!-- Toolbar: Search + Add Button -->
<div class="customer-toolbar">
    <div class="search-box" style="min-width:220px; flex:1;">
        <i class='bx bx-search'></i>
        <input type="text" placeholder="Cari pelanggan..."
               value="<?= sanitize($search) ?>"
               onkeydown="if(event.key==='Enter') location.href='index.php?page=customers&search='+this.value">
    </div>
    <a href="index.php?page=customer_form" class="btn btn-primary">
        <i class='bx bx-plus'></i> Tambah Pelanggan
    </a>
</div>

<!-- Customer Table -->
<div class="card" style="padding:0; overflow:hidden;">
    <div class="table-wrapper" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Pelanggan</th>
                    <th>Game ID</th>
                    <th>Kontak</th>
                    <th>Total Orders</th>
                    <th>Total Spent</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="6"><div class="empty-state"><i class='bx bx-user-plus'></i><h3>Belum Ada Pelanggan</h3><p>Tambahkan pelanggan pertama.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($customers as $i => $c):
                    $initials = '';
                    $nameParts = explode(' ', trim($c['name']));
                    if (count($nameParts) >= 2) {
                        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($c['name'], 0, 2));
                    }
                    $avatarColor = $avatarColors[$c['id'] % count($avatarColors)];
                    $contact = parseCustomerContact($c['phone'] ?? '');
                    $joinDate = date('d M Y', strtotime($c['created_at']));
                ?>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="customer-avatar" style="background:<?= $avatarColor ?>;">
                                <?= $initials ?>
                            </div>
                            <div class="customer-info">
                                <a href="index.php?page=customer_detail&id=<?= $c['id'] ?>" class="customer-name">
                                    <?= sanitize($c['name']) ?>
                                </a>
                                <span class="customer-join">Joined <?= $joinDate ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($c['game_id']): ?>
                        <span class="game-id-badge">#<?= sanitize($c['game_id']) ?></span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($contact['phone']): ?>
                            <div style="font-size:13px; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                                <i class='bx bx-phone' style="color:var(--text-muted); font-size:14px;"></i>
                                <?= sanitize($contact['phone']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($contact['social']): ?>
                            <div style="font-size:13px; display:flex; align-items:center; gap:4px;">
                                <i class='bx bx-envelope' style="color:var(--text-muted); font-size:14px;"></i>
                                <?= sanitize($contact['social']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$contact['phone'] && !$contact['social']): ?>
                            <span style="color:var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:600;"><?= $c['total_orders'] ?></span>
                        <span style="color:var(--text-muted); font-size:12px;">Orders</span>
                    </td>
                    <td style="font-weight:600; color:var(--success);">
                        <?= formatRupiah($c['total_spent']) ?>
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

<div style="text-align:right; margin-top:12px;">
    <span style="font-size:13px; color:var(--text-muted);">
        Menampilkan <?= count($customers) ?> pelanggan
    </span>
</div>

<?php
/**
 * Customers List - Redesigned UI
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
$filter = $_GET['filter'] ?? 'all';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE name LIKE ? OR phone LIKE ? OR game_id LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $db->prepare("SELECT * FROM customers $where ORDER BY created_at DESC");
$stmt->execute($params);
$allCustomers = $stmt->fetchAll();

// Calculate stats
$totalCustomers = count($allCustomers);
$vipCustomers = array_filter($allCustomers, fn($c) => $c['total_orders'] >= 5);
$regularCustomers = array_filter($allCustomers, fn($c) => $c['total_orders'] < 5);
$newThisMonth = array_filter($allCustomers, fn($c) => date('Y-m', strtotime($c['created_at'])) === date('Y-m'));
$activeCustomers = array_filter($allCustomers, fn($c) => $c['total_orders'] > 0);

// Apply filter
if ($filter === 'vip') {
    $customers = $vipCustomers;
} elseif ($filter === 'regular') {
    $customers = array_filter($allCustomers, fn($c) => $c['total_orders'] >= 1 && $c['total_orders'] < 5);
} elseif ($filter === 'inactive') {
    $customers = array_filter($allCustomers, fn($c) => $c['total_orders'] == 0);
} else {
    $customers = $allCustomers;
}

// Avatar colors
$avatarColors = [
    '#344945', '#4F6963', '#65848F', '#8C6153', '#9E9A62',
    '#6B7B76', '#5A7A6D', '#7A6B5A', '#6A5E7A', '#5E7A6A'
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
        <div class="cs-icon" style="background:rgba(52,73,69,0.12); color:var(--primary);">
            <i class='bx bxs-group'></i>
        </div>
    </div>
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Pelanggan Aktif</span>
            <span class="cs-value"><?= count($activeCustomers) ?></span>
            <span class="cs-meta"><i class='bx bx-check-circle'></i> Pernah order</span>
        </div>
        <div class="cs-icon" style="background:rgba(101,132,143,0.16); color:var(--info);">
            <i class='bx bxs-bolt'></i>
        </div>
    </div>
    <div class="customer-stat-card">
        <div class="cs-info">
            <span class="cs-label">Baru Bulan Ini</span>
            <span class="cs-value"><?= count($newThisMonth) ?></span>
            <span class="cs-meta"><i class='bx bx-calendar'></i> <?= date('F Y') ?></span>
        </div>
        <div class="cs-icon" style="background:rgba(79,111,102,0.14); color:var(--success);">
            <i class='bx bxs-user-plus'></i>
        </div>
    </div>
</div>

<!-- Filter Tabs + Search -->
<div class="customer-toolbar">
    <div class="filter-tabs">
        <a href="index.php?page=customers&filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All Customers <span class="ft-count"><?= $totalCustomers ?></span>
        </a>
        <a href="index.php?page=customers&filter=regular<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="filter-tab <?= $filter === 'regular' ? 'active' : '' ?>">
            Regular
        </a>
        <a href="index.php?page=customers&filter=vip<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="filter-tab <?= $filter === 'vip' ? 'active' : '' ?>">
            VIP Member <span class="ft-count"><?= count($vipCustomers) ?></span>
        </a>
        <a href="index.php?page=customers&filter=inactive<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="filter-tab <?= $filter === 'inactive' ? 'active' : '' ?>">
            Inactive
        </a>
    </div>
    <div class="customer-toolbar-right">
        <div class="search-box" style="min-width:220px;">
            <i class='bx bx-search'></i>
            <input type="text" placeholder="Cari pelanggan..."
                   value="<?= sanitize($search) ?>"
                   onkeydown="if(event.key==='Enter') location.href='index.php?page=customers&filter=<?= $filter ?>&search='+this.value">
        </div>
    </div>
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
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class='bx bx-user-plus'></i><h3>Belum Ada Pelanggan</h3><p>Tambahkan pelanggan pertama.</p></div></td></tr>
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
                        <?php if ($c['total_orders'] >= 5): ?>
                            <div>
                                <span class="badge badge-vip" style="margin-bottom:2px;"><i class='bx bx-crown' style="margin-right:3px;"></i> VIP</span>
                                <br><span class="badge" style="background:rgba(111,106,51,0.1); color:#6F6A33; font-size:10px;">MEMBER</span>
                            </div>
                        <?php elseif ($c['total_orders'] >= 1): ?>
                            <span class="badge" style="background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border);">REGULAR</span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">INACTIVE</span>
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

<!-- Bottom: Add Button + Count -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
    <a href="index.php?page=customer_form" class="btn btn-primary">
        <i class='bx bx-plus'></i> Tambah Pelanggan
    </a>
    <span style="font-size:13px; color:var(--text-muted);">
        Menampilkan <?= count($customers) ?> dari <?= $totalCustomers ?> pelanggan
    </span>
</div>

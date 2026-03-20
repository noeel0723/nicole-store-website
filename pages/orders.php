<?php
/**
 * Orders List - Table View Only
 */
$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    flash('success', 'Pesanan berhasil dihapus.');
    redirect('index.php?page=orders');
}

// Handle quick status update
if (isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    $validStatuses = ['unassigned', 'in_progress', 'pending_verification', 'completed'];

    if (in_array($newStatus, $validStatuses)) {
        $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("UPDATE orders SET status = ?, completed_at = COALESCE(?, completed_at), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $completedAt, $orderId]);

        // If completed, create commission entry
        if ($newStatus === 'completed') {
            $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $order->execute([$orderId]);
            $orderData = $order->fetch();
            if ($orderData && $orderData['worker_id'] && $orderData['worker_commission'] > 0) {
                $stmt = $db->prepare("INSERT INTO worker_commissions (worker_id, order_id, amount, type, notes) VALUES (?, ?, ?, 'earned', ?)");
                $stmt->execute([$orderData['worker_id'], $orderId, $orderData['worker_commission'], 'Komisi pesanan #' . $orderId]);
            }
            // Update customer stats
            if ($orderData) {
                $db->prepare("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?")->execute([$orderData['price'], $orderData['customer_id']]);
            }
        }

        flash('success', 'Status pesanan berhasil diperbarui.');
    }
    redirect('index.php?page=orders');
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($searchQuery) {
    $where .= " AND (c.name LIKE ? OR o.rank_from LIKE ? OR o.rank_to LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$stmt = $db->prepare("
    SELECT o.*, c.name as customer_name, c.game_id as customer_game_id, w.name as worker_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN workers w ON o.worker_id = w.id
    $where
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$allOrders = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Manajemen Pesanan</h2>
        <p>Total: <?= count($allOrders) ?> pesanan</p>
    </div>
    <a href="index.php?page=order_form" class="btn btn-primary">
        <i class='bx bx-plus'></i> Tambah Pesanan
    </a>
</div>

<div class="filter-bar">
    <div class="search-box">
        <i class='bx bx-search'></i>
        <input type="text" placeholder="Cari pelanggan atau rank..." id="searchOrders"
               value="<?= sanitize($searchQuery) ?>"
               onkeydown="if(event.key==='Enter') location.href='index.php?page=orders&search='+this.value">
    </div>
    <select class="filter-select" onchange="location.href='index.php?page=orders&status='+this.value">
        <option value="">Semua Status</option>
        <option value="unassigned" <?= $statusFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="pending_verification" <?= $statusFilter === 'pending_verification' ? 'selected' : '' ?>>Pending</option>
        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
    </select>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pelanggan</th>
                    <th>Rank</th>
                    <th>Worker</th>
                    <th>Harga</th>
                    <th>Pembayaran</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allOrders)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class='bx bx-receipt'></i><h3>Belum Ada Pesanan</h3></div></td></tr>
                <?php else: ?>
                <?php foreach ($allOrders as $o): ?>
                <tr>
                    <td style="font-weight:600; color:var(--text-muted);">#<?= $o['id'] ?></td>
                    <td style="font-weight:600; color:var(--text-primary);"><?= sanitize($o['customer_name']) ?></td>
                    <td style="font-size:12px;"><?= sanitize($o['rank_from']) ?> → <?= sanitize($o['rank_to']) ?></td>
                    <td><?= $o['worker_name'] ? sanitize($o['worker_name']) : '<span style="color:var(--danger)">-</span>' ?></td>
                    <td style="font-weight:600; color:var(--success);"><?= formatRupiah($o['price']) ?></td>
                    <td><?= paymentLabel($o['payment_status']) ?></td>
                    <td><?= statusLabel($o['status']) ?></td>
                    <td style="font-size:12px;"><?= $o['deadline'] ? date('d M Y', strtotime($o['deadline'])) : '-' ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="index.php?page=order_detail&id=<?= $o['id'] ?>" class="btn-icon" title="Detail"><i class='bx bx-show'></i></a>
                            <a href="index.php?page=order_form&id=<?= $o['id'] ?>" class="btn-icon" title="Edit"><i class='bx bx-edit'></i></a>
                            <a href="index.php?page=orders&delete=<?= $o['id'] ?>" class="btn-icon" title="Hapus"
                               onclick="return confirm('Yakin ingin menghapus pesanan ini?')"><i class='bx bx-trash' style="color:var(--danger)"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

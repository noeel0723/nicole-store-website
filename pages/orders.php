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

<?php if (empty($allOrders)): ?>
<div class="card">
    <div class="empty-state">
        <i class='bx bx-receipt'></i>
        <h3>Belum Ada Pesanan</h3>
        <p>Buat pesanan pertama Anda.</p>
    </div>
</div>
<?php else: ?>
<div class="order-cards-grid" id="ordersCardsGrid">
    <?php foreach ($allOrders as $o): ?>
    <?php
    $workerLabel = '<span style="color:var(--danger)">Belum ditugaskan</span>';
    if (!empty($o['worker_name'])) {
        $workerLabel = sanitize($o['worker_name']);
    } elseif ((float)$o['worker_commission'] === 0.0 && $o['status'] !== 'unassigned') {
        $workerLabel = '<span style="color:var(--primary); font-weight:600;">Admin</span>';
    }
    ?>
    <div class="order-card order-card-item status-<?= $o['status'] ?>">
        <div class="order-card-header">
            <div class="oc-left">
                <div class="oc-avatar">
                    <?= strtoupper(substr($o['customer_name'], 0, 1)) ?>
                </div>
                <div class="oc-info">
                    <span class="oc-customer"><?= sanitize($o['customer_name']) ?></span>
                    <span class="oc-id">Pesanan #<?= $o['id'] ?></span>
                </div>
            </div>
            <?= statusLabel($o['status']) ?>
        </div>

        <div class="order-card-body">
            <div class="oc-rank">
                <i class='bx bx-trophy'></i>
                <?= sanitize($o['rank_from']) ?> → <?= sanitize($o['rank_to']) ?>
            </div>

            <div class="oc-detail-row">
                <span class="oc-label">Worker</span>
                <span class="oc-value"><?= $workerLabel ?></span>
            </div>
            <div class="oc-detail-row">
                <span class="oc-label">Pembayaran</span>
                <span class="oc-value"><?= paymentLabel($o['payment_status']) ?></span>
            </div>
            <div class="oc-detail-row">
                <span class="oc-label">Deadline</span>
                <span class="oc-value"><?= $o['deadline'] ? date('d M Y', strtotime($o['deadline'])) : '-' ?></span>
            </div>
        </div>

        <div class="order-card-footer">
            <span class="oc-price"><?= formatRupiah($o['price']) ?></span>
            <div class="oc-actions">
                <a href="index.php?page=order_detail&id=<?= $o['id'] ?>" class="btn-icon" title="Detail"><i class='bx bx-show'></i></a>
                <?php if ($o['status'] !== 'completed'): ?>
                <a href="index.php?page=order_form&id=<?= $o['id'] ?>" class="btn-icon" title="Edit"><i class='bx bx-edit'></i></a>
                <?php endif; ?>
                <a href="index.php?page=orders&delete=<?= $o['id'] ?>" class="btn-icon" title="Hapus"
                   onclick="return confirm('Yakin ingin menghapus pesanan ini?')"><i class='bx bx-trash' style="color:var(--danger)"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="table-pagination order-cards-pagination" id="ordersCardsPagination"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = Array.from(document.querySelectorAll('#ordersCardsGrid .order-card-item'));
    const pagination = document.getElementById('ordersCardsPagination');
    if (!pagination || cards.length <= 15) {
        if (pagination) pagination.style.display = 'none';
        return;
    }

    const perPage = 15;
    const totalPages = Math.ceil(cards.length / perPage);
    let currentPage = 1;

    function createBtn(label, page, active, disabled) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'page-btn' + (active ? ' active' : '');
        btn.textContent = label;
        btn.disabled = disabled;
        if (!disabled) {
            btn.addEventListener('click', function() {
                currentPage = page;
                renderCards();
                renderPagination();
            });
        }
        return btn;
    }

    function renderCards() {
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        cards.forEach((card, idx) => {
            card.style.display = (idx >= start && idx < end) ? '' : 'none';
        });
    }

    function renderPagination() {
        pagination.innerHTML = '';
        pagination.appendChild(createBtn('Prev', currentPage - 1, false, currentPage === 1));

        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        for (let page = startPage; page <= endPage; page++) {
            pagination.appendChild(createBtn(String(page), page, page === currentPage, false));
        }

        pagination.appendChild(createBtn('Next', currentPage + 1, false, currentPage === totalPages));
    }

    renderCards();
    renderPagination();
});
</script>
<?php endif; ?>

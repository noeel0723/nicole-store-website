<?php
/**
 * Log Activity - Ticket Updates Timeline
 */
$db = getDB();

// Fetch logs with optional search
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE ol.message LIKE ? OR c.name LIKE ? OR o.id = ?";
    $params = ["%$search%", "%$search%", (int)$search];
}

$stmt = $db->prepare("
    SELECT ol.*, o.customer_id, c.name as customer_name, o.status as order_status
    FROM order_logs ol
    JOIN orders o ON ol.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    $where
    ORDER BY ol.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Log Activity</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-top:4px;">Riwayat aktivitas dan update tiket pesanan terbaru</p>
    </div>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <div class="search-box">
        <i class='bx bx-search'></i>
        <form action="index.php" method="GET" style="display:inline; width:100%;">
            <input type="hidden" name="page" value="log_activity">
            <input type="text" name="search" placeholder="Cari log, ID pesanan, atau pelanggan..." value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>
</div>

<!-- Log List -->
<div class="card" style="padding:0;">
    <?php if (empty($logs)): ?>
        <div style="padding:40px; text-align:center; color:var(--text-muted);">
            <i class='bx bx-history' style="font-size:48px; margin-bottom:12px; opacity:0.5;"></i>
            <p>Belum ada aktivitas yang dicatat.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="150">TANGGAL & WAKTU</th>
                        <th width="120">PESANAN</th>
                        <th>PELANGGAN</th>
                        <th>AKTIVITAS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="color:var(--text-muted); font-size:13px;">
                                <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                                <strong style="color:var(--text-primary);"><?= date('H:i', strtotime($log['created_at'])) ?></strong>
                            </td>
                            <td>
                                <a href="index.php?page=order_detail&id=<?= $log['order_id'] ?>" style="font-weight:600; color:var(--primary);">
                                    #<?= $log['order_id'] ?>
                                </a>
                            </td>
                            <td>
                                <a href="index.php?page=customer_detail&id=<?= $log['customer_id'] ?>" style="color:var(--text-secondary);">
                                    <?= sanitize($log['customer_name']) ?>
                                </a>
                            </td>
                            <td>
                                <span style="font-size:14px;"><?= sanitize($log['message']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

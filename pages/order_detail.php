<?php
/**
 * Order Detail — 2-Column Panel Layout
 */
$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    flash('error', 'ID pesanan tidak valid.');
    redirect('index.php?page=orders');
}

$orderId = (int)$_GET['id'];
$stmt = $db->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.game_id as customer_game_id,
           w.name as worker_name, w.phone as worker_phone, w.rank_info as worker_rank
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN workers w ON o.worker_id = w.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Pesanan tidak ditemukan.');
    redirect('index.php?page=orders');
}

// Get logs
$logs = $db->prepare("SELECT * FROM order_logs WHERE order_id = ? ORDER BY created_at DESC");
$logs->execute([$orderId]);
$logs = $logs->fetchAll();

// Handle add log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_log'])) {
    $message = trim($_POST['log_message']);
    if (!empty($message)) {
        $stmt = $db->prepare("INSERT INTO order_logs (order_id, message) VALUES (?, ?)");
        $stmt->execute([$orderId, $message]);
        flash('success', 'Log progres berhasil ditambahkan.');
    }
    redirect('index.php?page=order_detail&id=' . $orderId);
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['new_status'];
    $validStatuses = ['unassigned', 'in_progress', 'pending_verification', 'completed'];
    if (in_array($newStatus, $validStatuses)) {

        // If completing, require proof photo
        if ($newStatus === 'completed') {
            $proofPath = null;
            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $fileType = $_FILES['proof_photo']['type'];
                if (in_array($fileType, $allowed)) {
                    $ext = pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION);
                    $filename = 'proof_' . $orderId . '_' . time() . '.' . $ext;
                    $uploadDir = __DIR__ . '/../uploads/proof/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $uploadDir . $filename)) {
                        $proofPath = 'uploads/proof/' . $filename;
                    }
                } else {
                    flash('error', 'Format file tidak valid. Gunakan JPG, PNG, WEBP, atau GIF.');
                    redirect('index.php?page=order_detail&id=' . $orderId);
                }
            } else {
                flash('error', 'Wajib upload foto bukti/testimoni untuk menyelesaikan pesanan.');
                redirect('index.php?page=order_detail&id=' . $orderId);
            }

            if (!$proofPath) {
                flash('error', 'Gagal mengupload foto. Coba lagi.');
                redirect('index.php?page=order_detail&id=' . $orderId);
            }

            $completedAt = date('Y-m-d H:i:s');
            $stmt = $db->prepare("UPDATE orders SET status = ?, completed_at = ?, proof_photo = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $completedAt, $proofPath, $orderId]);

            // Commission entry
            if ($order['worker_id'] && $order['worker_commission'] > 0) {
                $stmt = $db->prepare("INSERT INTO worker_commissions (worker_id, order_id, amount, type, notes) VALUES (?, ?, ?, 'earned', ?)");
                $stmt->execute([$order['worker_id'], $orderId, $order['worker_commission'], 'Komisi pesanan #' . $orderId]);
            }
            // Update customer stats
            $db->prepare("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?")->execute([$order['price'], $order['customer_id']]);

            // Auto log
            $db->prepare("INSERT INTO order_logs (order_id, message) VALUES (?, ?)")->execute([$orderId, '✅ Pesanan diselesaikan.']);

        } else {
            $completedAt = null;
            $stmt = $db->prepare("UPDATE orders SET status = ?, completed_at = COALESCE(?, completed_at), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $completedAt, $orderId]);
        }

        flash('success', 'Status berhasil diperbarui.');
    }
    redirect('index.php?page=order_detail&id=' . $orderId);
}

// Profit
$profit = $order['price'] - $order['worker_commission'];
$isCompleted = $order['status'] === 'completed';

// Joki type label
$jokiTypeLabels = [
    'joki_gendong' => 'Joki Gendong',
    'joki_login' => 'Joki Login',
];
$jokiTypeDisplay = $jokiTypeLabels[$order['joki_type'] ?? ''] ?? null;

// Contact parsing
$customerContact = parseCustomerContact($order['customer_phone'] ?? '');
?>

<!-- Breadcrumb + Actions -->
<div class="page-header">
    <div>
        <a href="index.php?page=orders" style="font-size:13px; color:var(--text-muted); display:inline-flex; align-items:center; gap:4px; margin-bottom:4px;">
            <i class='bx bx-arrow-back'></i> Kembali ke Pesanan
        </a>
        <h2>Pesanan #<?= $order['id'] ?> <?= statusLabel($order['status']) ?></h2>
    </div>
    <div class="btn-group">
        <?php if (!$isCompleted): ?>
        <a href="index.php?page=order_form&id=<?= $order['id'] ?>" class="btn btn-outline">
            <i class='bx bx-edit'></i> Edit
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2-Column Detail Layout -->
<div class="od-grid">
    <!-- LEFT: Order Info Panel -->
    <div class="od-panel">
        <div class="od-panel-header">
            <h3><i class='bx bx-package'></i> Detail Pesanan</h3>
        </div>

        <div class="od-section">
            <div class="od-row-2col">
                <div>
                    <span class="od-label">PELANGGAN</span>
                    <span class="od-value">
                        <a href="index.php?page=customer_detail&id=<?= $order['customer_id'] ?>" style="color:var(--mocha); font-weight:600;">
                            <?= sanitize($order['customer_name']) ?>
                        </a>
                    </span>
                </div>
                <div>
                    <span class="od-label">TANGGAL DIBUAT</span>
                    <span class="od-value"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                </div>
            </div>
            <div class="od-row-2col">
                <div>
                    <span class="od-label">ID GAME</span>
                    <span class="od-value"><?= sanitize($order['customer_game_id'] ?? '-') ?></span>
                </div>
                <div>
                    <span class="od-label">DEADLINE</span>
                    <span class="od-value"><?= $order['deadline'] ? date('d M Y', strtotime($order['deadline'])) : '-' ?></span>
                </div>
            </div>
        </div>

        <div class="od-divider"></div>

        <div class="od-section">
            <div class="od-row-2col">
                <div>
                    <span class="od-label">RANK AWAL</span>
                    <span class="od-value"><?= sanitize($order['rank_from']) ?></span>
                </div>
                <div>
                    <span class="od-label">RANK TUJUAN</span>
                    <span class="od-value"><?= sanitize($order['rank_to']) ?></span>
                </div>
            </div>
            <?php if ($jokiTypeDisplay): ?>
            <div class="od-row-2col">
                <div>
                    <span class="od-label">TIPE JOKI</span>
                    <span class="od-value">
                        <span class="badge <?= $order['joki_type'] === 'joki_gendong' ? 'badge-info' : 'badge-warning' ?>">
                            <?= $jokiTypeDisplay ?>
                        </span>
                    </span>
                </div>
                <div></div>
            </div>
            <?php endif; ?>
            <?php if ($order['request_hero'] || $order['request_role']): ?>
            <div class="od-row-2col">
                <?php if ($order['request_hero']): ?>
                <div>
                    <span class="od-label">REQUEST HERO</span>
                    <span class="od-value"><?= sanitize($order['request_hero']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['request_role']): ?>
                <div>
                    <span class="od-label">REQUEST ROLE</span>
                    <span class="od-value"><?= sanitize($order['request_role']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($order['special_request']): ?>
        <div class="od-divider"></div>
        <div class="od-section">
            <span class="od-label">CATATAN KHUSUS</span>
            <p style="margin-top:6px; color:var(--text-secondary); font-size:14px; line-height:1.7;"><?= sanitize($order['special_request']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Proof Photo -->
        <?php if ($order['proof_photo']): ?>
        <div class="od-divider"></div>
        <div class="od-section">
            <span class="od-label">BUKTI / TESTIMONI</span>
            <div style="margin-top:8px; text-align:center;">
                <img src="<?= sanitize($order['proof_photo']) ?>" alt="Bukti"
                     class="od-proof-thumb"
                     onclick="openLightbox(this.src)">
                <p style="font-size:11px; color:var(--text-muted); margin-top:6px;">
                    <i class='bx bx-check-circle' style="color:var(--success);"></i> Klik foto untuk memperbesar
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Payment + Worker + Actions -->
    <div>
        <!-- Payment Card -->
        <div class="od-panel" style="margin-bottom:16px;">
            <div class="od-panel-header">
                <h3><i class='bx bx-credit-card'></i> Pembayaran</h3>
                <?= paymentLabel($order['payment_status']) ?>
            </div>
            <div class="od-finance-grid">
                <div>
                    <span class="od-label">TOTAL HARGA</span>
                    <span class="od-finance-value"><?= formatRupiah($order['price']) ?></span>
                </div>
                <div>
                    <span class="od-label">KOMISI WORKER</span>
                    <span class="od-finance-value" style="color:var(--danger);">-<?= formatRupiah($order['worker_commission']) ?></span>
                </div>
                <div>
                    <span class="od-label">PROFIT BERSIH</span>
                    <span class="od-finance-value" style="color:var(--success); font-weight:800;"><?= formatRupiah($profit) ?></span>
                </div>
            </div>
        </div>

        <!-- Worker Card -->
        <div class="od-panel" style="margin-bottom:16px;">
            <div class="od-panel-header">
                <h3><i class='bx bx-user'></i> Worker</h3>
            </div>
            <?php if ($order['worker_name']): ?>
            <div style="display:flex; align-items:center; gap:14px; padding:4px 0;">
                <div style="width:46px; height:46px; background:linear-gradient(135deg, var(--mocha), var(--taupe)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:white; flex-shrink:0;">
                    <?= strtoupper(substr($order['worker_name'], 0, 1)) ?>
                </div>
                <div style="flex:1;">
                    <strong style="font-size:14px;"><?= sanitize($order['worker_name']) ?></strong>
                    <div style="font-size:12px; color:var(--text-muted);"><?= sanitize($order['worker_rank'] ?? '-') ?></div>
                    <?php if ($order['worker_phone']): ?>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                        <i class='bx bx-phone' style="font-size:12px;"></i> <?= sanitize($order['worker_phone']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="index.php?page=worker_detail&id=<?= $order['worker_id'] ?>" class="btn btn-sm btn-outline">Profil</a>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:20px;">
                <i class='bx bx-user-x'></i>
                <p>Belum ada worker</p>
                <?php if (!$isCompleted): ?>
                <a href="index.php?page=order_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-primary" style="margin-top:8px;">
                    <i class='bx bx-user-plus'></i> Tugaskan
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <?php
        $nextStatuses = [
            'unassigned' => ['in_progress' => 'Mulai Kerjakan'],
            'in_progress' => ['pending_verification' => 'Minta Verifikasi'],
            'pending_verification' => ['completed' => 'Selesaikan Pesanan', 'in_progress' => 'Kembali ke Progress'],
            'completed' => []
        ];
        $actions = $nextStatuses[$order['status']] ?? [];
        ?>

        <?php if (!empty($actions)): ?>
        <div class="od-panel">
            <div class="od-panel-header">
                <h3><i class='bx bx-bolt'></i> Aksi Cepat</h3>
            </div>
            <?php foreach ($actions as $status => $label): ?>
                <?php if ($status === 'completed'): ?>
                <form method="POST" enctype="multipart/form-data" style="margin-bottom:8px;" id="completeForm">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="new_status" value="completed">
                    <div class="proof-upload-section" style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">
                            <i class='bx bx-camera' style="color:var(--success);"></i> Upload Bukti *
                        </label>
                        <input type="file" name="proof_photo" id="proofPhotoInput" accept="image/*" capture="environment" style="display:none;" required>
                        <div class="proof-drop-zone" id="proofDropZone">
                            <div id="proofPlaceholder">
                                <i class='bx bx-cloud-upload pdz-icon'></i>
                                <p class="pdz-text">Seret, tempel (Ctrl+V), atau klik</p>
                                <p class="pdz-hint">JPG, PNG, WEBP, GIF</p>
                            </div>
                            <div id="proofPreview" style="display:none;">
                                <img id="proofPreviewImg" src="" alt="Preview" class="pdz-preview">
                                <span class="pdz-change">📷 Ganti gambar</span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;" onclick="return validateProofPhoto()">
                        <i class='bx bx-check-circle'></i> Selesaikan Pesanan
                    </button>
                </form>
                <?php else: ?>
                <form method="POST" style="margin-bottom:8px;">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="new_status" value="<?= $status ?>">
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <i class='bx bx-right-arrow-alt'></i> <?= $label ?>
                    </button>
                </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($isCompleted): ?>
        <div class="od-panel">
            <p style="text-align:center; color:var(--success); font-size:14px; padding:10px;">
                <i class='bx bx-check-circle'></i> Pesanan ini sudah selesai
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Timeline Log -->
<div class="od-panel" style="margin-top:20px;">
    <div class="od-panel-header">
        <h3><i class='bx bx-history'></i> Log Progres</h3>
    </div>
    <?php if (!$isCompleted): ?>
    <form method="POST" style="margin-bottom:16px;">
        <div style="display:flex; gap:10px;">
            <input type="text" name="log_message" class="form-control" placeholder="Tambah catatan progres..." required style="flex:1;">
            <button type="submit" name="add_log" class="btn btn-primary btn-sm">
                <i class='bx bx-plus'></i> Tambah
            </button>
        </div>
    </form>
    <?php endif; ?>
    <?php if (empty($logs)): ?>
        <div class="empty-state" style="padding:30px;">
            <i class='bx bx-note'></i>
            <p>Belum ada catatan progres.</p>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($logs as $log): ?>
            <div class="timeline-item">
                <div class="tl-time"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
                <div class="tl-message"><?= sanitize($log['message']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<div id="lightboxOverlay" class="lightbox-overlay" onclick="closeLightbox()">
    <img id="lightboxImg" src="" alt="Preview">
    <span class="lightbox-close">&times;</span>
</div>

<script>
// Lightbox
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightboxOverlay').classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

// Proof upload: drag-drop, paste, click
const proofInput = document.getElementById('proofPhotoInput');
const dropZone = document.getElementById('proofDropZone');
const proofPlaceholder = document.getElementById('proofPlaceholder');
const proofPreview = document.getElementById('proofPreview');
const proofPreviewImg = document.getElementById('proofPreviewImg');

function showImagePreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        proofPreviewImg.src = e.target.result;
        proofPlaceholder.style.display = 'none';
        proofPreview.style.display = 'block';
        dropZone.classList.add('has-image');
    };
    reader.readAsDataURL(file);
}

function setFileInput(file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    proofInput.files = dt.files;
    showImagePreview(file);
}

if (dropZone && proofInput) {
    dropZone.addEventListener('click', function() { proofInput.click(); });
    proofInput.addEventListener('change', function() {
        if (this.files && this.files[0]) showImagePreview(this.files[0]);
    });
    dropZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault(); this.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) setFileInput(e.dataTransfer.files[0]);
    });
    document.addEventListener('paste', function(e) {
        const items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.startsWith('image/')) {
                e.preventDefault();
                const blob = items[i].getAsFile();
                const ext = blob.type.split('/')[1] || 'png';
                const file = new File([blob], 'screenshot_' + Date.now() + '.' + ext, { type: blob.type });
                setFileInput(file);
                break;
            }
        }
    });
}

function validateProofPhoto() {
    if (!proofInput || !proofInput.files || proofInput.files.length === 0) {
        alert('Wajib upload foto bukti/testimoni!');
        return false;
    }
    return confirm('Yakin pesanan sudah selesai?');
}
</script>

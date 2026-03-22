<?php
/**
 * Order Detail
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
            // Non-completed status update (no photo required)
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

// Joki type label
$jokiTypeLabels = [
    'joki_gendong' => 'Joki Gendong',
    'joki_login' => 'Joki Login',
];
$jokiTypeDisplay = $jokiTypeLabels[$order['joki_type'] ?? ''] ?? null;
?>

<div class="page-header">
    <div>
        <h2>Pesanan #<?= $order['id'] ?></h2>
        <p>Dibuat: <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>
    </div>
    <div class="btn-group">
        <a href="index.php?page=order_form&id=<?= $order['id'] ?>" class="btn btn-outline">
            <i class='bx bx-edit'></i> Edit
        </a>
        <a href="index.php?page=orders" class="btn btn-outline">
            <i class='bx bx-arrow-back'></i> Kembali
        </a>
    </div>
</div>

<div class="detail-grid">
    <!-- Left: Details -->
    <div>
        <!-- Order Info -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class='bx bx-info-circle' style="color:var(--primary-light)"></i> Informasi Pesanan</h3>
                <?= statusLabel($order['status']) ?>
            </div>

            <div class="detail-row">
                <span class="label">Pelanggan</span>
                <span class="value">
                    <a href="index.php?page=customer_detail&id=<?= $order['customer_id'] ?>" style="color:var(--primary-light);">
                        <?= sanitize($order['customer_name']) ?>
                    </a>
                </span>
            </div>
            <div class="detail-row">
                <span class="label">ID Game</span>
                <span class="value"><?= sanitize($order['customer_game_id'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Rank</span>
                <span class="value"><?= sanitize($order['rank_from']) ?> → <?= sanitize($order['rank_to']) ?></span>
            </div>
            <?php if ($jokiTypeDisplay): ?>
            <div class="detail-row">
                <span class="label">Tipe Joki</span>
                <span class="value">
                    <span class="badge <?= $order['joki_type'] === 'joki_gendong' ? 'badge-info' : 'badge-warning' ?>">
                        <i class='bx <?= $order['joki_type'] === 'joki_gendong' ? 'bx-group' : 'bx-log-in' ?>' style="margin-right:4px;"></i>
                        <?= $jokiTypeDisplay ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($order['request_hero']): ?>
            <div class="detail-row">
                <span class="label">Hero</span>
                <span class="value"><?= sanitize($order['request_hero']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($order['request_role']): ?>
            <div class="detail-row">
                <span class="label">Role</span>
                <span class="value"><?= sanitize($order['request_role']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($order['special_request']): ?>
            <div class="detail-row">
                <span class="label">Request Khusus</span>
                <span class="value" style="max-width:300px; text-align:right;"><?= sanitize($order['special_request']) ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="label">Deadline</span>
                <span class="value"><?= $order['deadline'] ? date('d M Y', strtotime($order['deadline'])) : '-' ?></span>
            </div>
        </div>

        <!-- Proof Photo (if completed) -->
        <?php if ($order['proof_photo']): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class='bx bx-camera' style="color:var(--success)"></i> Bukti / Testimoni</h3>
            </div>
            <div style="text-align:center;">
                <img src="<?= sanitize($order['proof_photo']) ?>" alt="Bukti Testimoni"
                     style="max-width:100%; max-height:400px; border-radius:var(--radius-sm); border:1px solid var(--border); cursor:pointer;"
                     onclick="window.open(this.src, '_blank')">
                <p style="font-size:12px; color:var(--text-muted); margin-top:8px;">
                    <i class='bx bx-check-circle' style="color:var(--success);"></i>
                    Foto bukti telah diupload
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Financial -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class='bx bx-money' style="color:var(--warning)"></i> Keuangan</h3>
                <?= paymentLabel($order['payment_status']) ?>
            </div>
            <div class="detail-row">
                <span class="label">Total Harga</span>
                <span class="value" style="color:var(--success); font-size:18px;"><?= formatRupiah($order['price']) ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Komisi Worker</span>
                <span class="value" style="color:var(--warning);"><?= formatRupiah($order['worker_commission']) ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Profit Bersih</span>
                <span class="value" style="color:var(--accent); font-size:18px;"><?= formatRupiah($profit) ?></span>
            </div>
        </div>

        <!-- Progress Log -->
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bx-history' style="color:var(--accent)"></i> Log Progres</h3>
            </div>

            <!-- Add log form -->
            <form method="POST" style="margin-bottom:20px;">
                <div style="display:flex; gap:10px;">
                    <input type="text" name="log_message" class="form-control" placeholder="Tambah catatan progres..." required style="flex:1;">
                    <button type="submit" name="add_log" class="btn btn-primary btn-sm">
                        <i class='bx bx-plus'></i> Tambah
                    </button>
                </div>
            </form>

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
    </div>

    <!-- Right: Worker + Actions -->
    <div>
        <!-- Worker Info -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class='bx bx-user' style="color:var(--info)"></i> Worker</h3>
            </div>
            <?php if ($order['worker_name']): ?>
                <div style="text-align:center; padding:10px 0;">
                    <div style="width:56px; height:56px; background:linear-gradient(135deg, var(--primary), var(--accent)); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:white; margin-bottom:12px;">
                        <?= strtoupper(substr($order['worker_name'], 0, 1)) ?>
                    </div>
                    <h3 style="font-size:16px; margin-bottom:4px;"><?= sanitize($order['worker_name']) ?></h3>
                    <p style="font-size:13px; color:var(--text-muted);"><?= sanitize($order['worker_rank'] ?? '-') ?></p>
                    <?php if ($order['worker_phone']): ?>
                    <p style="font-size:12px; color:var(--text-muted); margin-top:6px;">
                        <i class='bx bx-phone'></i> <?= sanitize($order['worker_phone']) ?>
                    </p>
                    <?php endif; ?>
                    <a href="index.php?page=worker_detail&id=<?= $order['worker_id'] ?>" class="btn btn-sm btn-outline" style="margin-top:12px;">
                        Lihat Profil
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:20px;">
                    <i class='bx bx-user-x'></i>
                    <p>Belum ada worker ditugaskan</p>
                    <a href="index.php?page=order_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-primary" style="margin-top:10px;">
                        <i class='bx bx-user-plus'></i> Tugaskan Worker
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class='bx bx-bolt' style="color:var(--warning)"></i> Aksi Cepat</h3>
            </div>

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
                <?php foreach ($actions as $status => $label): ?>
                    <?php if ($status === 'completed'): ?>
                    <!-- Complete order requires photo proof -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom:8px;" id="completeForm">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="new_status" value="completed">

                        <div class="proof-upload-section" style="margin-bottom:12px;">
                            <label style="display:block; font-size:13px; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">
                                <i class='bx bx-camera' style="color:var(--success);"></i> Upload Bukti / Testimoni *
                            </label>
                            <input type="file" name="proof_photo" id="proofPhotoInput" accept="image/*" capture="environment"
                                   style="display:none;" required>
                            <div class="proof-drop-zone" id="proofDropZone">
                                <div id="proofPlaceholder">
                                    <i class='bx bx-cloud-upload pdz-icon'></i>
                                    <p class="pdz-text">Seret gambar ke sini, tempel (Ctrl+V), atau klik untuk pilih</p>
                                    <p class="pdz-hint">Format: JPG, PNG, WEBP, GIF — Wajib untuk menyelesaikan pesanan</p>
                                </div>
                                <div id="proofPreview" style="display:none;">
                                    <img id="proofPreviewImg" src="" alt="Preview" class="pdz-preview">
                                    <span class="pdz-change">📷 Klik untuk ganti gambar</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success" style="width:100%;"
                                onclick="return validateProofPhoto()">
                            <i class='bx bx-check-circle'></i> Selesaikan Pesanan
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="margin-bottom:8px;">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="new_status" value="<?= $status ?>">
                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class='bx bx-right-arrow-alt'></i>
                            <?= $label ?>
                        </button>
                    </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; color:var(--success); font-size:14px; padding:10px;">
                    <i class='bx bx-check-circle'></i> Pesanan ini sudah selesai
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
    // Click to open file picker
    dropZone.addEventListener('click', function() {
        proofInput.click();
    });

    proofInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            showImagePreview(this.files[0]);
        }
    });

    // Drag and drop
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            setFileInput(e.dataTransfer.files[0]);
        }
    });

    // Clipboard paste (Ctrl+V)
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
        alert('Wajib upload foto bukti/testimoni untuk menyelesaikan pesanan!\nAnda bisa drag & drop, paste screenshot (Ctrl+V), atau klik area upload.');
        return false;
    }
    return confirm('Yakin pesanan sudah selesai? Pastikan foto bukti sudah benar.');
}
</script>

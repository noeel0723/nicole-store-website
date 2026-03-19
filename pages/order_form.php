<?php
/**
 * Order Form - Add / Edit
 */
$db = getDB();
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$order = null;

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $order = $stmt->fetch();
    if (!$order) {
        flash('error', 'Pesanan tidak ditemukan.');
        redirect('index.php?page=orders');
    }
}

// Get workers for dropdown
$workers = $db->query("SELECT id, name, rank_info FROM workers WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get customers for autocomplete
$customers = $db->query("SELECT id, name, phone, game_id FROM customers ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)$_POST['customer_id'];
    $workerId = !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null;
    $rankFrom = trim($_POST['rank_from']);
    $rankTo = trim($_POST['rank_to']);
    $requestHero = trim($_POST['request_hero'] ?? '');
    $requestRole = trim($_POST['request_role'] ?? '');
    $specialRequest = trim($_POST['special_request'] ?? '');
    $price = (float)str_replace(['.', ','], ['', '.'], $_POST['price']);
    $workerCommission = (float)str_replace(['.', ','], ['', '.'], $_POST['worker_commission']);
    $paymentStatus = $_POST['payment_status'];
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    // Determine status
    $status = $workerId ? 'in_progress' : 'unassigned';
    if ($isEdit) {
        $status = $_POST['status'] ?? $order['status'];
    }

    // New customer inline
    if (empty($customerId) && !empty($_POST['new_customer_name'])) {
        $stmt = $db->prepare("INSERT INTO customers (name, phone, game_id) VALUES (?, ?, ?)");
        $stmt->execute([
            trim($_POST['new_customer_name']),
            trim($_POST['new_customer_phone'] ?? ''),
            trim($_POST['new_customer_game_id'] ?? '')
        ]);
        $customerId = $db->lastInsertId();
    }

    if (empty($customerId)) {
        flash('error', 'Pelanggan harus dipilih atau dibuat baru.');
        redirect('index.php?page=order_form' . ($isEdit ? '&id=' . $order['id'] : ''));
    }

    if ($isEdit) {
        $stmt = $db->prepare("UPDATE orders SET customer_id=?, worker_id=?, rank_from=?, rank_to=?, request_hero=?, request_role=?, special_request=?, price=?, worker_commission=?, payment_status=?, status=?, deadline=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$customerId, $workerId, $rankFrom, $rankTo, $requestHero, $requestRole, $specialRequest, $price, $workerCommission, $paymentStatus, $status, $deadline, $order['id']]);
        flash('success', 'Pesanan berhasil diperbarui.');
    } else {
        $stmt = $db->prepare("INSERT INTO orders (customer_id, worker_id, rank_from, rank_to, request_hero, request_role, special_request, price, worker_commission, payment_status, status, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customerId, $workerId, $rankFrom, $rankTo, $requestHero, $requestRole, $specialRequest, $price, $workerCommission, $paymentStatus, $status, $deadline]);
        flash('success', 'Pesanan berhasil ditambahkan!');
    }

    redirect('index.php?page=orders');
}
?>

<div class="page-header">
    <div>
        <h2><?= $isEdit ? 'Edit Pesanan #' . $order['id'] : 'Tambah Pesanan Baru' ?></h2>
        <p>Isi detail pesanan joki</p>
    </div>
    <a href="index.php?page=orders" class="btn btn-outline">
        <i class='bx bx-arrow-back'></i> Kembali
    </a>
</div>

<div class="card">
    <form method="POST" autocomplete="off">

        <!-- Customer Selection -->
        <h3 style="margin-bottom:16px; font-size:15px; color:var(--primary-light);">
            <i class='bx bx-user'></i> Data Pelanggan
        </h3>

        <div class="form-group autocomplete-wrapper">
            <label>Pilih Pelanggan</label>
            <input type="text" id="customerSearch" class="form-control"
                   placeholder="Ketik nama pelanggan untuk mencari..."
                   value="<?= $isEdit ? sanitize($db->query("SELECT name FROM customers WHERE id = " . $order['customer_id'])->fetchColumn()) : '' ?>">
            <input type="hidden" name="customer_id" id="customerId" value="<?= $isEdit ? $order['customer_id'] : '' ?>">
            <div class="autocomplete-list" id="customerList">
                <?php foreach ($customers as $c): ?>
                <div class="autocomplete-item" data-id="<?= $c['id'] ?>" data-name="<?= sanitize($c['name']) ?>"
                     data-phone="<?= sanitize($c['phone']) ?>" data-gameid="<?= sanitize($c['game_id']) ?>">
                    <?= sanitize($c['name']) ?>
                    <small>ID Game: <?= sanitize($c['game_id'] ?? '-') ?> · <?= sanitize($c['phone'] ?? '-') ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="newCustomerFields" style="display:none; padding:16px; background:var(--bg-input); border-radius:var(--radius-sm); margin-bottom:20px; border:1px solid var(--border);">
            <p style="font-size:13px; color:var(--accent); margin-bottom:12px; font-weight:600;">
                <i class='bx bx-plus-circle'></i> Pelanggan Baru
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Pelanggan</label>
                    <input type="text" name="new_customer_name" class="form-control" placeholder="Nama pelanggan">
                </div>
                <div class="form-group">
                    <label>No. HP / WhatsApp</label>
                    <input type="text" name="new_customer_phone" class="form-control" placeholder="08xxx">
                </div>
            </div>
            <div class="form-group">
                <label>ID Game</label>
                <input type="text" name="new_customer_game_id" class="form-control" placeholder="ID Game MLBB">
            </div>
        </div>

        <!-- Order Details -->
        <h3 style="margin:24px 0 16px; font-size:15px; color:var(--primary-light);">
            <i class='bx bx-trophy'></i> Detail Pesanan
        </h3>

        <div class="form-row">
            <div class="form-group">
                <label>Rank Awal</label>
                <input type="text" name="rank_from" class="form-control" required list="mlbbRanks"
                       placeholder="Ketik rank awal"
                       value="<?= $isEdit ? sanitize($order['rank_from']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Rank Tujuan</label>
                <input type="text" name="rank_to" class="form-control" required list="mlbbRanks"
                       placeholder="Ketik rank tujuan"
                       value="<?= $isEdit ? sanitize($order['rank_to']) : '' ?>">
            </div>
        </div>

        <datalist id="mlbbRanks">
            <?php foreach (MLBB_RANKS as $rank): ?>
            <option value="<?= $rank ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="form-row">
            <div class="form-group">
                <label>Request Hero</label>
                <input type="text" name="request_hero" class="form-control" placeholder="Hero yang dipakai (opsional)"
                       value="<?= $isEdit ? sanitize($order['request_hero']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Request Role</label>
                <select name="request_role" class="form-control">
                    <option value="">Bebas</option>
                    <?php foreach (MLBB_ROLES as $role): ?>
                    <option value="<?= $role ?>" <?= ($isEdit && $order['request_role'] === $role) ? 'selected' : '' ?>><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Request Khusus</label>
            <textarea name="special_request" class="form-control" placeholder="Catatan khusus dari pelanggan (opsional)"><?= $isEdit ? sanitize($order['special_request']) : '' ?></textarea>
        </div>

        <!-- Financial -->
        <h3 style="margin:24px 0 16px; font-size:15px; color:var(--primary-light);">
            <i class='bx bx-money'></i> Keuangan & Status
        </h3>

        <div class="form-row">
            <div class="form-group">
                <label>Total Harga (Rp)</label>
                <input type="text" name="price" class="form-control" placeholder="Contoh: 150000" required
                       value="<?= $isEdit ? $order['price'] : '' ?>">
            </div>
            <div class="form-group">
                <label>Komisi Worker (Rp)</label>
                <input type="text" name="worker_commission" class="form-control" placeholder="Contoh: 75000"
                       value="<?= $isEdit ? $order['worker_commission'] : '' ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Status Pembayaran</label>
                <select name="payment_status" class="form-control" required>
                    <option value="unpaid" <?= ($isEdit && $order['payment_status'] === 'unpaid') ? 'selected' : '' ?>>Belum Bayar</option>
                    <option value="dp" <?= ($isEdit && $order['payment_status'] === 'dp') ? 'selected' : '' ?>>DP</option>
                    <option value="paid" <?= ($isEdit && $order['payment_status'] === 'paid') ? 'selected' : '' ?>>Lunas</option>
                </select>
            </div>
            <div class="form-group">
                <label>Deadline</label>
                <input type="date" name="deadline" class="form-control"
                       value="<?= $isEdit ? $order['deadline'] : '' ?>">
            </div>
        </div>

        <!-- Worker Assignment -->
        <h3 style="margin:24px 0 16px; font-size:15px; color:var(--primary-light);">
            <i class='bx bx-group'></i> Penugasan Worker
        </h3>

        <div class="form-row">
            <div class="form-group">
                <label>Pilih Worker</label>
                <select name="worker_id" class="form-control">
                    <option value="">Belum ditugaskan</option>
                    <?php foreach ($workers as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= ($isEdit && $order['worker_id'] == $w['id']) ? 'selected' : '' ?>>
                        <?= sanitize($w['name']) ?> (<?= sanitize($w['rank_info'] ?? '-') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($isEdit): ?>
            <div class="form-group">
                <label>Status Pesanan</label>
                <select name="status" class="form-control">
                    <option value="unassigned" <?= $order['status'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    <option value="in_progress" <?= $order['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="pending_verification" <?= $order['status'] === 'pending_verification' ? 'selected' : '' ?>>Pending Verification</option>
                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class='bx bx-save'></i> <?= $isEdit ? 'Simpan Perubahan' : 'Buat Pesanan' ?>
            </button>
            <a href="index.php?page=orders" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

<script>
// Customer autocomplete
const searchInput = document.getElementById('customerSearch');
const customerList = document.getElementById('customerList');
const customerIdInput = document.getElementById('customerId');
const newCustomerFields = document.getElementById('newCustomerFields');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const items = customerList.querySelectorAll('.autocomplete-item');
        let hasMatch = false;

        items.forEach(item => {
            const name = item.dataset.name.toLowerCase();
            const gameid = (item.dataset.gameid || '').toLowerCase();
            if (name.includes(query) || gameid.includes(query)) {
                item.style.display = 'block';
                hasMatch = true;
            } else {
                item.style.display = 'none';
            }
        });

        customerList.classList.toggle('show', query.length > 0);

        // Show new customer fields if no match
        if (query.length > 0 && !hasMatch) {
            newCustomerFields.style.display = 'block';
            customerIdInput.value = '';
        }
    });

    searchInput.addEventListener('focus', function() {
        if (this.value.length > 0) customerList.classList.add('show');
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !customerList.contains(e.target)) {
            customerList.classList.remove('show');
        }
    });

    customerList.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('click', function() {
            searchInput.value = this.dataset.name;
            customerIdInput.value = this.dataset.id;
            customerList.classList.remove('show');
            newCustomerFields.style.display = 'none';
        });
    });
}
</script>

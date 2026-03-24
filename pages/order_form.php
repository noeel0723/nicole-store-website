<?php
/**
 * Order Form - 2-Column Card Layout
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
    // Block editing completed orders
    if ($order['status'] === 'completed') {
        flash('error', 'Pesanan yang sudah selesai tidak dapat diedit.');
        redirect('index.php?page=order_detail&id=' . $order['id']);
    }
}

// Get workers for dropdown
$workers = $db->query("SELECT id, name, rank_info FROM workers WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get customers for autocomplete
$customers = $db->query("SELECT id, name, phone, game_id FROM customers WHERE is_guest = 0 ORDER BY name")->fetchAll();

$selectedCustomer = null;
if ($isEdit && !empty($order['customer_id'])) {
    $selectedStmt = $db->prepare("SELECT id, name, phone, game_id, is_guest FROM customers WHERE id = ?");
    $selectedStmt->execute([$order['customer_id']]);
    $selectedCustomer = $selectedStmt->fetch();
}

// Rank suggestions
$rankSuggestions = [];
$isMythicAdded = false;
foreach (MLBB_RANKS as $rank) {
    if (preg_match('/^Mythic\s+[IVX]+$/i', $rank)) {
        if (!$isMythicAdded) {
            $rankSuggestions[] = 'Mythic';
            $isMythicAdded = true;
        }
        continue;
    }
    $rankSuggestions[] = $rank;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)$_POST['customer_id'];
    $isAdminSendiri = ($_POST['worker_id'] ?? '') === 'admin';
    $workerId = (!empty($_POST['worker_id']) && !$isAdminSendiri) ? (int)$_POST['worker_id'] : null;
    $rankFrom = trim($_POST['rank_from']);
    $rankTo = trim($_POST['rank_to']);
    $requestHero = trim($_POST['request_hero'] ?? '');
    $requestRole = trim($_POST['request_role'] ?? '');
    $jokiType = trim($_POST['joki_type'] ?? '');
    $loginVia = trim($_POST['login_via'] ?? '');
    $specialRequest = trim($_POST['special_request'] ?? '');
    $price = (float)str_replace(['.', ','], ['', '.'], $_POST['price']);
    $workerCommissionRaw = str_replace(['.', ','], ['', '.'], $_POST['worker_commission'] ?? '');
    $workerCommission = $isAdminSendiri ? 0 : (is_numeric($workerCommissionRaw) ? round((float)$workerCommissionRaw, 2) : round($price * 0.7, 2));
    $paymentStatus = $_POST['payment_status'];
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    // Determine status
    if ($isAdminSendiri) {
        $status = 'in_progress';
    } else {
        $status = $workerId ? 'in_progress' : 'unassigned';
    }
    if ($isEdit) {
        $status = $_POST['status'] ?? $order['status'];
    }

    // New customer inline — allow non-registered customers
    if (empty($customerId) && !empty($_POST['customer_name_input'])) {
        $inputName = trim($_POST['customer_name_input']);
        $inputPhone = trim($_POST['new_customer_phone'] ?? '');
        $inputGameId = trim($_POST['new_customer_game_id'] ?? '');
        $saveCustomer = !empty($_POST['save_customer']) ? 1 : 0;

        // Check if customer already exists by name
        $existingStmt = $db->prepare("SELECT id, is_guest FROM customers WHERE name = ?");
        $existingStmt->execute([$inputName]);
        $existingCustomer = $existingStmt->fetch();

        if ($existingCustomer) {
            $customerId = $existingCustomer['id'];
            // If they check "Simpan" and the previous record was a guest, upgrade it
            if ($saveCustomer && $existingCustomer['is_guest']) {
                $db->prepare("UPDATE customers SET is_guest = 0 WHERE id = ?")->execute([$customerId]);
            }
        } else {
            // Create customer record (is_guest determines if they show in the Pelanggan list)
            $isGuest = $saveCustomer ? 0 : 1;
            $stmt = $db->prepare("INSERT INTO customers (name, phone, game_id, is_guest) VALUES (?, ?, ?, ?)");
            $stmt->execute([$inputName, $inputPhone, $inputGameId, $isGuest]);
            $customerId = $db->lastInsertId();
        }
    }

    // Fallback for old-style customer_id
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
        $stmt = $db->prepare("UPDATE orders SET customer_id=?, worker_id=?, rank_from=?, rank_to=?, request_hero=?, request_role=?, joki_type=?, special_request=?, price=?, worker_commission=?, payment_status=?, status=?, deadline=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$customerId, $workerId, $rankFrom, $rankTo, $requestHero, $requestRole, $jokiType ?: null, $specialRequest, $price, $workerCommission, $paymentStatus, $status, $deadline, $order['id']]);
        flash('success', 'Pesanan berhasil diperbarui.');
    } else {
        $stmt = $db->prepare("INSERT INTO orders (customer_id, worker_id, rank_from, rank_to, request_hero, request_role, joki_type, special_request, price, worker_commission, payment_status, status, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customerId, $workerId, $rankFrom, $rankTo, $requestHero, $requestRole, $jokiType ?: null, $specialRequest, $price, $workerCommission, $paymentStatus, $status, $deadline]);
        flash('success', 'Pesanan berhasil ditambahkan!');
    }

    redirect('index.php?page=orders');
}
?>

<!-- Header -->
<div class="page-header">
    <div>
        <h2><?= $isEdit ? 'Edit Pesanan' : 'Buat Pesanan Baru' ?></h2>
        <p><?= $isEdit ? 'Perbarui detail pesanan joki untuk memastikan akurasi data' : 'Isi detail pesanan joki baru' ?></p>
    </div>
    <div class="btn-group">
        <a href="index.php?page=orders" class="btn btn-outline">
            <i class='bx bx-arrow-back'></i> Kembali
        </a>
        <button type="submit" form="orderForm" class="btn btn-primary">
            <i class='bx bx-save'></i> <?= $isEdit ? 'Simpan Perubahan' : 'Buat Pesanan' ?>
        </button>
    </div>
</div>

<form method="POST" autocomplete="off" id="orderForm">

<!-- 2-Column Grid -->
<div class="of-grid">
    <!-- LEFT: Customer Data -->
    <div>
        <div class="of-panel">
            <div class="of-panel-title">
                <i class='bx bx-user'></i> Data Pelanggan
            </div>

            <div class="form-group">
                <label>NAMA CUSTOMER</label>
                <input type="text" id="customerSearch" name="customer_name_input" class="form-control"
                       placeholder="Ketik nama pelanggan..."
                       value="<?= ($isEdit && $selectedCustomer) ? sanitize($selectedCustomer['name']) : '' ?>">
                <input type="hidden" name="customer_id" id="customerId" value="<?= $isEdit ? $order['customer_id'] : '' ?>">
                <div class="autocomplete-list" id="customerList">
                    <?php foreach ($customers as $c): ?>
                    <div class="autocomplete-item" data-id="<?= $c['id'] ?>" data-name="<?= sanitize($c['name']) ?>"
                         data-phone="<?= sanitize($c['phone']) ?>" data-gameid="<?= sanitize($c['game_id']) ?>">
                        <?= sanitize($c['name']) ?>
                        <small><span class="badge badge-success" style="padding:2px 6px; font-size:10px; margin-right:6px;">Terdaftar</span>ID: <?= sanitize($c['game_id'] ?? '-') ?> · <?= sanitize(formatCustomerContact($c['phone'] ?? '')) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="registeredCustomerHint" style="display:<?= ($selectedCustomer && !(int)$selectedCustomer['is_guest']) ? 'block' : 'none' ?>; margin-top:8px; padding:8px 10px; border-radius:8px; border:1px solid rgba(91,140,90,0.3); background:rgba(91,140,90,0.08); font-size:12px; color:var(--success);">
                    <strong style="display:block; margin-bottom:2px;">Pelanggan terdaftar terpilih</strong>
                    <span id="registeredCustomerMeta">
                        <?= ($selectedCustomer && !(int)$selectedCustomer['is_guest']) ? sanitize($selectedCustomer['name'] . ' · ID: ' . ($selectedCustomer['game_id'] ?: '-')) : '' ?>
                    </span>
                </div>
                <small style="color:var(--text-muted); font-size:11px;">Pilih nama dari daftar untuk menggunakan pelanggan terdaftar. Jika ketik nama baru, sistem akan membuat data baru.</small>
            </div>

            <div id="newCustomerFields" style="display:none; padding:14px; background:var(--bg-input); border-radius:var(--radius-sm); margin-bottom:16px; border:1px solid var(--border);">
                <p style="font-size:12px; color:var(--primary); margin-bottom:10px; font-weight:600;">
                    <i class='bx bx-plus-circle'></i> Info Pelanggan Baru
                </p>
                <div class="form-group">
                    <label>No. HP / WhatsApp</label>
                    <input type="text" name="new_customer_phone" class="form-control" placeholder="08xxx">
                </div>
                <div class="form-group">
                    <label>ID Game</label>
                    <input type="text" name="new_customer_game_id" class="form-control" placeholder="ID Game MLBB">
                </div>
                <div class="form-group" style="grid-column: 1 / -1; margin-bottom:0;">
                    <label style="font-size:13px; display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="save_customer" value="1" checked>
                        <span>Simpan sebagai pelanggan terdaftar</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>METODE PEMBAYARAN</label>
                <select name="payment_status" class="form-control" required>
                    <option value="unpaid" <?= ($isEdit && $order['payment_status'] === 'unpaid') ? 'selected' : '' ?>>Belum Bayar</option>
                    <option value="dp" <?= ($isEdit && $order['payment_status'] === 'dp') ? 'selected' : '' ?>>DP</option>
                    <option value="paid" <?= ($isEdit && $order['payment_status'] === 'paid') ? 'selected' : '' ?>>Lunas</option>
                </select>
            </div>
        </div>

        <!-- Metadata (Edit only) -->
        <?php if ($isEdit): ?>
        <div class="of-metadata">
            <div class="of-metadata-title">Metadata Pesanan</div>
            <div class="of-metadata-row">
                <span>Order ID</span>
                <span>#<?= $order['id'] ?></span>
            </div>
            <div class="of-metadata-row">
                <span>Dibuat</span>
                <span><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="of-metadata-row">
                <span>Status</span>
                <span><?= statusLabel($order['status']) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Order Details -->
    <div class="of-panel">
        <div class="of-panel-title">
            <i class='bx bx-trophy'></i> Detail Pesanan
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>RANK AWAL</label>
                <input type="text" name="rank_from" class="form-control" required list="mlbbRanks"
                       placeholder="Ketik rank awal"
                       value="<?= $isEdit ? sanitize($order['rank_from']) : '' ?>">
            </div>
            <div class="form-group">
                <label>RANK TUJUAN</label>
                <input type="text" name="rank_to" class="form-control" required list="mlbbRanks"
                       placeholder="Ketik rank tujuan"
                       value="<?= $isEdit ? sanitize($order['rank_to']) : '' ?>">
            </div>
        </div>

        <datalist id="mlbbRanks">
            <?php foreach ($rankSuggestions as $rank): ?>
            <option value="<?= $rank ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="form-row">
            <div class="form-group">
                <label>TIPE JOKI</label>
                <select name="joki_type" class="form-control">
                    <option value="">Pilih Tipe Joki</option>
                    <option value="joki_gendong" <?= ($isEdit && $order['joki_type'] === 'joki_gendong') ? 'selected' : '' ?>>Joki Gendong</option>
                    <option value="joki_login" <?= ($isEdit && $order['joki_type'] === 'joki_login') ? 'selected' : '' ?>>Joki Login</option>
                </select>
            </div>
            <div class="form-group">
                <label>REQUEST ROLE</label>
                <select name="request_role" class="form-control">
                    <option value="">Bebas</option>
                    <?php foreach (MLBB_ROLES as $role): ?>
                    <option value="<?= $role ?>" <?= ($isEdit && $order['request_role'] === $role) ? 'selected' : '' ?>><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>REQUEST HERO</label>
            <input type="text" name="request_hero" class="form-control" placeholder="Hero yang dipakai (opsional)"
                   value="<?= $isEdit ? sanitize($order['request_hero']) : '' ?>">
        </div>

        <div class="form-group">
            <label>CATATAN TAMBAHAN</label>
            <textarea name="special_request" class="form-control" placeholder="Catatan khusus dari pelanggan (opsional)" rows="3"><?= $isEdit ? sanitize($order['special_request']) : '' ?></textarea>
        </div>

        <div class="form-row" style="margin-top:16px;">
            <div class="form-group">
                <label>TOTAL HARGA (Rp)</label>
                <input type="text" name="price" id="orderPrice" class="form-control" placeholder="150000" required
                       value="<?= $isEdit ? $order['price'] : '' ?>">
            </div>
            <div class="form-group">
                <label>KOMISI WORKER (70%)</label>
                <input type="text" name="worker_commission" id="workerCommission" class="form-control" placeholder="Otomatis 70%"
                       value="<?= $isEdit ? $order['worker_commission'] : '' ?>">
                <small style="color:var(--text-muted); font-size:11px;">Otomatis 70%. Bisa diubah manual.</small>
            </div>
        </div>

        <div class="form-group">
            <label>DEADLINE</label>
            <input type="date" name="deadline" class="form-control"
                   value="<?= $isEdit ? $order['deadline'] : '' ?>">
        </div>
    </div>
</div>

<!-- Worker Assignment Bar -->
<div class="of-worker-bar">
    <div class="of-worker-icon">
        <i class='bx bx-group'></i>
    </div>
    <div class="of-worker-info">
        <strong>Assign Worker</strong>
        <small>Pilih tenaga ahli untuk pengerjaan pesanan ini</small>
    </div>
    <div style="flex:1; max-width:280px;">
        <select name="worker_id" id="workerSelect" class="form-control">
            <option value="">Belum ditugaskan</option>
            <option value="admin" <?= ($isEdit && !$order['worker_id'] && $order['worker_commission'] == 0 && $order['status'] !== 'unassigned') ? 'selected' : '' ?>>👤 Admin Sendiri</option>
            <?php foreach ($workers as $w): ?>
            <option value="<?= $w['id'] ?>" <?= ($isEdit && $order['worker_id'] == $w['id']) ? 'selected' : '' ?>>
                <?= sanitize($w['name']) ?> (<?= sanitize($w['rank_info'] ?? '-') ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($isEdit): ?>
    <div style="max-width:180px;">
        <select name="status" class="form-control">
            <option value="unassigned" <?= $order['status'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
            <option value="in_progress" <?= $order['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="pending_verification" <?= $order['status'] === 'pending_verification' ? 'selected' : '' ?>>Pending Verification</option>
        </select>
    </div>
    <?php endif; ?>
</div>

</form>

<script>
// Customer autocomplete
const searchInput = document.getElementById('customerSearch');
const customerList = document.getElementById('customerList');
const customerIdInput = document.getElementById('customerId');
const newCustomerFields = document.getElementById('newCustomerFields');
const registeredHint = document.getElementById('registeredCustomerHint');
const registeredMeta = document.getElementById('registeredCustomerMeta');

function showRegisteredHint(name, gameId) {
    if (!registeredHint || !registeredMeta) return;
    registeredMeta.textContent = `${name} · ID: ${gameId || '-'}`;
    registeredHint.style.display = 'block';
}

function hideRegisteredHint() {
    if (!registeredHint) return;
    registeredHint.style.display = 'none';
}

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
            hideRegisteredHint();
        } else if (query.length === 0) {
            newCustomerFields.style.display = 'none';
            customerIdInput.value = '';
            hideRegisteredHint();
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
            showRegisteredHint(this.dataset.name, this.dataset.gameid);
        });
    });
}

// Auto-calculate worker commission = 70% of total price (editable)
const orderPriceInput = document.getElementById('orderPrice');
const workerCommissionInput = document.getElementById('workerCommission');
const workerSelect = document.getElementById('workerSelect');
let commissionManuallyEdited = false;

function parseToNumber(value) {
    if (!value) return 0;
    const cleaned = value.toString().replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
    const num = parseFloat(cleaned);
    return Number.isFinite(num) ? num : 0;
}

function updateCommission() {
    if (!orderPriceInput || !workerCommissionInput) return;
    if (commissionManuallyEdited) return;
    const isAdmin = workerSelect && workerSelect.value === 'admin';
    const price = parseToNumber(orderPriceInput.value);
    if (isAdmin) {
        workerCommissionInput.value = 0;
    } else {
        workerCommissionInput.value = Math.round(price * 0.7);
    }
}

if (orderPriceInput && workerCommissionInput) {
    orderPriceInput.addEventListener('input', function() {
        commissionManuallyEdited = false;
        updateCommission();
    });
    workerCommissionInput.addEventListener('input', function() {
        commissionManuallyEdited = true;
    });
    if (workerSelect) {
        workerSelect.addEventListener('change', function() {
            // Only auto-set commission if user hasn't manually edited it,
            // OR if switching to "Admin Sendiri" (which should always zero out)
            if (this.value === 'admin') {
                commissionManuallyEdited = false;
            }
            updateCommission();
        });
    }
    if (!workerCommissionInput.value) {
        updateCommission();
    }
}
</script>

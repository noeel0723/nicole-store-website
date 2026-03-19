<?php
/**
 * Customer Form - Add / Edit
 */
$db = getDB();
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$customer = null;

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $customer = $stmt->fetch();
    if (!$customer) {
        flash('error', 'Pelanggan tidak ditemukan.');
        redirect('index.php?page=customers');
    }
}

$phoneValue = '';
$socialValue = '';
if ($isEdit) {
    $parsedContact = parseCustomerContact($customer['phone'] ?? '');
    $phoneValue = $parsedContact['phone'];
    $socialValue = $parsedContact['social'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phoneValue = trim($_POST['phone_value'] ?? '');
    $socialValue = trim($_POST['social_value'] ?? '');
    $phone = buildCustomerContact($phoneValue, $socialValue);
    $gameId = trim($_POST['game_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name)) {
        flash('error', 'Nama pelanggan harus diisi.');
        redirect('index.php?page=customer_form' . ($isEdit ? '&id=' . $customer['id'] : ''));
    }

    if ($isEdit) {
        $stmt = $db->prepare("UPDATE customers SET name=?, phone=?, game_id=?, notes=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $phone, $gameId, $notes, $customer['id']]);
        flash('success', 'Data pelanggan berhasil diperbarui.');
    } else {
        $stmt = $db->prepare("INSERT INTO customers (name, phone, game_id, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $gameId, $notes]);
        flash('success', 'Pelanggan berhasil ditambahkan!');
    }
    redirect('index.php?page=customers');
}
?>

<div class="page-header">
    <div>
        <h2><?= $isEdit ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru' ?></h2>
    </div>
    <a href="index.php?page=customers" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Kembali</a>
</div>

<div class="card" style="max-width:640px;">
    <form method="POST">
        <div class="form-group">
            <label>Nama Pelanggan *</label>
            <input type="text" name="name" class="form-control" placeholder="Nama lengkap pelanggan" required
                   value="<?= $isEdit ? sanitize($customer['name']) : '' ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>No. HP / WhatsApp</label>
                <input type="text" name="phone_value" class="form-control" placeholder="08xxx"
                       value="<?= sanitize($phoneValue) ?>">
            </div>
            <div class="form-group">
                <label>Username Social Media</label>
                <input type="text" name="social_value" class="form-control" placeholder="@username / link profile"
                       value="<?= sanitize($socialValue) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>ID Game MLBB</label>
                <input type="text" name="game_id" class="form-control" placeholder="12345678 (1234)"
                       value="<?= $isEdit ? sanitize($customer['game_id']) : '' ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Catatan</label>
            <textarea name="notes" class="form-control" placeholder="Catatan tambahan..."><?= $isEdit ? sanitize($customer['notes']) : '' ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> <?= $isEdit ? 'Simpan' : 'Tambah Pelanggan' ?></button>
            <a href="index.php?page=customers" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

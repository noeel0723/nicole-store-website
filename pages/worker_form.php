<?php
/**
 * Worker Form - Add / Edit
 */
$db = getDB();
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$worker = null;

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM workers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $worker = $stmt->fetch();
    if (!$worker) {
        flash('error', 'Worker tidak ditemukan.');
        redirect('index.php?page=workers');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $rankInfo = trim($_POST['rank_info'] ?? '');
    $roles = trim($_POST['roles'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name)) {
        flash('error', 'Nama worker harus diisi.');
        redirect('index.php?page=worker_form' . ($isEdit ? '&id=' . $worker['id'] : ''));
    }

    if ($isEdit) {
        $stmt = $db->prepare("UPDATE workers SET name=?, phone=?, specialization=?, rank_info=?, roles=?, is_active=?, notes=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $phone, $specialization, $rankInfo, $roles, $isActive, $notes, $worker['id']]);
        flash('success', 'Data worker berhasil diperbarui.');
    } else {
        $stmt = $db->prepare("INSERT INTO workers (name, phone, specialization, rank_info, roles, is_active, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $specialization, $rankInfo, $roles, $isActive, $notes]);
        flash('success', 'Worker berhasil ditambahkan!');
    }
    redirect('index.php?page=workers');
}
?>

<div class="page-header">
    <div>
        <h2><?= $isEdit ? 'Edit Worker' : 'Tambah Worker Baru' ?></h2>
    </div>
    <a href="index.php?page=workers" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Kembali</a>
</div>

<div class="card" style="max-width:640px;">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Nama Worker *</label>
                <input type="text" name="name" class="form-control" placeholder="Nama worker" required
                       value="<?= $isEdit ? sanitize($worker['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>No. HP / WhatsApp</label>
                <input type="text" name="phone" class="form-control" placeholder="08xxx"
                       value="<?= $isEdit ? sanitize($worker['phone']) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Rank / Level Tertinggi</label>
            <select name="rank_info" class="form-control">
                <option value="">Pilih rank tertinggi</option>
                <?php foreach (MLBB_RANKS as $rank): ?>
                <option value="<?= $rank ?>" <?= ($isEdit && $worker['rank_info'] === $rank) ? 'selected' : '' ?>><?= $rank ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Spesialisasi</label>
            <input type="text" name="specialization" class="form-control"
                   placeholder="Contoh: Ex-Immortal 200++, Paham Makro & Mikro"
                   value="<?= $isEdit ? sanitize($worker['specialization']) : '' ?>">
        </div>

        <div class="form-group">
            <label>Role yang Dikuasai</label>
            <input type="text" name="roles" class="form-control"
                   placeholder="Contoh: Tank, Fighter, Assassin"
                   value="<?= $isEdit ? sanitize($worker['roles']) : '' ?>">
        </div>

        <div class="form-group">
            <label>Catatan</label>
            <textarea name="notes" class="form-control" placeholder="Catatan tambahan tentang worker..."><?= $isEdit ? sanitize($worker['notes']) : '' ?></textarea>
        </div>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" name="is_active" value="1"
                       <?= ($isEdit ? ($worker['is_active'] ? 'checked' : '') : 'checked') ?>
                       style="width:18px; height:18px; accent-color:var(--primary);">
                Worker Aktif
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> <?= $isEdit ? 'Simpan' : 'Tambah Worker' ?></button>
            <a href="index.php?page=workers" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

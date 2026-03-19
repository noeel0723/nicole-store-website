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

$mainRoles = ['', '', ''];
$roleHeroes = ['', '', ''];
$socialMedia = '';

if ($isEdit) {
    $socialMedia = trim((string)($worker['notes'] ?? ''));

    $savedRoles = array_values(array_filter(array_map('trim', explode(',', (string)($worker['roles'] ?? '')))));
    for ($i = 0; $i < 3; $i++) {
        $mainRoles[$i] = $savedRoles[$i] ?? '';
    }

    $specLines = preg_split('/\r\n|\r|\n/', (string)($worker['specialization'] ?? ''));
    $idx = 0;
    foreach ($specLines as $line) {
        if ($idx >= 3) {
            break;
        }
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (strpos($line, ':') !== false) {
            [$roleLabel, $heroLabel] = array_map('trim', explode(':', $line, 2));
            if ($mainRoles[$idx] === '') {
                $mainRoles[$idx] = $roleLabel;
            }
            $roleHeroes[$idx] = $heroLabel;
        } else {
            $roleHeroes[$idx] = $line;
        }
        $idx++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $paymentAccount = trim($_POST['payment_account'] ?? '');
    $socialMedia = trim($_POST['social_media'] ?? '');
    $rankInfo = trim($_POST['rank_info'] ?? '');
    $mainRoles = [
        trim($_POST['main_role_1'] ?? ''),
        trim($_POST['main_role_2'] ?? ''),
        trim($_POST['main_role_3'] ?? ''),
    ];
    $roleHeroes = [
        trim($_POST['hero_role_1'] ?? ''),
        trim($_POST['hero_role_2'] ?? ''),
        trim($_POST['hero_role_3'] ?? ''),
    ];

    $roles = implode(', ', array_values(array_filter($mainRoles)));

    $specializationLines = [];
    for ($i = 0; $i < 3; $i++) {
        if ($mainRoles[$i] === '' && $roleHeroes[$i] === '') {
            continue;
        }
        $roleLabel = $mainRoles[$i] !== '' ? $mainRoles[$i] : 'Role ' . ($i + 1);
        $heroLabel = $roleHeroes[$i] !== '' ? $roleHeroes[$i] : '-';
        $specializationLines[] = $roleLabel . ': ' . $heroLabel;
    }
    $specialization = implode(PHP_EOL, $specializationLines);

    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes = $socialMedia;

    if (empty($name)) {
        flash('error', 'Nama worker harus diisi.');
        redirect('index.php?page=worker_form' . ($isEdit ? '&id=' . $worker['id'] : ''));
    }

    if ($isEdit) {
        $stmt = $db->prepare("UPDATE workers SET name=?, phone=?, specialization=?, rank_info=?, roles=?, is_active=?, notes=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $paymentAccount, $specialization, $rankInfo, $roles, $isActive, $notes, $worker['id']]);
        flash('success', 'Data worker berhasil diperbarui.');
    } else {
        $stmt = $db->prepare("INSERT INTO workers (name, phone, specialization, rank_info, roles, is_active, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $paymentAccount, $specialization, $rankInfo, $roles, $isActive, $notes]);
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
                <label>Nama Lengkap *</label>
                <input type="text" name="name" class="form-control" placeholder="Nama lengkap worker" required
                       value="<?= $isEdit ? sanitize($worker['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Nomor Rekening / E-Wallet</label>
                <input type="text" name="payment_account" class="form-control" placeholder="BCA 123xxxx / DANA 08xxx"
                       value="<?= $isEdit ? sanitize($worker['phone']) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Akun Media Social</label>
            <input type="text" name="social_media" class="form-control" placeholder="@username / link profile"
                   value="<?= sanitize($socialMedia) ?>">
        </div>

        <div class="form-group">
            <label>Highest Rank</label>
            <input type="text" name="rank_info" class="form-control" placeholder="Contoh: Mythical Glory 150"
                   value="<?= $isEdit ? sanitize($worker['rank_info']) : '' ?>">
        </div>

        <h3 style="margin:24px 0 16px; font-size:15px; color:var(--primary-light);">
            <i class='bx bx-shield-quarter'></i> 3 Role Utama & Hero Andalan
        </h3>

        <div class="form-row">
            <div class="form-group">
                <label>Role Utama 1</label>
                <input type="text" name="main_role_1" class="form-control" placeholder="Contoh: Jungler"
                       value="<?= sanitize($mainRoles[0]) ?>">
            </div>
            <div class="form-group">
                <label>3 Hero Andalan (Role 1)</label>
                <input type="text" name="hero_role_1" class="form-control" placeholder="Fanny, Ling, Lancelot"
                       value="<?= sanitize($roleHeroes[0]) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Role Utama 2</label>
                <input type="text" name="main_role_2" class="form-control" placeholder="Contoh: Gold Lane"
                       value="<?= sanitize($mainRoles[1]) ?>">
            </div>
            <div class="form-group">
                <label>3 Hero Andalan (Role 2)</label>
                <input type="text" name="hero_role_2" class="form-control" placeholder="Claude, Brody, Beatrix"
                       value="<?= sanitize($roleHeroes[1]) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Role Utama 3</label>
                <input type="text" name="main_role_3" class="form-control" placeholder="Contoh: Roamer"
                       value="<?= sanitize($mainRoles[2]) ?>">
            </div>
            <div class="form-group">
                <label>3 Hero Andalan (Role 3)</label>
                <input type="text" name="hero_role_3" class="form-control" placeholder="Tigreal, Khufra, Mathilda"
                       value="<?= sanitize($roleHeroes[2]) ?>">
            </div>
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

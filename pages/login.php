<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth.php';

// Already logged in?
if (isLoggedIn()) {
    redirect('index.php?page=dashboard');
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi.';
    } elseif (loginAdmin($username, $password)) {
        redirect('index.php?page=dashboard');
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-header">
            <div class="login-icon">A</div>
            <h1><?= APP_NAME ?></h1>
            <p>Management System — Login</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class='bx bx-error-circle'></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=login">
            <div class="form-group">
                <label for="username"><i class='bx bx-user'></i> Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       placeholder="Masukkan username" value="<?= sanitize($_POST['username'] ?? '') ?>" autofocus required>
            </div>
            <div class="form-group">
                <label for="password"><i class='bx bx-lock-alt'></i> Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class='bx bx-log-in'></i> Masuk
            </button>
        </form>
    </div>
</div>
</body>
</html>

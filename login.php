<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if (attemptLogin($user, $pass)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= APP_NAME ?> — Login</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-button { display: none; width: 0; height: 0; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 9999px; border: 2px solid transparent; background-clip: content-box; }
        ::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
        body { background: #f1f5f9; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 380px; background: #fff;
                      border-radius: 12px; padding: 2.5rem;
                      box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .brand-logo-img { width: 58px; height: 58px; border-radius: 14px;
                          box-shadow: 0 6px 18px rgba(37,99,235,.25); margin-bottom: 1rem; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 .2rem rgba(37,99,235,.15); }
        .btn-login { background: #2563eb; border: none; width: 100%;
                     padding: .7rem; font-weight: 600; }
        .btn-login:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center">
        <img src="assets/img/logo.svg" alt="<?= APP_NAME ?> Logo" class="brand-logo-img">
    </div>
    <h5 class="text-center fw-bold mb-1"><?= APP_NAME ?></h5>
    <p class="text-center text-muted small mb-4">FreeRADIUS Management System</p>

    <?php if ($timeout): ?>
    <div class="alert alert-warning py-2 small">Session expired. Please login again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" placeholder="admin / administrator" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" class="btn btn-login btn-primary text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>
    <p class="text-center text-muted mt-3" style="font-size:.75rem">
        Supports config admin & FreeRADIUS operator accounts
    </p>
</div>
</body>
</html>

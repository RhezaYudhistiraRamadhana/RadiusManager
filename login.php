<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: welcome.php');
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$lockout = getLoginAttemptLockout($ip);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($lockout['locked']) {
        $error = "Too many failed login attempts. Access temporarily locked. Please wait {$lockout['remaining_min']} minute(s) before trying again.";
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');

        if ($user === '' || $pass === '') {
            $error = 'Please enter both username and password.';
        } elseif (attemptLogin($user, $pass)) {
            clearLoginAttempts($ip);
            session_write_close();
            header('Location: welcome.php');
            exit;
        } else {
            $totalAttempts = recordLoginFailure($ip, $user);
            if ($totalAttempts >= 5) {
                $lockout = getLoginAttemptLockout($ip);
                $error = "Too many failed attempts. Your IP has been temporarily locked for {$lockout['remaining_min']} minute(s).";
            } else {
                $remaining = 5 - $totalAttempts;
                $error = "Invalid username or password. ($remaining attempt(s) remaining before temporary lockout)";
            }
        }
    }
}
$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= APP_NAME ?> — Login</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg?v=cendana_tree_<?= APP_VERSION ?>">
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
                          box-shadow: 0 6px 20px rgba(16,185,129,.28), 0 3px 10px rgba(37,99,235,.22); margin-bottom: 1rem; }
        .brand-logo-img img,
        .brand-logo-img svg { width: 100%; height: 100%; display: block; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 .2rem rgba(37,99,235,.15); }
        .btn-login { background: #2563eb; border: none; width: 100%;
                     padding: .7rem; font-weight: 600; }
        .btn-login:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center">
        <div class="brand-logo-img d-inline-flex align-items-center justify-content-center overflow-hidden">
            <?php
            $loginLogo = __DIR__ . '/assets/img/logo.svg';
            if (file_exists($loginLogo)) {
                readfile($loginLogo);
            } else {
                echo '<img src="assets/img/logo.svg?v=tree_' . APP_VERSION . '" alt="' . APP_NAME . ' Logo">';
            }
            ?>
        </div>
    </div>
    <h4 class="text-center fw-bold mb-1"><?= APP_NAME ?></h4>
    <p class="text-center text-secondary small mb-1" style="font-size: .78rem; font-weight: 500;"><?= defined('APP_FULL_NAME') ? APP_FULL_NAME : 'Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi' ?></p>
    <p class="text-center text-muted mb-4" style="font-size: .7rem; letter-spacing: .06em; text-transform: uppercase;">FreeRADIUS Management &amp; Access Control</p>

    <?php if ($timeout): ?>
    <div class="alert alert-warning py-2 small">Session expired. Please login again.</div>
    <?php endif; ?>
    <?php if ($lockout['locked']): ?>
    <div class="alert alert-danger py-2.5 small d-flex align-items-center gap-2">
        <i class="bi bi-shield-slash-fill fs-5 text-danger"></i>
        <div>
            <div class="fw-bold">Security Lockout Active</div>
            <div>Too many failed attempts. Try again in <strong><?= $lockout['remaining_min'] ?> minute(s)</strong>.</div>
        </div>
    </div>
    <?php elseif ($error): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" placeholder="admin / administrator" required autofocus <?= $lockout['locked'] ? 'disabled' : '' ?>>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required <?= $lockout['locked'] ? 'disabled' : '' ?>>
            </div>
        </div>
        <button type="submit" id="btnSubmit" class="btn btn-login btn-primary text-white" <?= $lockout['locked'] ? 'disabled' : '' ?>>
            <span id="btnText"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
            <span id="btnSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing In...</span>
        </button>
    </form>
    <script>
    document.getElementById('loginForm')?.addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        var btnText = document.getElementById('btnText');
        var btnSpinner = document.getElementById('btnSpinner');
        if (btn && btnText && btnSpinner) {
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
        }
    });
    </script>
    <p class="text-center text-muted mt-3" style="font-size:.75rem">
        Supports config admin & FreeRADIUS operator accounts
    </p>
</div>
</body>
</html>

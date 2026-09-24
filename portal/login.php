<?php
require_once __DIR__ . '/auth.php';

if (isPortalLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $res = portalLogin($username, $password);
        if ($res['success']) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = $res['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriber Portal - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 1rem;
        }
        .portal-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }
        .portal-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        .portal-icon-wrap {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .portal-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 8px;
            padding: .65rem .85rem;
        }
        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            border-radius: 8px;
            padding: .65rem 1rem;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="portal-card">
    <div class="portal-header">
        <div class="portal-icon-wrap">
            <i class="bi bi-wifi text-white fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1">Subscriber Portal</h4>
        <p class="text-white-50 small mb-0">Check bandwidth usage, session history & manage account</p>
    </div>

    <div class="portal-body">
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
        <div class="alert alert-info alert-dismissible fade show small" role="alert">
            <i class="bi bi-info-circle me-1"></i> You have been logged out successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Username or Voucher Code</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="username" class="form-control" required autofocus
                           placeholder="Enter your RADIUS username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-key text-muted"></i></span>
                    <input type="password" name="password" id="passInput" class="form-control" required
                           placeholder="Enter your password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePass()">
                        <i class="bi bi-eye" id="passEye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 shadow-sm mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
            </button>
        </form>

        <div class="text-center pt-2 border-top">
            <small class="text-muted">Need help with your login? Contact network support.</small>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const input = document.getElementById('passInput');
    const eye = document.getElementById('passEye');
    if (input.type === 'password') {
        input.type = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        eye.className = 'bi bi-eye';
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

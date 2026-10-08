<?php
require_once __DIR__ . '/auth.php';
requirePortalLogin();

$username = getPortalUser();
$db = getDB();

$flashSuccess = $_SESSION['portal_flash_success'] ?? '';
$flashError   = $_SESSION['portal_flash_error'] ?? '';
unset($_SESSION['portal_flash_success'], $_SESSION['portal_flash_error']);

// Fetch User Profile Info & Registered Email
$profile = [];
if (dbTableExists('userinfo')) {
    $uStmt = $db->prepare("SELECT firstname, lastname, department, email FROM userinfo WHERE username = ?");
    $uStmt->execute([$username]);
    $profile = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
$displayName = !empty($profile['firstname']) ? trim($profile['firstname'] . ' ' . ($profile['lastname'] ?? '')) : $username;
$userEmail   = trim($profile['email'] ?? '');
$hasEmail    = !empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL);

// ── Handle OTP Request ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_otp') {
    checkCsrf();
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

    if (!$hasEmail) {
        $msg = 'No registered email address found for this account. Please contact IT support to register your email.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['portal_flash_error'] = $msg;
        header('Location: dashboard.php');
        exit;
    }

    $lastSent = $_SESSION['portal_otp']['last_sent'] ?? 0;
    if ((time() - $lastSent) < 60) {
        $waitSec = 60 - (time() - $lastSent);
        $msg = "Please wait $waitSec seconds before requesting another OTP code.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['portal_flash_error'] = $msg;
        header('Location: dashboard.php');
        exit;
    }

    $otpCode = sprintf('%06d', random_int(100000, 999999));
    $_SESSION['portal_otp'] = [
        'code'       => $otpCode,
        'email'      => $userEmail,
        'created_at' => time(),
        'expires_at' => time() + 600, // 10 minutes
        'attempts'   => 0,
        'last_sent'  => time(),
    ];

    $mailRes = sendOtpEmail($userEmail, $displayName, $otpCode);
    $masked  = maskEmail($userEmail);

    $isLocalDev = (defined('DEV_MODE') && DEV_MODE) || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $devNote = ($isLocalDev && empty($mailRes['success'])) ? " [Local Test OTP: $otpCode]" : '';

    $msg = "OTP verification code sent to your registered email ($masked).$devNote Please check your inbox or spam folder.";

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'masked'  => $masked,
            'dev_otp' => $isLocalDev ? $otpCode : null
        ]);
        exit;
    }

    $_SESSION['portal_flash_success'] = $msg;
    header('Location: dashboard.php');
    exit;
}

// ── Handle Password Change with OTP ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    checkCsrf();

    $otpInput = trim($_POST['otp_code'] ?? '');
    $currPass = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_password'] ?? '';

    if (!$hasEmail) {
        $flashError = 'Cannot change password: Your account does not have a registered email address. Please contact IT support.';
    } elseif ($otpInput === '' || $currPass === '' || $newPass === '' || $confPass === '') {
        $flashError = 'All fields, including the OTP verification code, are required.';
    } elseif ($newPass !== $confPass) {
        $flashError = 'New password and confirmation do not match.';
    } elseif (strlen($newPass) < 6) {
        $flashError = 'New password must be at least 6 characters long.';
    } else {
        $otpData = $_SESSION['portal_otp'] ?? null;

        if (empty($otpData) || empty($otpData['code'])) {
            $flashError = 'Please request an OTP verification code sent to your registered email first.';
        } elseif (time() > ($otpData['expires_at'] ?? 0)) {
            $flashError = 'Your OTP verification code has expired. Please request a new code.';
            unset($_SESSION['portal_otp']);
        } elseif (($otpData['attempts'] ?? 0) >= 5) {
            $flashError = 'Too many failed attempts. Please request a new OTP code.';
            unset($_SESSION['portal_otp']);
        } elseif (!hash_equals((string)$otpData['code'], $otpInput)) {
            $_SESSION['portal_otp']['attempts'] = ($otpData['attempts'] ?? 0) + 1;
            $remaining = 5 - $_SESSION['portal_otp']['attempts'];
            $flashError = "Invalid OTP code entered. Please check your email and try again ($remaining attempts remaining).";
        } else {
            // Verify current password
            $chkStmt = $db->prepare("SELECT value FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password'");
            $chkStmt->execute([$username]);
            $storedPass = $chkStmt->fetchColumn();

            if ($storedPass === false || !hash_equals((string)$storedPass, $currPass)) {
                $flashError = 'Your current password was entered incorrectly.';
            } else {
                $db->beginTransaction();
                try {
                    // Update radcheck
                    $updStmt = $db->prepare("UPDATE radcheck SET value = ? WHERE username = ? AND attribute = 'Cleartext-Password'");
                    $updStmt->execute([$newPass, $username]);

                    // If user has a voucher record, update rm_vouchers too
                    if (dbTableExists('rm_vouchers')) {
                        $vUpd = $db->prepare("UPDATE rm_vouchers SET password = ? WHERE username = ?");
                        $vUpd->execute([$newPass, $username]);
                    }

                    $db->commit();

                    unset($_SESSION['portal_otp']);

                    auditLog('portal_password_change_otp', $username, 'Password updated via subscriber portal with verified email OTP (' . maskEmail($userEmail) . ')');
                    $_SESSION['portal_flash_success'] = 'Your password has been updated successfully!';
                    header('Location: dashboard.php');
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $flashError = 'Database error updating password: ' . $e->getMessage();
                }
            }
        }
    }
}

// 1. Fetch User Attributes
$attrsStmt = $db->prepare("SELECT attribute, value FROM radcheck WHERE username = ?");
$attrsStmt->execute([$username]);
$attrs = $attrsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$expiration = $attrs['Expiration'] ?? null;
$isDisabled = isset($attrs['Auth-Type']) && strcasecmp($attrs['Auth-Type'], 'Reject') === 0;

// 2. Fetch Group & Rate Plan
$groupStmt = $db->prepare("SELECT groupname FROM radusergroup WHERE username = ? ORDER BY priority ASC LIMIT 1");
$groupStmt->execute([$username]);
$groupname = $groupStmt->fetchColumn() ?: 'Default';

$plan = null;
if (dbTableExists('rm_plans')) {
    $planStmt = $db->prepare("SELECT * FROM rm_plans WHERE groupname = ? LIMIT 1");
    $planStmt->execute([$groupname]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
}

// 3. User Profile Info (Already resolved at top)
$otpSent = !empty($_SESSION['portal_otp']['code']) && (time() <= ($_SESSION['portal_otp']['expires_at'] ?? 0));

// 4. Monthly Usage Stats
$startMonth = date('Y-m-01 00:00:00');
$endMonth   = date('Y-m-t 23:59:59');

$usageStmt = $db->prepare("
    SELECT
        COUNT(*) AS session_count,
        COALESCE(SUM(acctinputoctets), 0) AS upload_bytes,
        COALESCE(SUM(acctoutputoctets), 0) AS download_bytes,
        COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_bytes,
        COALESCE(SUM(acctsessiontime), 0) AS total_time
    FROM radacct
    WHERE username = ? AND acctstarttime >= ? AND acctstarttime <= ?
");
$usageStmt->execute([$username, $startMonth, $endMonth]);
$monthlyUsage = $usageStmt->fetch(PDO::FETCH_ASSOC);

// 5. Active Session (if currently online)
$activeSessionStmt = $db->prepare("
    SELECT ra.*, COALESCE(n.shortname, ra.nasipaddress) AS nas_label
    FROM radacct ra
    LEFT JOIN nas n ON n.nasname = ra.nasipaddress
    WHERE ra.username = ? AND ra.acctstoptime IS NULL
    ORDER BY ra.radacctid DESC LIMIT 1
");
$activeSessionStmt->execute([$username]);
$activeSession = $activeSessionStmt->fetch(PDO::FETCH_ASSOC);

// 6. Recent Sessions (last 5)
$recentStmt = $db->prepare("
    SELECT ra.*, COALESCE(n.shortname, ra.nasipaddress) AS nas_label
    FROM radacct ra
    LEFT JOIN nas n ON n.nasname = ra.nasipaddress
    WHERE ra.username = ?
    ORDER BY ra.radacctid DESC LIMIT 5
");
$recentStmt->execute([$username]);
$recentSessions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Data Quota calculation if plan has data_mb
$quotaMb = (int)($plan['data_mb'] ?? 0);
$usedMb  = round(($monthlyUsage['total_bytes'] ?? 0) / (1024 * 1024), 2);
$quotaPercent = ($quotaMb > 0) ? min(100, round(($usedMb / $quotaMb) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($displayName) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .portal-nav {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: .85rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .user-hero {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-card-p {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            height: 100%;
        }
        .stat-icon-p {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: .85rem;
        }
        .table th {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="portal-nav d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <div class="bg-primary text-white rounded p-1 d-flex align-items-center justify-content-center" style="width:32px;height:32px">
            <i class="bi bi-wifi"></i>
        </div>
        <span class="fw-bold fs-5 text-dark"><?= APP_NAME ?> <span class="text-primary font-monospace fw-normal" style="font-size:.85rem">Subscriber</span></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="d-none d-md-inline small text-muted">
            <i class="bi bi-person-circle me-1 text-secondary"></i> <?= htmlspecialchars($displayName) ?>
        </span>
        <a href="logout.php" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-box-arrow-right me-1"></i> Sign Out
        </a>
    </div>
</nav>

<div class="container py-4 flex-grow-1" style="max-width: 1050px;">

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- User Hero Banner -->
    <div class="user-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <h3 class="fw-bold mb-0"><?= htmlspecialchars($displayName) ?></h3>
                <?php if ($isDisabled): ?>
                    <span class="badge bg-danger">Deactivated</span>
                <?php elseif ($activeSession): ?>
                    <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>Online Now</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Offline</span>
                <?php endif; ?>
            </div>
            <div class="text-white-50 small font-monospace">Username: <?= htmlspecialchars($username) ?></div>
            <?php if (!empty($profile['department'])): ?>
                <div class="text-white-50 small"><?= htmlspecialchars($profile['department']) ?></div>
            <?php endif; ?>
        </div>
        <div class="text-md-end">
            <div class="small text-white-50 mb-1">Assigned Plan & Group</div>
            <div class="fw-bold fs-5 text-light"><?= htmlspecialchars($plan['name'] ?? $groupname) ?></div>
            <?php if (!empty($expiration)): ?>
                <div class="small text-warning mt-1"><i class="bi bi-clock me-1"></i>Expires: <?= htmlspecialchars($expiration) ?></div>
            <?php else: ?>
                <div class="small text-success mt-1"><i class="bi bi-infinity me-1"></i>No Expiration</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Session Alert if Connected -->
    <?php if ($activeSession): ?>
    <div class="card border-0 bg-success-subtle text-success-emphasis p-3 mb-4 shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <i class="bi bi-activity me-1 fs-5"></i>
                <strong>Currently Connected</strong> via <strong><?= htmlspecialchars($activeSession['nas_label']) ?></strong>
                (IP: <code><?= htmlspecialchars($activeSession['framedipaddress'] ?: 'DHCP') ?></code>)
            </div>
            <div class="small">
                Connected since: <?= date('d M Y, H:i', strtotime($activeSession['acctstarttime'])) ?>
                &bull; Current Session Data: <?= formatBytes($activeSession['acctinputoctets'] + $activeSession['acctoutputoctets']) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Usage Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card-p">
                <div class="stat-icon-p bg-primary-subtle text-primary">
                    <i class="bi bi-cloud-arrow-down"></i>
                </div>
                <div class="text-muted small">Download This Month</div>
                <div class="fw-bold fs-4"><?= formatBytes($monthlyUsage['download_bytes']) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-p">
                <div class="stat-icon-p bg-info-subtle text-info">
                    <i class="bi bi-cloud-arrow-up"></i>
                </div>
                <div class="text-muted small">Upload This Month</div>
                <div class="fw-bold fs-4"><?= formatBytes($monthlyUsage['upload_bytes']) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-p">
                <div class="stat-icon-p bg-success-subtle text-success">
                    <i class="bi bi-pie-chart"></i>
                </div>
                <div class="text-muted small">Total Data Transfer</div>
                <div class="fw-bold fs-4"><?= formatBytes($monthlyUsage['total_bytes']) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-p">
                <div class="stat-icon-p bg-secondary-subtle text-secondary">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="text-muted small">Total Sessions (Month)</div>
                <div class="fw-bold fs-4"><?= number_format((int)$monthlyUsage['session_count']) ?></div>
            </div>
        </div>
    </div>

    <!-- Data Cap Progress Bar (if quota configured) -->
    <?php if ($quotaMb > 0): ?>
    <div class="card border-0 shadow-sm p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Monthly Data Quota Allowance</h6>
            <span class="small fw-semibold <?= $quotaPercent >= 90 ? 'text-danger' : 'text-primary' ?>">
                <?= $usedMb ?> MB / <?= $quotaMb ?> MB (<?= $quotaPercent ?>%)
            </span>
        </div>
        <div class="progress" style="height: 12px;">
            <div class="progress-bar <?= $quotaPercent >= 90 ? 'bg-danger' : ($quotaPercent >= 75 ? 'bg-warning' : 'bg-primary') ?>"
                 role="progressbar" style="width: <?= $quotaPercent ?>%;"></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Recent Sessions (Left 8 cols) -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-1 text-primary"></i>Recent Connection Sessions</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Start Time</th>
                                <th>Duration</th>
                                <th>Transfer</th>
                                <th>Access Point</th>
                                <th>Assigned IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSessions)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    No connection sessions recorded yet.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentSessions as $s): ?>
                            <tr>
                                <td>
                                    <div class="small fw-semibold"><?= date('d M Y, H:i', strtotime($s['acctstarttime'])) ?></div>
                                </td>
                                <td>
                                    <?php if ($s['acctstoptime'] === null): ?>
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    <?php else: ?>
                                        <span class="small text-muted"><?= formatDuration((int)$s['acctsessiontime']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small"><?= formatBytes($s['acctinputoctets'] + $s['acctoutputoctets']) ?></div>
                                    <div class="text-muted" style="font-size: .7rem">&uarr;<?= formatBytes($s['acctinputoctets']) ?> &bull; &darr;<?= formatBytes($s['acctoutputoctets']) ?></div>
                                </td>
                                <td>
                                    <span class="small"><?= htmlspecialchars($s['nas_label']) ?></span>
                                </td>
                                <td>
                                    <code class="small"><?= htmlspecialchars($s['framedipaddress'] ?: '—') ?></code>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Password Change Card (Right 4 cols) -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0"><i class="bi bi-shield-lock me-1 text-primary"></i>Change Account Password</h6>
                    <?php if (!$hasEmail): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size:.65rem">
                            <i class="bi bi-lock-fill me-1"></i>Locked
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size:.65rem">
                            <i class="bi bi-shield-check me-1"></i>OTP Protected
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3">
                    <?php if (!$hasEmail): ?>
                        <!-- Case A: No Registered Email Address Found -->
                        <div class="alert alert-warning border-warning-subtle p-3 mb-0" role="alert">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                <h6 class="fw-bold mb-0 text-dark">Email Registration Required</h6>
                            </div>
                            <p class="small text-secondary mb-2" style="font-size: .83rem; line-height: 1.45;">
                                To change your account password, you must first verify an <strong>OTP code</strong> sent directly to your registered email address.
                            </p>
                            <div class="p-2.5 bg-white rounded border border-warning-subtle small text-dark mb-3">
                                <i class="bi bi-x-circle-fill text-danger me-1"></i>
                                <strong>No registered email address found</strong> for account <code><?= htmlspecialchars($username) ?></code>.
                            </div>
                            <div class="alert alert-light border small text-muted mb-0 py-2 px-2.5" style="font-size: .8rem;">
                                <i class="bi bi-headset text-primary me-1"></i>
                                Please contact the <strong>IT Support / Network Administrator</strong> to register your official email address before updating your password.
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Case B: Has Registered Email Address with OTP Verification -->
                        <div class="mb-3 p-2.5 bg-light rounded border small">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size:.72rem;">Registered Email:</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem;">Verified</span>
                            </div>
                            <div class="fw-semibold text-dark text-truncate mt-1" title="<?= htmlspecialchars($userEmail) ?>">
                                <i class="bi bi-envelope-check text-primary me-1"></i><?= htmlspecialchars(maskEmail($userEmail)) ?>
                            </div>
                        </div>

                        <form method="POST" action="dashboard.php" id="changePasswordForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-semibold text-secondary mb-0">OTP Verification Code <span class="text-danger">*</span></label>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnRequestOtp" onclick="requestOtpAjax()">
                                        <i class="bi bi-send me-1"></i><span id="btnOtpText"><?= $otpSent ? 'Resend OTP' : 'Send OTP Code' ?></span>
                                    </button>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="bi bi-shield-check text-primary"></i></span>
                                    <input type="text" name="otp_code" id="otpCodeInput" class="form-control"
                                           placeholder="Enter 6-digit code" maxlength="6" pattern="\d{6}"
                                           value="<?= htmlspecialchars($_POST['otp_code'] ?? '') ?>" required>
                                </div>
                                <div id="otpStatusMsg" class="form-text mt-1" style="font-size:.72rem">
                                    <?php if ($otpSent): ?>
                                        <span class="text-success"><i class="bi bi-check-circle me-1"></i>OTP sent to your email. Valid for 10 minutes.</span>
                                    <?php else: ?>
                                        <span class="text-muted">Click <strong>Send OTP Code</strong> to receive your code via email.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control form-control-sm" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control form-control-sm" minlength="6" required>
                                <div class="form-text" style="font-size:.7rem">Minimum 6 characters.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control form-control-sm" minlength="6" required>
                            </div>

                            <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm" id="btnSubmitPassword">
                                <i class="bi bi-check-circle me-1"></i>Verify OTP &amp; Update Password
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<footer class="bg-white border-top py-3 mt-auto">
    <div class="container text-center small text-muted">
        <?= APP_NAME ?> Subscriber Self-Service Portal &bull; FreeRADIUS Integration
    </div>
</footer>

<script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
let otpCooldown = 0;
let otpTimer = null;

function requestOtpAjax() {
    if (otpCooldown > 0) return;

    const btn = document.getElementById('btnRequestOtp');
    const btnText = document.getElementById('btnOtpText');
    const statusMsg = document.getElementById('otpStatusMsg');
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:0.75rem; height:0.75rem;"></span>Sending...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'request_otp');
    formData.append('csrf_token', csrfToken);

    fetch('dashboard.php?ajax=1', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusMsg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' + data.message + '</span>';
            const otpInput = document.getElementById('otpCodeInput');
            if (otpInput) {
                if (data.dev_otp && !otpInput.value) {
                    otpInput.value = data.dev_otp;
                }
                otpInput.focus();
            }
            startCooldown(60);
        } else {
            statusMsg.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i>' + (data.error || 'Failed to send OTP.') + '</span>';
            btn.disabled = false;
            btnText.textContent = 'Retry Send OTP';
        }
    })
    .catch(err => {
        statusMsg.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i>Network error sending OTP. Please try again.</span>';
        btn.disabled = false;
        btnText.textContent = 'Retry Send OTP';
    });
}

function startCooldown(sec) {
    otpCooldown = sec;
    const btn = document.getElementById('btnRequestOtp');
    const btnText = document.getElementById('btnOtpText');
    if (!btn || !btnText) return;
    btn.disabled = true;

    if (otpTimer) clearInterval(otpTimer);
    otpTimer = setInterval(() => {
        otpCooldown--;
        if (otpCooldown <= 0) {
            clearInterval(otpTimer);
            btn.disabled = false;
            btnText.textContent = 'Resend OTP';
        } else {
            btnText.textContent = `Resend in ${otpCooldown}s`;
        }
    }, 1000);
}
</script>
</body>
</html>

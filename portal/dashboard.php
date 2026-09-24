<?php
require_once __DIR__ . '/auth.php';
requirePortalLogin();

$username = getPortalUser();
$db = getDB();

$flashSuccess = $_SESSION['portal_flash_success'] ?? '';
$flashError   = $_SESSION['portal_flash_error'] ?? '';
unset($_SESSION['portal_flash_success'], $_SESSION['portal_flash_error']);

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    checkCsrf();

    $currPass = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_password'] ?? '';

    if ($currPass === '' || $newPass === '' || $confPass === '') {
        $flashError = 'All password fields are required.';
    } elseif ($newPass !== $confPass) {
        $flashError = 'New password and confirmation do not match.';
    } elseif (strlen($newPass) < 6) {
        $flashError = 'New password must be at least 6 characters long.';
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

                auditLog('portal_password_change', $username, 'Password updated via subscriber self-service portal');
                $flashSuccess = 'Your password has been updated successfully!';
            } catch (Exception $e) {
                $db->rollBack();
                $flashError = 'Database error updating password: ' . $e->getMessage();
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

// 3. User Profile Info
$profile = $_SESSION['portal_profile'] ?? [];
if (empty($profile) && dbTableExists('userinfo')) {
    $uStmt = $db->prepare("SELECT firstname, lastname, department, email FROM userinfo WHERE username = ?");
    $uStmt->execute([$username]);
    $profile = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
$displayName = !empty($profile['firstname']) ? trim($profile['firstname'] . ' ' . ($profile['lastname'] ?? '')) : $username;

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-shield-lock me-1 text-primary"></i>Change Account Password</h6>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="dashboard.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Current Password</label>
                            <input type="password" name="current_password" class="form-control form-control-sm" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">New Password</label>
                            <input type="password" name="new_password" class="form-control form-control-sm" minlength="6" required>
                            <div class="form-text" style="font-size:.7rem">Minimum 6 characters.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control form-control-sm" minlength="6" required>
                        </div>

                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            Update Password
                        </button>
                    </form>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

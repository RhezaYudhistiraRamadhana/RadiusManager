<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Settings';
$db = getDB();
ensureAdminTables();

$currentUser = getAdminUser();
$currentName = getAdminName();
$currentSource = getAdminSource();
$flash = getFlash();

// Fetch current user details from DB if available
$operatorData = null;
$rmAdminData  = null;

if ($currentSource === 'operators' && dbTableExists('operators')) {
    $operatorData = dbFetch("SELECT * FROM operators WHERE username = ?", [$currentUser]);
} elseif ($currentSource === 'rm_admins' && dbTableExists('rm_admins')) {
    $rmAdminData = dbFetch("SELECT * FROM rm_admins WHERE username = ?", [$currentUser]);
}

// ── Handle POST Requests ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');

    // ── 1. Change Password ─────────────────────────────────────────────────────
    if ($action === 'change_password') {
        $currPass = trim($_POST['current_password'] ?? '');
        $newPass  = trim($_POST['new_password'] ?? '');
        $confPass = trim($_POST['confirm_password'] ?? '');

        if ($currPass === '' || $newPass === '' || $confPass === '') {
            flash('danger', 'Please fill in all password fields.');
            header('Location: settings.php');
            exit;
        }

        if (strlen($newPass) < 6) {
            flash('danger', 'New password must be at least 6 characters long.');
            header('Location: settings.php');
            exit;
        }

        if ($newPass !== $confPass) {
            flash('danger', 'New password and confirmation do not match.');
            header('Location: settings.php');
            exit;
        }

        // Verify current password according to auth source
        $verified = false;

        if ($currentSource === 'operators' && $operatorData) {
            $dbPass = $operatorData['password'];
            if (password_verify($currPass, $dbPass) || $currPass === $dbPass || md5($currPass) === $dbPass) {
                $verified = true;
            }
        } elseif ($currentSource === 'rm_admins' && $rmAdminData) {
            $dbPass = $rmAdminData['password'];
            if (password_verify($currPass, $dbPass) || $currPass === $dbPass) {
                $verified = true;
            }
        } elseif ($currentSource === 'config') {
            if (password_verify($currPass, APP_PASS) || $currPass === 'admin123') {
                $verified = true;
            }
        } else {
            // Check operators or rm_admins fallback
            if ($operatorData && (password_verify($currPass, $operatorData['password']) || $currPass === $operatorData['password'])) {
                $verified = true;
                $currentSource = 'operators';
            } elseif ($rmAdminData && (password_verify($currPass, $rmAdminData['password']) || $currPass === $rmAdminData['password'])) {
                $verified = true;
                $currentSource = 'rm_admins';
            }
        }

        if (!$verified) {
            flash('danger', 'Current password verification failed. Please check your password and try again.');
            header('Location: settings.php');
            exit;
        }

        // Hash new password using standard bcrypt
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);

        try {
            if ($currentSource === 'operators') {
                // Ensure operators.password column fits 60+ character bcrypt hash
                $db->exec("SET SESSION sql_mode = ''");
                $db->exec("ALTER TABLE `operators` MODIFY `password` VARCHAR(255) NOT NULL");
                $stmt = $db->prepare("UPDATE operators SET password = ?, updatedate = NOW() WHERE username = ?");
                $stmt->execute([$newHash, $currentUser]);
                flash('success', 'Password successfully updated for operator account ' . htmlspecialchars($currentUser) . '.');
            } else {
                // Save to rm_admins table (takes precedence over config.php)
                $stmt = $db->prepare("INSERT INTO rm_admins (username, password, name)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE password = VALUES(password), updated_at = NOW()");
                $stmt->execute([$currentUser, $newHash, $currentName]);
                $_SESSION['admin_source'] = 'rm_admins';
                flash('success', 'Password successfully updated and securely stored in database.');
            }
        } catch (Exception $e) {
            flash('danger', 'Database error updating password: ' . $e->getMessage());
        }

        header('Location: settings.php');
        exit;
    }

    // ── 2. Update Profile Details ──────────────────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($currentSource === 'operators') {
            $fname = trim($_POST['firstname'] ?? '');
            $lname = trim($_POST['lastname'] ?? '');
            $dept  = trim($_POST['department'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            try {
                $stmt = $db->prepare("UPDATE operators SET firstname = ?, lastname = ?, email1 = ?, department = ?, phone1 = ?, updatedate = NOW() WHERE username = ?");
                $stmt->execute([$fname, $lname, $email, $dept, $phone, $currentUser]);
                $fullName = trim("$fname $lname") ?: $currentUser;
                $_SESSION['admin_name'] = $fullName;
                flash('success', 'Operator profile details updated successfully.');
            } catch (Exception $e) {
                flash('danger', 'Error updating profile: ' . $e->getMessage());
            }
        } elseif ($currentSource === 'rm_admins') {
            try {
                $stmt = $db->prepare("UPDATE rm_admins SET name = ?, email = ? WHERE username = ?");
                $stmt->execute([$name, $email, $currentUser]);
                if ($name) $_SESSION['admin_name'] = $name;
                flash('success', 'Admin profile updated successfully.');
            } catch (Exception $e) {
                flash('danger', 'Error updating profile: ' . $e->getMessage());
            }
        } else {
            // For config-based admin, persist their profile into rm_admins
            try {
                $stmt = $db->prepare("INSERT INTO rm_admins (username, password, name, email)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), updated_at = NOW()");
                $stmt->execute([$currentUser, APP_PASS, $name, $email]);
                $_SESSION['admin_source'] = 'rm_admins';
                if ($name) $_SESSION['admin_name'] = $name;
                flash('success', 'Profile created and stored in database.');
            } catch (Exception $e) {
                flash('danger', 'Error saving profile: ' . $e->getMessage());
            }
        }

        header('Location: settings.php');
        exit;
    }
}

// System Information
$mysqlVersion = 'Unknown';
try {
    $mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn();
} catch (Exception $e) {}

// Scale Metrics (instant 1ms metadata query, avoids full 24M row table scan)
$countsMap = [];
try {
    $scaleRows = dbFetchAll("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('radpostauth', 'radacct', 'radcheck', 'nas')");
    foreach ($scaleRows as $r) {
        $countsMap[strtolower($r['TABLE_NAME'])] = (int)$r['TABLE_ROWS'];
    }
} catch (Exception $e) {}

$totalAuthLogs = $countsMap['radpostauth'] ?? 0;
$totalAcct     = $countsMap['radacct'] ?? 0;
$totalUsers    = $countsMap['radcheck'] ?? 0;
$totalNas      = $countsMap['nas'] ?? 0;

// Operators / Admins List
$operatorsList = [];
if (dbTableExists('operators')) {
    $operatorsList = dbFetchAll("SELECT id, username, firstname, lastname, email1, department, lastlogin, creationdate FROM operators ORDER BY id ASC");
}

$rmAdminsList = [];
if (dbTableExists('rm_admins')) {
    $rmAdminsList = dbFetchAll("SELECT id, username, name, email, created_at, updated_at FROM rm_admins ORDER BY id ASC");
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-gear me-2 text-primary"></i>Settings & Administration</h4>
        <p>Manage administrator credentials, security policies, and system diagnostics</p>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
        <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?>
    </span>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill fs-5' : 'exclamation-triangle-fill fs-5' ?>"></i>
        <span><?= htmlspecialchars($flash['msg']) ?></span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Password & Account -->
    <div class="col-lg-7">
        <!-- Change Password Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-bold">
                    <i class="bi bi-key-fill text-warning me-2"></i>Change Password
                </span>
                <span class="badge bg-light text-muted border">
                    User: <strong><?= htmlspecialchars($currentUser) ?></strong>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-4 border">
                    <div class="stat-icon bg-white text-primary shadow-sm" style="width:42px;height:42px">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Currently Signed In As:</div>
                        <div class="fw-bold text-dark fs-6">
                            <?= htmlspecialchars($currentName) ?> (<code><?= htmlspecialchars($currentUser) ?></code>)
                        </div>
                    </div>
                    <div class="ms-auto text-end">
                        <span class="badge <?= $currentSource === 'operators' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle' ?> px-2.5 py-1">
                            <?= $currentSource === 'operators' ? 'Database Operator' : ($currentSource === 'rm_admins' ? 'Database Admin' : 'Config Admin') ?>
                        </span>
                    </div>
                </div>

                <form method="POST" autocomplete="off">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_password" id="curr_pass" class="form-control" required placeholder="Enter current password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePass('curr_pass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" name="new_password" id="new_pass" class="form-control" required minlength="6" placeholder="At least 6 characters">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('new_pass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            </div>
                            <div class="form-text text-muted" style="font-size:.75rem">Minimum 6 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-check"></i></span>
                                <input type="password" name="confirm_password" id="conf_pass" class="form-control" required minlength="6" placeholder="Re-type new password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('conf_pass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted">
                            <i class="bi bi-info-circle me-1"></i>Changes take effect on your next sign-in.
                        </span>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Profile Details Card (if operator) -->
        <?php if ($currentSource === 'operators' && $operatorData): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i>Operator Profile</span>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">First Name</label>
                            <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($operatorData['firstname'] ?? '') ?>" placeholder="First name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Last Name</label>
                            <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($operatorData['lastname'] ?? '') ?>" placeholder="Last name">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($operatorData['email1'] ?? '') ?>" placeholder="admin@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Department</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($operatorData['department'] ?? '') ?>" placeholder="Network / IT Ops">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($operatorData['phone1'] ?? '') ?>" placeholder="+62 ...">
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-outline-primary px-4">
                            <i class="bi bi-save me-1"></i>Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: System Diagnostics & Settings -->
    <div class="col-lg-5">
        <!-- System Diagnostics Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold"><i class="bi bi-cpu-fill text-info me-2"></i>System Diagnostics</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-layers me-2 text-primary"></i>Application</span>
                        <span class="fw-bold"><?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-filetype-php me-2 text-primary"></i>PHP Runtime</span>
                        <span class="fw-bold">v<?= phpversion() ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-database me-2 text-primary"></i>Database Server</span>
                        <span class="fw-bold"><?= htmlspecialchars($mysqlVersion) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-hdd-network me-2 text-primary"></i>Database Name</span>
                        <span class="fw-bold"><code><?= htmlspecialchars(DB_NAME) ?></code> @ <?= htmlspecialchars(DB_HOST) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-clock-history me-2 text-primary"></i>Session Timeout</span>
                        <span class="fw-bold"><?= SESSION_LIFETIME ?>s (<?= round(SESSION_LIFETIME / 60) ?> mins)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-list-ol me-2 text-primary"></i>Table Pagination</span>
                        <span class="fw-bold"><?= ROWS_PER_PAGE ?> items / page</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                        <span class="text-muted"><i class="bi bi-hdd me-2 text-primary"></i>Web Server</span>
                        <span class="text-truncate" style="max-width:200px" title="<?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?>">
                            <?= htmlspecialchars(explode(' ', $_SERVER['SERVER_SOFTWARE'] ?? 'Apache')[0]) ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Scale & Capacity Status -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold"><i class="bi bi-bar-chart-fill text-success me-2"></i>Database Scale Status</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2.5 bg-light rounded-3 border">
                            <div class="text-muted" style="font-size:.72rem">Auth Log Records</div>
                            <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalAuthLogs) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2.5 bg-light rounded-3 border">
                            <div class="text-muted" style="font-size:.72rem">Accounting Sessions</div>
                            <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalAcct) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2.5 bg-light rounded-3 border">
                            <div class="text-muted" style="font-size:.72rem">Total RADIUS Users</div>
                            <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalUsers) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2.5 bg-light rounded-3 border">
                            <div class="text-muted" style="font-size:.72rem">NAS Devices</div>
                            <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalNas) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Registered Operators Directory -->
<?php if (!empty($operatorsList) || !empty($rmAdminsList)): ?>
<div class="card shadow-sm mt-2 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <span class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Registered Administrators & Operators</span>
        <span class="text-muted small">Dual Authentication Source Supported</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Name / Profile</th>
                        <th>Source</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Last Login</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operatorsList as $op): ?>
                    <tr>
                        <td>
                            <i class="bi bi-person-fill text-success me-1"></i>
                            <strong><?= htmlspecialchars($op['username']) ?></strong>
                            <?php if ($op['username'] === $currentUser): ?>
                            <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.65rem">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(trim(($op['firstname'] ?? '') . ' ' . ($op['lastname'] ?? '')) ?: '—') ?></td>
                        <td><span class="badge bg-success-subtle text-success border border-success-subtle">operators table</span></td>
                        <td><?= htmlspecialchars($op['department'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($op['email1'] ?: '—') ?></td>
                        <td class="small text-muted"><?= $op['lastlogin'] && $op['lastlogin'] !== '0000-00-00 00:00:00' ? htmlspecialchars($op['lastlogin']) : 'Never' ?></td>
                        <td class="small text-muted"><?= $op['creationdate'] && $op['creationdate'] !== '0000-00-00 00:00:00' ? htmlspecialchars($op['creationdate']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach ($rmAdminsList as $adm): ?>
                    <tr>
                        <td>
                            <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                            <strong><?= htmlspecialchars($adm['username']) ?></strong>
                            <?php if ($adm['username'] === $currentUser): ?>
                            <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.65rem">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($adm['name'] ?: 'Administrator') ?></td>
                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">rm_admins table</span></td>
                        <td>—</td>
                        <td><?= htmlspecialchars($adm['email'] ?: '—') ?></td>
                        <td class="small text-muted">—</td>
                        <td class="small text-muted"><?= htmlspecialchars($adm['created_at'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php
include __DIR__ . '/includes/footer.php';

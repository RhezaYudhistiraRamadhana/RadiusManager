<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Add User';
$db = getDB();

$errors = [];
$groups = $db->query("SELECT DISTINCT groupname FROM radusergroup UNION SELECT DISTINCT groupname FROM radgroupcheck ORDER BY groupname")->fetchAll(PDO::FETCH_COLUMN);
$hasUserinfo = dbTableExists('userinfo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username    = trim($_POST['username'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $group       = trim($_POST['group'] ?? '');
    $simul_count = (int)($_POST['simul_count'] ?? 0);
    $expiry      = trim($_POST['expiry'] ?? '');
    $static_ip   = trim($_POST['static_ip'] ?? '');
    $fullname    = trim($_POST['fullname'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $email       = trim($_POST['email'] ?? '');

    if (!$username) $errors[] = 'Username is required.';
    if (!$password) $errors[] = 'Password is required.';
    if (preg_match('/\s/', $username)) $errors[] = 'Username cannot contain spaces.';
    if ($static_ip && !filter_var($static_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $errors[] = 'Invalid IPv4 address for Static IP.';
    }

    // Check duplicate
    if (!$errors) {
        $exists = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username=?");
        $exists->execute([$username]);
        if ($exists->fetchColumn() > 0) $errors[] = "Username '$username' already exists.";
    }

    if (!$errors) {
        $db->beginTransaction();
        try {
            // Password
            $db->prepare("INSERT INTO radcheck (username,attribute,op,value) VALUES (?,?,?,?)")
               ->execute([$username, 'Cleartext-Password', ':=', $password]);

            // Simultaneous-Use limit
            if ($simul_count > 0) {
                $db->prepare("INSERT INTO radcheck (username,attribute,op,value) VALUES (?,?,?,?)")
                   ->execute([$username, 'Simultaneous-Use', ':=', (string)$simul_count]);
            }

            // Expiry
            if ($expiry) {
                $db->prepare("INSERT INTO radcheck (username,attribute,op,value) VALUES (?,?,?,?)")
                   ->execute([$username, 'Expiration', ':=', date('d M Y', strtotime($expiry))]);
            }

            // Static IP (Framed-IP-Address in radreply)
            if ($static_ip) {
                $db->prepare("INSERT INTO radreply (username,attribute,op,value) VALUES (?, 'Framed-IP-Address', ':=', ?)")
                   ->execute([$username, $static_ip]);
            }

            // Group assignment
            if ($group) {
                $db->prepare("INSERT INTO radusergroup (username,groupname,priority) VALUES (?,?,0)")
                   ->execute([$username, $group]);
            }

            // userinfo profile
            if ($hasUserinfo && ($fullname || $department || $email)) {
                $db->prepare("INSERT INTO userinfo (username, firstname, lastname, email, department, creationdate, creationby) VALUES (?,?,?,?,?,NOW(),?)")
                   ->execute([$username, $fullname, $department, $email, $department, $_SESSION['admin_user'] ?? 'admin']);
            }

            $db->commit();
            $detail = "Group: " . ($group ?: 'None');
            if ($static_ip) $detail .= ", Static IP: $static_ip";
            auditLog('user.create', $username, $detail);
            flash("User '$username' created successfully.", 'success');
            header('Location: users.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-plus me-2 text-primary"></i>Add User</h4>
        <p>Create a new RADIUS user account</p>
    </div>
    <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle me-1"></i><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row">
<div class="col-lg-7">
<div class="card mb-4">
    <div class="card-header bg-white fw-semibold py-3">Account Details</div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="e.g. 206412005 or john.doe" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="password" class="form-control" id="pwField"
                               value="<?= htmlspecialchars($_POST['password'] ?? '') ?>"
                               placeholder="Enter password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="generatePw()" title="Generate password">
                            <i class="bi bi-shuffle"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($hasUserinfo): ?>
            <div class="mb-3 border-top pt-3">
                <span class="fw-semibold small text-muted text-uppercase mb-2 d-block">Profile Info (Optional)</span>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label small">Full Name</label>
                        <input type="text" name="fullname" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" placeholder="e.g. John Doe">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label small">Department / Class</label>
                        <input type="text" name="department" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($_POST['department'] ?? '') ?>" placeholder="e.g. Teknik Mesin / BPU">
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label small">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="e.g. john@example.com">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="border-top pt-3">
                <span class="fw-semibold small text-muted text-uppercase mb-2 d-block">RADIUS Policies</span>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Group</label>
                        <select name="group" class="form-select">
                            <option value="">— No Group —</option>
                            <?php foreach ($groups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>"
                                <?= (($_POST['group'] ?? '') === $g) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Max Simultaneous Logins</label>
                        <input type="number" name="simul_count" class="form-control"
                               min="0" value="<?= (int)($_POST['simul_count'] ?? 0) ?>"
                               placeholder="0 = unlimited">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Account Expiry Date</label>
                        <input type="date" name="expiry" class="form-control"
                               value="<?= htmlspecialchars($_POST['expiry'] ?? '') ?>">
                        <div class="form-text">Leave blank for no expiry.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Static IP Address <span class="text-muted">(Framed-IP-Address)</span></label>
                        <input type="text" name="static_ip" class="form-control"
                               value="<?= htmlspecialchars($_POST['static_ip'] ?? '') ?>"
                               placeholder="e.g. 192.168.10.50">
                        <div class="form-text">Optional. Assigned to user session.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Create User
                </button>
                <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>

<div class="col-lg-5">
    <div class="card">
        <div class="card-header bg-white fw-semibold py-3">RADIUS Attributes Reference</div>
        <div class="card-body small text-muted">
            <p><i class="bi bi-info-circle text-primary me-1"></i><strong>Cleartext-Password</strong> stores the authentication secret for FreeRADIUS.</p>
            <p><i class="bi bi-info-circle text-primary me-1"></i><strong>Simultaneous-Use</strong> limits concurrent logins across network access servers.</p>
            <p><i class="bi bi-info-circle text-primary me-1"></i><strong>Expiration</strong> sets account expiry date after which RADIUS rejects auth requests.</p>
            <p><i class="bi bi-info-circle text-primary me-1"></i><strong>Group</strong> assigns shared bandwidth / session reply policies.</p>
        </div>
    </div>
</div>
</div>

<?php
$extra_js = '<script>
function generatePw() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$";
    let pw = "";
    for (let i = 0; i < 10; i++) pw += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById("pwField").value = pw;
}
</script>';
include __DIR__ . '/includes/footer.php';

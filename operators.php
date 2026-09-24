<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('superadmin');

$page_title = 'Operators & RBAC';
$current_page = 'operators';
$db = getDB();

ensureAdminTables();

$errors = [];
$success = '';

// Handle Create / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'operator';
        $target   = $_POST['target_table'] ?? 'operators'; // 'operators' or 'rm_admins'

        if (!in_array($role, ['superadmin', 'operator', 'readonly'], true)) {
            $role = 'operator';
        }

        if ($username === '' || $password === '') {
            $errors[] = 'Username and password are required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            // Check existence in both tables
            $chkOp = dbFetch("SELECT id FROM operators WHERE username = ?", [$username]);
            $chkAdm = dbFetch("SELECT id FROM rm_admins WHERE username = ?", [$username]);
            if ($chkOp || $chkAdm || (defined('APP_ADMIN') && $username === APP_ADMIN)) {
                $errors[] = "An operator with username \"$username\" already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($target === 'operators' && dbTableExists('operators')) {
                    $parts = explode(' ', $name, 2);
                    $first = $parts[0] ?? $username;
                    $last  = $parts[1] ?? '';
                    $db->exec("SET sql_mode = ''");
                    dbQuery("
                        INSERT INTO operators (username, password, firstname, lastname, email1, role, creationdate, creationby)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                    ", [$username, $hash, $first, $last, $email, $role, getAdminUser()]);
                } else {
                    dbQuery("
                        INSERT INTO rm_admins (username, password, name, email, role)
                        VALUES (?, ?, ?, ?, ?)
                    ", [$username, $hash, $name, $email, $role]);
                }
                auditLog('operator_create', $username, "Created operator with role $role");
                $_SESSION['flash_success'] = "Operator \"$username\" created successfully with role $role.";
                header("Location: operators.php");
                exit;
            }
        }

    } elseif ($action === 'edit_role') {
        $id     = (int)($_POST['id'] ?? 0);
        $source = $_POST['source'] ?? 'operators';
        $role   = $_POST['role'] ?? 'operator';
        $newPass= $_POST['new_password'] ?? '';

        if (!in_array($role, ['superadmin', 'operator', 'readonly'], true)) {
            $role = 'operator';
        }

        if ($id > 0) {
            if ($source === 'operators' && dbTableExists('operators')) {
                $db->exec("SET sql_mode = ''");
                dbQuery("UPDATE operators SET role = ? WHERE id = ?", [$role, $id]);
                if (!empty($newPass)) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    dbQuery("UPDATE operators SET password = ? WHERE id = ?", [$hash, $id]);
                }
            } else {
                dbQuery("UPDATE rm_admins SET role = ? WHERE id = ?", [$role, $id]);
                if (!empty($newPass)) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    dbQuery("UPDATE rm_admins SET password = ? WHERE id = ?", [$hash, $id]);
                }
            }
            auditLog('operator_update', "ID:$id", "Updated operator role to $role" . (!empty($newPass) ? ' and changed password' : ''));
            $_SESSION['flash_success'] = "Operator updated successfully.";
            header("Location: operators.php");
            exit;
        }

    } elseif ($action === 'delete') {
        $id     = (int)($_POST['id'] ?? 0);
        $source = $_POST['source'] ?? 'operators';
        $user   = trim($_POST['username'] ?? '');

        if ($user === getAdminUser()) {
            $errors[] = 'You cannot delete your own currently logged-in account.';
        } elseif ($id > 0) {
            if ($source === 'operators' && dbTableExists('operators')) {
                dbQuery("DELETE FROM operators WHERE id = ?", [$id]);
            } else {
                dbQuery("DELETE FROM rm_admins WHERE id = ?", [$id]);
            }
            auditLog('operator_delete', $user, "Deleted operator account");
            $_SESSION['flash_success'] = "Operator \"$user\" has been removed.";
            header("Location: operators.php");
            exit;
        }
    }
}

// Fetch all operators from operators table
$operators = [];
if (dbTableExists('operators')) {
    $rows = dbFetchAll("SELECT id, username, firstname, lastname, email1, role, lastlogin, 'operators' AS source FROM operators ORDER BY username ASC");
    $operators = array_merge($operators, $rows);
}

// Fetch all from rm_admins table
if (dbTableExists('rm_admins')) {
    $rows = dbFetchAll("SELECT id, username, name AS firstname, '' AS lastname, email AS email1, role, created_at AS lastlogin, 'rm_admins' AS source FROM rm_admins ORDER BY username ASC");
    $operators = array_merge($operators, $rows);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-badge me-2 text-primary"></i>Operators & Role-Based Access Control</h4>
        <p class="text-muted mb-0">Manage administrator accounts, operational permissions, and read-only roles</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOperatorModal">
        <i class="bi bi-plus-lg me-1"></i> Add Operator
    </button>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($_SESSION['flash_success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Role Info Card -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 h-100 border-start border-danger border-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-danger">superadmin</span>
                <span class="fw-bold">Full Access</span>
            </div>
            <div class="small text-muted">Complete control over all modules, system settings, NAS hardware, audit trails, and operator roles.</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 h-100 border-start border-primary border-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary">operator</span>
                <span class="fw-bold">Operations Manager</span>
            </div>
            <div class="small text-muted">Full day-to-day control of users, rate plans, vouchers, sessions, and accounting. No NAS or system settings.</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 h-100 border-start border-secondary border-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-secondary">readonly</span>
                <span class="fw-bold">Auditor / Viewer</span>
            </div>
            <div class="small text-muted">View-only permissions across all dashboards and reports. Action buttons and mutations are restricted.</div>
        </div>
    </div>
</div>

<!-- Operators Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold">Configured Administrators & Operators (<?= count($operators) ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Source</th>
                    <th>Last Active</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Built-in config admin -->
                <tr class="table-light">
                    <td>
                        <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars(APP_ADMIN) ?></span>
                        <span class="badge bg-light text-dark border ms-1">Root</span>
                    </td>
                    <td><span class="text-muted">Config Root Admin</span></td>
                    <td><span class="text-muted">—</span></td>
                    <td><span class="badge bg-danger">superadmin</span></td>
                    <td><span class="badge bg-light text-secondary border">config.php</span></td>
                    <td><span class="text-muted">Always Available</span></td>
                    <td class="text-end pe-3">
                        <span class="badge bg-secondary-subtle text-secondary">System Locked</span>
                    </td>
                </tr>

                <?php foreach ($operators as $op): ?>
                <tr>
                    <td>
                        <span class="fw-bold text-primary font-monospace"><?= htmlspecialchars($op['username']) ?></span>
                        <?php if ($op['username'] === getAdminUser()): ?>
                            <span class="badge bg-success-subtle text-success ms-1">You</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(trim(($op['firstname'] ?? '') . ' ' . ($op['lastname'] ?? ''))) ?: '<span class="text-muted">—</span>' ?></td>
                    <td><?= htmlspecialchars($op['email1'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?php if ($op['role'] === 'superadmin'): ?>
                            <span class="badge bg-danger"><i class="bi bi-shield-shaded me-1"></i>Superadmin</span>
                        <?php elseif ($op['role'] === 'readonly'): ?>
                            <span class="badge bg-secondary"><i class="bi bi-eye me-1"></i>Readonly</span>
                        <?php else: ?>
                            <span class="badge bg-primary"><i class="bi bi-person-gear me-1"></i>Operator</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($op['source']) ?></span>
                    </td>
                    <td>
                        <small class="text-muted"><?= (!empty($op['lastlogin']) && $op['lastlogin'] !== '0000-00-00 00:00:00') ? htmlspecialchars($op['lastlogin']) : 'Never' ?></small>
                    </td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#editModal<?= $op['source'] ?>_<?= $op['id'] ?>">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <?php if ($op['username'] !== getAdminUser()): ?>
                        <form method="POST" action="operators.php" class="d-inline" onsubmit="return confirm('Delete operator <?= htmlspecialchars($op['username']) ?>?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $op['id'] ?>">
                            <input type="hidden" name="source" value="<?= $op['source'] ?>">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($op['username']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Operator Modals (rendered outside table for correct CSS/DOM hierarchy) -->
<?php foreach ($operators as $op): ?>
<div class="modal fade" id="editModal<?= $op['source'] ?>_<?= $op['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-white shadow border-0">
            <form method="POST" action="operators.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="id" value="<?= $op['id'] ?>">
                <input type="hidden" name="source" value="<?= $op['source'] ?>">
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-person-gear me-1 text-primary"></i>Edit Operator: <?= htmlspecialchars($op['username']) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Role Permissions</label>
                        <select name="role" class="form-select">
                            <option value="superadmin" <?= $op['role'] === 'superadmin' ? 'selected' : '' ?>>superadmin (Full Access)</option>
                            <option value="operator" <?= $op['role'] === 'operator' ? 'selected' : '' ?>>operator (Users & Sessions)</option>
                            <option value="readonly" <?= $op['role'] === 'readonly' ? 'selected' : '' ?>>readonly (View Only)</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-secondary">Change Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="New password (optional)">
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Modal: Add New Operator -->
<div class="modal fade" id="newOperatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-white shadow border-0">
            <form method="POST" action="operators.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus me-1 text-primary"></i>Create New Operator</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. operator1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Role Permissions <span class="text-danger">*</span></label>
                        <select name="role" class="form-select">
                            <option value="operator" selected>operator (Users, Sessions, Vouchers)</option>
                            <option value="superadmin">superadmin (Full System Access)</option>
                            <option value="readonly">readonly (View Only)</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Full Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. John Doe">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="john@example.com">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-secondary">Storage Backend</label>
                        <select name="target_table" class="form-select">
                            <option value="operators" selected>FreeRADIUS Operators Table (daloRADIUS compatible)</option>
                            <option value="rm_admins">RadiusManager Admins Table (rm_admins)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3">Create Operator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


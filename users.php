<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Users';
$db = getDB();

// Check if userinfo table exists
$hasUserinfo = dbTableExists('userinfo');
$userInfoJoin = $hasUserinfo ? "LEFT JOIN userinfo ui ON ui.username = rc.username" : "";

// Search & pagination
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = defined('ROWS_PER_PAGE') ? ROWS_PER_PAGE : 20;
$offset  = ($page - 1) * $perPage;
$lim     = (int)$perPage;
$off     = (int)$offset;

if ($search) {
    $q = "%$search%";
    if ($hasUserinfo) {
        $totalStmt = $db->prepare("SELECT COUNT(DISTINCT rc.username) c FROM radcheck rc LEFT JOIN userinfo ui ON ui.username = rc.username WHERE rc.username LIKE ? OR ui.firstname LIKE ? OR ui.department LIKE ? OR ui.email LIKE ?");
        $totalStmt->execute([$q, $q, $q, $q]);
        $total = (int)($totalStmt->fetch()['c'] ?? 0);
        $uStmt = $db->prepare("SELECT DISTINCT rc.username FROM radcheck rc LEFT JOIN userinfo ui ON ui.username = rc.username WHERE rc.username LIKE ? OR ui.firstname LIKE ? OR ui.department LIKE ? OR ui.email LIKE ? ORDER BY rc.username LIMIT $lim OFFSET $off");
        $uStmt->execute([$q, $q, $q, $q]);
    } else {
        $totalStmt = $db->prepare("SELECT COUNT(DISTINCT username) c FROM radcheck WHERE username LIKE ?");
        $totalStmt->execute([$q]);
        $total = (int)($totalStmt->fetch()['c'] ?? 0);
        $uStmt = $db->prepare("SELECT DISTINCT username FROM radcheck WHERE username LIKE ? ORDER BY username LIMIT $lim OFFSET $off");
        $uStmt->execute([$q]);
    }
    $usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $total = (int)$db->query("SELECT COUNT(DISTINCT username) FROM radcheck")->fetchColumn();
    $uStmt = $db->query("SELECT DISTINCT username FROM radcheck ORDER BY username LIMIT $lim OFFSET $off");
    $usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN);
}

$pages = max(1, (int)ceil($total / $perPage));

$allGroups = $db->query("SELECT DISTINCT groupname FROM radusergroup UNION SELECT DISTINCT groupname FROM radgroupcheck ORDER BY groupname")->fetchAll(PDO::FETCH_COLUMN);

$uiCols = $hasUserinfo ? "MAX(ui.firstname) AS firstname, MAX(ui.lastname) AS lastname, MAX(ui.department) AS department, MAX(ui.email) AS email," : "";

if (!empty($usernames)) {
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));

    // Check which of these 20 users are currently active in radacct (fast batch check, 2ms)
    $onlineStmt = $db->prepare("SELECT DISTINCT username FROM radacct WHERE username IN ($placeholders) AND acctstoptime IS NULL");
    $onlineStmt->execute($usernames);
    $onlineSet = array_flip($onlineStmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $db->prepare("SELECT rc.username,
        COALESCE(
            MAX(CASE WHEN rc.attribute='Cleartext-Password' THEN rc.value END),
            MAX(CASE WHEN rc.attribute='User-Password' THEN rc.value END)
        ) AS password,
        MAX(CASE WHEN rc.attribute='Auth-Type' AND rc.value='Reject' THEN 1 ELSE 0 END) AS is_disabled,
        MAX(CASE WHEN rr.attribute='Framed-IP-Address' THEN rr.value END) AS static_ip,
        GROUP_CONCAT(DISTINCT rug.groupname ORDER BY rug.priority SEPARATOR ', ') AS groupname,
        $uiCols
        rc.username AS dummy
      FROM radcheck rc
      $userInfoJoin
      LEFT JOIN radusergroup rug ON rug.username=rc.username
      LEFT JOIN radreply rr ON rr.username=rc.username
      WHERE rc.username IN ($placeholders)
      GROUP BY rc.username
      ORDER BY rc.username");
    $stmt->execute($usernames);
    $rawUsers = $stmt->fetchAll();

    $users = [];
    foreach ($rawUsers as $u) {
        $u['online'] = isset($onlineSet[$u['username']]) ? 1 : 0;
        $users[] = $u;
    }
} else {
    $users = [];
}

// Flash message
$flash = getFlash();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-people me-2 text-primary"></i>Users</h4>
        <p><?= number_format($total) ?> total users</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export.php?type=users<?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="user-import.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-upload me-1"></i>Import CSV
        </a>
        <a href="user-add.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add User
        </a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Search bar -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Search username, name, department..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-sm btn-primary px-3">Search</button>
            <?php if ($search): ?>
            <a href="users.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Users table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr>
                <th style="width: 40px;" class="text-center">
                    <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll(this)" title="Select all on this page">
                </th>
                <th>Username & Info</th>
                <th>Password</th>
                <th>Group</th>
                <th>Status</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>No users found
            </td></tr>
            <?php else: foreach ($users as $u): ?>
            <tr class="<?= !empty($u['is_disabled']) ? 'table-light opacity-75' : '' ?>">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input user-check" value="<?= htmlspecialchars($u['username']) ?>" onchange="updateBatchBar()">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-person-circle <?= !empty($u['is_disabled']) ? 'text-danger' : 'text-secondary' ?> fs-5 me-2"></i>
                        <div>
                            <div>
                                <strong><?= sanitize($u['username']) ?></strong>
                                <?php if (!empty($u['is_disabled'])): ?>
                                <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">DISABLED</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($u['static_ip'])): ?>
                            <div class="text-muted small" title="Static IP Assignment">
                                <i class="bi bi-hdd-network text-primary me-1" style="font-size: 0.75rem;"></i><code><?= sanitize($u['static_ip']) ?></code>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($u['firstname'])): ?>
                            <div class="text-muted small">
                                <?= sanitize($u['firstname']) ?>
                                <?php if (!empty($u['department'])): ?>
                                <span class="badge bg-light text-secondary border ms-1"><?= sanitize($u['department']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if (!empty($u['password'])): ?>
                    <span class="text-muted user-select-none" id="pw_<?= md5($u['username']) ?>">••••••••</span>
                    <button class="btn btn-link btn-sm p-0 ms-1"
                        onclick="togglePw('<?= md5($u['username']) ?>','<?= htmlspecialchars(addslashes($u['password']), ENT_QUOTES) ?>')"
                        title="Show/hide">
                        <i class="bi bi-eye text-muted" style="font-size:.8rem"></i>
                    </button>
                    <?php else: ?>
                    <span class="text-muted small"><em>Encrypted / None</em></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($u['groupname'])): ?>
                        <?php foreach (explode(', ', $u['groupname']) as $grp): ?>
                        <span class="badge bg-light text-dark border me-1"><?= sanitize($grp) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($u['is_disabled'])): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                        <i class="bi bi-slash-circle me-1"></i>Disabled
                    </span>
                    <?php elseif ($u['online'] > 0): ?>
                    <span class="badge badge-online rounded-pill"><i class="bi bi-circle-fill me-1" style="font-size:.4rem"></i>Online</span>
                    <?php else: ?>
                    <span class="badge badge-offline rounded-pill">Offline</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($u['is_disabled'])): ?>
                    <button class="btn btn-sm btn-outline-success py-0 px-2 me-1"
                        onclick="toggleUser('<?= htmlspecialchars(addslashes($u['username']), ENT_QUOTES) ?>', 'enable')"
                        title="Enable User">
                        <i class="bi bi-check-circle"></i>
                    </button>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-warning py-0 px-2 me-1"
                        onclick="toggleUser('<?= htmlspecialchars(addslashes($u['username']), ENT_QUOTES) ?>', 'disable')"
                        title="Disable User">
                        <i class="bi bi-slash-circle"></i>
                    </button>
                    <?php endif; ?>
                    <a href="user-edit.php?username=<?= urlencode($u['username']) ?>"
                       class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2"
                        onclick="confirmDelete('<?= htmlspecialchars(addslashes($u['username']), ENT_QUOTES) ?>')" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white border-top d-flex align-items-center justify-content-between">
        <span class="small text-muted">Page <?= $page ?> of <?= $pages ?> (<?= number_format($total) ?> total)</span>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Floating Batch Action Toolbar -->
<div id="batchActionBar" class="card shadow-lg position-fixed bottom-0 start-50 translate-middle-x mb-4 d-none" style="z-index: 1050; min-width: 580px; max-width: 90%;">
    <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-3 bg-white rounded-3 border">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary rounded-pill px-2.5 py-1.5" id="selectedCount">0</span>
            <span class="small fw-semibold text-secondary">users selected</span>
        </div>
        <form id="batchForm" method="POST" action="user-batch.php" class="d-flex align-items-center gap-2 mb-0">
            <?= csrfField() ?>
            <div id="batchUsernamesContainer"></div>

            <select name="action" id="batchActionSelect" class="form-select form-select-sm" style="width: 170px;" onchange="toggleGroupSelect(this.value)" required>
                <option value="">-- Choose Action --</option>
                <option value="enable">Enable Selected</option>
                <option value="disable">Disable Selected</option>
                <option value="change_group">Change Group</option>
                <option value="export">Export Selected (CSV)</option>
                <option value="delete">Delete Selected</option>
            </select>

            <select name="new_group" id="batchGroupSelect" class="form-select form-select-sm d-none" style="width: 150px;">
                <option value="">Select Group...</option>
                <?php foreach ($allGroups as $grpName): ?>
                <option value="<?= htmlspecialchars($grpName) ?>"><?= htmlspecialchars($grpName) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="button" class="btn btn-sm btn-primary px-3" onclick="submitBatchAction()">
                Apply
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                Cancel
            </button>
        </form>
    </div>
</div>

<!-- Delete form (hidden) -->
<form id="deleteForm" method="POST" action="user-delete.php">
    <?= csrfField() ?>
    <input type="hidden" name="username" id="deleteUsername">
</form>

<!-- Toggle form (hidden) -->
<form id="toggleForm" method="POST" action="user-toggle.php">
    <?= csrfField() ?>
    <input type="hidden" name="username" id="toggleUsername">
    <input type="hidden" name="state" id="toggleState">
    <input type="hidden" name="return_url" value="users.php<?= $search ? '?q=' . urlencode($search) : '' ?>">
</form>

<?php
$extra_js = '<script>
function togglePw(id, pw) {
    const el = document.getElementById("pw_" + id);
    el.textContent = el.textContent === "••••••••" ? pw : "••••••••";
}

function confirmDelete(username) {
    if (confirm("Delete user: " + username + "?\\nThis will remove all user attributes and group assignments.")) {
        document.getElementById("deleteUsername").value = username;
        document.getElementById("deleteForm").submit();
    }
}

function toggleUser(username, state) {
    document.getElementById("toggleUsername").value = username;
    document.getElementById("toggleState").value = state;
    document.getElementById("toggleForm").submit();
}

function toggleSelectAll(master) {
    const checks = document.querySelectorAll(".user-check");
    checks.forEach(c => c.checked = master.checked);
    updateBatchBar();
}

function updateBatchBar() {
    const checked = document.querySelectorAll(".user-check:checked");
    const count = checked.length;
    const bar = document.getElementById("batchActionBar");
    const countBadge = document.getElementById("selectedCount");
    const selectAll = document.getElementById("selectAll");
    const allChecks = document.querySelectorAll(".user-check");

    countBadge.textContent = count;
    if (count > 0) {
        bar.classList.remove("d-none");
    } else {
        bar.classList.add("d-none");
    }

    if (selectAll) {
        selectAll.checked = allChecks.length > 0 && checked.length === allChecks.length;
        selectAll.indeterminate = count > 0 && count < allChecks.length;
    }
}

function toggleGroupSelect(action) {
    const grpSelect = document.getElementById("batchGroupSelect");
    if (action === "change_group") {
        grpSelect.classList.remove("d-none");
        grpSelect.setAttribute("required", "required");
    } else {
        grpSelect.classList.add("d-none");
        grpSelect.removeAttribute("required");
    }
}

function clearSelection() {
    document.querySelectorAll(".user-check").forEach(c => c.checked = false);
    const selectAll = document.getElementById("selectAll");
    if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }
    updateBatchBar();
}

function submitBatchAction() {
    const checked = document.querySelectorAll(".user-check:checked");
    if (checked.length === 0) {
        alert("Please select at least one user.");
        return;
    }

    const action = document.getElementById("batchActionSelect").value;
    if (!action) {
        alert("Please select an action to perform.");
        return;
    }

    if (action === "change_group") {
        const grp = document.getElementById("batchGroupSelect").value;
        if (!grp) {
            alert("Please choose a target group.");
            return;
        }
    }

    if (action === "delete") {
        if (!confirm("Are you sure you want to permanently delete " + checked.length + " selected user(s)?\\nThis action cannot be undone.")) {
            return;
        }
    }

    const container = document.getElementById("batchUsernamesContainer");
    container.innerHTML = "";
    checked.forEach(c => {
        const inp = document.createElement("input");
        inp.type = "hidden";
        inp.name = "usernames[]";
        inp.value = c.value;
        container.appendChild(inp);
    });

    document.getElementById("batchForm").submit();
}
</script>';
include __DIR__ . '/includes/footer.php';

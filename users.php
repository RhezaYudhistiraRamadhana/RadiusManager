<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Users';
$db = getDB();

// Check if userinfo table exists
$hasUserinfo = dbTableExists('userinfo');
$userInfoJoin = $hasUserinfo ? "LEFT JOIN userinfo ui ON ui.username = rc.username" : "";

// Search, group filter & pagination
$search      = trim($_GET['q'] ?? '');
$groupFilter = trim($_GET['group'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = defined('ROWS_PER_PAGE') ? ROWS_PER_PAGE : 20;
$offset      = ($page - 1) * $perPage;
$lim         = (int)$perPage;
$off         = (int)$offset;

$whereClauses = [];
$params = [];

if ($groupFilter !== '') {
    $whereClauses[] = "rug.groupname = ?";
    $params[] = $groupFilter;
}

if ($search !== '') {
    $q = "%$search%";
    if ($hasUserinfo) {
        $whereClauses[] = "(rc.username LIKE ? OR ui.firstname LIKE ? OR ui.lastname LIKE ? OR ui.department LIKE ? OR ui.email LIKE ? OR rug.groupname LIKE ?)";
        $params = array_merge($params, [$q, $q, $q, $q, $q, $q]);
    } else {
        $whereClauses[] = "(rc.username LIKE ? OR rug.groupname LIKE ?)";
        $params = array_merge($params, [$q, $q]);
    }
}

if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(' AND ', $whereClauses);
    $totalStmt = $db->prepare("SELECT COUNT(DISTINCT rc.username) c FROM radcheck rc $userInfoJoin LEFT JOIN radusergroup rug ON rug.username = rc.username $whereSql");
    $totalStmt->execute($params);
    $total = (int)($totalStmt->fetch()['c'] ?? 0);

    $uStmt = $db->prepare("SELECT DISTINCT rc.username FROM radcheck rc $userInfoJoin LEFT JOIN radusergroup rug ON rug.username = rc.username $whereSql ORDER BY rc.username LIMIT $lim OFFSET $off");
    $uStmt->execute($params);
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

    // Query connected devices count per user (fast batch GROUP BY query, < 2ms)
    $onlineStmt = $db->prepare("
        SELECT username, COUNT(*) AS device_count
        FROM radacct
        WHERE username IN ($placeholders) AND acctstoptime IS NULL
        GROUP BY username
    ");
    $onlineStmt->execute($usernames);
    $deviceMap = $onlineStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

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
        $devCount = (int)($deviceMap[$u['username']] ?? 0);
        $u['device_count'] = $devCount;
        $u['online'] = ($devCount > 0) ? 1 : 0;
        $users[] = $u;
    }
} else {
    $users = [];
}

// Flash message
$flash = getFlash();

$exportParams = ['type' => 'users'];
if ($search !== '') $exportParams['q'] = $search;
if ($groupFilter !== '') $exportParams['group'] = $groupFilter;
$exportUrl = 'export.php?' . http_build_query($exportParams);

$returnParams = [];
if ($search !== '') $returnParams['q'] = $search;
if ($groupFilter !== '') $returnParams['group'] = $groupFilter;
if ($page > 1) $returnParams['page'] = $page;
$returnUrl = 'users.php' . (!empty($returnParams) ? '?' . http_build_query($returnParams) : '');

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-people me-2 text-primary"></i>Users</h4>
        <p class="mb-0 text-muted">
            <?= number_format($total) ?> total users
            <?php if ($groupFilter !== ''): ?>
            <span class="ms-1">in group <span class="badge bg-primary"><?= htmlspecialchars($groupFilter) ?></span></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="bi bi-download me-1"></i>Export CSV
            </button>
            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Export Options">
                <span class="visually-hidden">Toggle Dropdown</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header text-uppercase fs-xs">Subscribers</h6></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportModal"><i class="bi bi-sliders me-2 text-primary"></i>Custom Export Options...</a></li>
                <li><a class="dropdown-item" href="<?= $exportUrl ?>"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Export Current View (Default)</a></li>
                <li><a class="dropdown-item" href="export.php?type=users"><i class="bi bi-people me-2 text-secondary"></i>Export All Users CSV</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-uppercase fs-xs">Other Datasets</h6></li>
                <li><a class="dropdown-item" href="export.php?type=accounting"><i class="bi bi-clock-history me-2 text-secondary"></i>Accounting Sessions</a></li>
                <li><a class="dropdown-item" href="export.php?type=postauth"><i class="bi bi-shield-check me-2 text-secondary"></i>Authentication Logs</a></li>
                <li><a class="dropdown-item" href="export.php?type=vouchers"><i class="bi bi-ticket-perforated me-2 text-secondary"></i>Hotspot Vouchers</a></li>
                <li><a class="dropdown-item" href="export.php?type=nas"><i class="bi bi-router me-2 text-secondary"></i>NAS Devices Inventory</a></li>
                <li><a class="dropdown-item" href="export.php?type=audit"><i class="bi bi-journal-text me-2 text-secondary"></i>Audit Trail</a></li>
                <li><a class="dropdown-item" href="export.php?type=schema"><i class="bi bi-filetype-sql me-2 text-secondary"></i>Database Schema SQL</a></li>
            </ul>
        </div>
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

<!-- Search & Filter bar -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Search username, name, department..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-tag"></i> Group</span>
                    <select name="group" class="form-select" onchange="this.form.submit()">
                        <option value="">All Groups / Plans</option>
                        <?php foreach ($allGroups as $grpName): ?>
                        <option value="<?= htmlspecialchars($grpName) ?>" <?= $groupFilter === $grpName ? 'selected' : '' ?>>
                            <?= htmlspecialchars($grpName) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== '' || $groupFilter !== ''): ?>
                <a href="users.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>

            <?php if ($groupFilter !== '' || $search !== ''): ?>
            <div class="col-12 pt-2 border-top d-flex align-items-center gap-2 flex-wrap">
                <span class="small text-muted">Active filters:</span>
                <?php if ($groupFilter !== ''): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1">
                    <i class="bi bi-tag-fill me-1"></i> Group: <strong><?= htmlspecialchars($groupFilter) ?></strong>
                    <a href="users.php?<?= http_build_query(array_filter(['q' => $search])) ?>" class="text-primary text-decoration-none ms-1" title="Remove group filter">&times;</a>
                </span>
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1">
                    <i class="bi bi-search me-1"></i> Keyword: <strong><?= htmlspecialchars($search) ?></strong>
                    <a href="users.php?<?= http_build_query(array_filter(['group' => $groupFilter])) ?>" class="text-secondary text-decoration-none ms-1" title="Remove keyword filter">&times;</a>
                </span>
                <?php endif; ?>
                <a href="users.php" class="small text-danger text-decoration-none ms-2">Clear all</a>
            </div>
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
                <th class="text-center">Connected Devices</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">
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
                <td class="text-center">
                    <?php if ($u['device_count'] > 0): ?>
                    <a href="sessions.php?q=<?= urlencode($u['username']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill text-decoration-none py-1.5 px-2.5 d-inline-flex align-items-center gap-1.5" title="View <?= $u['device_count'] ?> active session(s) in Active Sessions">
                        <i class="bi bi-laptop"></i>
                        <span class="fw-bold"><?= $u['device_count'] ?></span> <?= $u['device_count'] === 1 ? 'device' : 'devices' ?>
                    </a>
                    <?php else: ?>
                    <span class="badge bg-light text-muted border rounded-pill py-1.5 px-2.5 d-inline-flex align-items-center gap-1" title="No devices currently connected">
                        <i class="bi bi-phone text-muted" style="font-size:.75rem"></i> 0 devices
                    </span>
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
            <?php 
            $basePageParams = [];
            if ($search !== '') $basePageParams['q'] = $search;
            if ($groupFilter !== '') $basePageParams['group'] = $groupFilter;
            for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): 
                $pParams = array_merge($basePageParams, ['page' => $i]);
            ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
                <a class="page-link" href="?<?= http_build_query($pParams) ?>"><?= $i ?></a>
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
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
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
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
</form>

<!-- Toggle form (hidden) -->
<form id="toggleForm" method="POST" action="user-toggle.php">
    <?= csrfField() ?>
    <input type="hidden" name="username" id="toggleUsername">
    <input type="hidden" name="state" id="toggleState">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
</form>

<!-- Modal: Export Options -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-semibold text-dark" id="exportModalLabel">
                    <i class="bi bi-download text-success me-2"></i>Export System Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <ul class="nav nav-pills nav-fill mb-3" id="exportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-medium" id="export-users-tab" data-bs-toggle="tab" data-bs-target="#export-users-pane" type="button" role="tab">
                            <i class="bi bi-people-fill me-1"></i> Subscribers / Users
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-medium" id="export-more-tab" data-bs-toggle="tab" data-bs-target="#export-more-pane" type="button" role="tab">
                            <i class="bi bi-database-down me-1"></i> Other Datasets & Backups
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="exportTabContent">
                    <!-- Tab 1: User Export Form -->
                    <div class="tab-pane fade show active" id="export-users-pane" role="tabpanel">
                        <form method="GET" action="export.php" id="customUserExportForm">
                            <input type="hidden" name="type" value="users">
                            
                            <!-- Section: Scope & Filter -->
                            <div class="card border mb-3">
                                <div class="card-header bg-white py-2 fw-semibold text-muted small text-uppercase">
                                    <i class="bi bi-funnel me-1 text-primary"></i> 1. Scope & Filter
                                </div>
                                <div class="card-body py-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary">Target Group / Scope</label>
                                            <select name="group" class="form-select form-select-sm" id="exportGroupFilter">
                                                <option value="" <?= ($groupFilter === '') ? 'selected' : '' ?>>All Groups (<?= number_format($total) ?> users)</option>
                                                <?php foreach ($allGroups as $grpName): ?>
                                                <option value="<?= htmlspecialchars($grpName) ?>" <?= ($groupFilter === $grpName) ? 'selected' : '' ?>>
                                                    Group: <?= htmlspecialchars($grpName) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary">Account Status</label>
                                            <select name="status" class="form-select form-select-sm">
                                                <option value="">All Statuses (Active & Disabled)</option>
                                                <option value="active">Active Accounts Only</option>
                                                <option value="disabled">Disabled Accounts Only</option>
                                                <option value="online">Currently Online Only</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold text-secondary">Keyword Search Filter (Optional)</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                <input type="text" name="q" class="form-control" placeholder="Leave empty for all, or type keyword..." value="<?= htmlspecialchars($search) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Column Selection -->
                            <div class="card border mb-3">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-muted small text-uppercase">
                                        <i class="bi bi-columns-gap me-1 text-primary"></i> 2. Choose Fields to Export
                                    </span>
                                    <div class="small">
                                        <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none" onclick="toggleExportCols(true)">Select All</button>
                                        <span class="text-muted mx-1">&bull;</span>
                                        <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none" onclick="resetDefaultExportCols()">Default</button>
                                        <span class="text-muted mx-1">&bull;</span>
                                        <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none" onclick="toggleExportCols(false)">Clear Optional</button>
                                    </div>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-2">
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="username" id="col_username" checked disabled>
                                                <input type="hidden" name="cols[]" value="username">
                                                <label class="form-check-label fw-semibold" for="col_username">
                                                    Username <span class="badge bg-secondary-subtle text-secondary fs-xs">Required</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="password" id="col_password" checked>
                                                <label class="form-check-label" for="col_password">Password</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="group" id="col_group" checked>
                                                <label class="form-check-label" for="col_group">Group / Profile</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="status" id="col_status" checked>
                                                <label class="form-check-label" for="col_status">Account Status</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="online" id="col_online" checked>
                                                <label class="form-check-label" for="col_online">Online Status</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="firstname" id="col_firstname" checked>
                                                <label class="form-check-label" for="col_firstname">First Name</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="lastname" id="col_lastname" checked>
                                                <label class="form-check-label" for="col_lastname">Last Name</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="department" id="col_department" checked>
                                                <label class="form-check-label" for="col_department">Department</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="email" id="col_email" checked>
                                                <label class="form-check-label" for="col_email">Email</label>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input export-col-check" type="checkbox" name="cols[]" value="static_ip" id="col_static_ip" checked>
                                                <label class="form-check-label" for="col_static_ip">Static IP Address</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: CSV Options -->
                            <div class="card border mb-3">
                                <div class="card-header bg-white py-2 fw-semibold text-muted small text-uppercase">
                                    <i class="bi bi-file-earmark-code me-1 text-primary"></i> 3. CSV File Formatting
                                </div>
                                <div class="card-body py-2">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary mb-1">CSV Delimiter</label>
                                            <select name="delimiter" class="form-select form-select-sm">
                                                <option value="," selected>Comma ( , ) &mdash; Standard / Excel US</option>
                                                <option value=";">Semicolon ( ; ) &mdash; Regional / Excel EU</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 pt-md-3">
                                            <div class="form-check">
                                                <input type="hidden" name="headers" value="0">
                                                <input class="form-check-input" type="checkbox" name="headers" value="1" id="exportHeaders" checked>
                                                <label class="form-check-label small" for="exportHeaders">
                                                    Include header column names as row 1
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
                                <a href="<?= $exportUrl ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-lightning-charge me-1"></i>Quick Export (Default)
                                </a>
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="bi bi-download me-1"></i>Download CSV
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 2: Other Datasets -->
                    <div class="tab-pane fade" id="export-more-pane" role="tabpanel">
                        <p class="text-muted small mb-3">Need to export logs, accounting data, or system assets? Select an entity below to generate instant CSV / SQL downloads:</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="rounded p-2 bg-primary-subtle text-primary">
                                            <i class="bi bi-clock-history fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">Accounting History</h6>
                                            <p class="text-muted small mb-2">RADIUS session records with duration, upload/download octets, and termination causes.</p>
                                            <a href="export.php?type=accounting" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download me-1"></i>Export Accounting CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="rounded p-2 bg-warning-subtle text-warning">
                                            <i class="bi bi-shield-check fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">Authentication Logs</h6>
                                            <p class="text-muted small mb-2">Access-Accept and Access-Reject post-auth history across all network NAS devices.</p>
                                            <a href="export.php?type=postauth" class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-download me-1"></i>Export Auth Logs CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="rounded p-2 bg-info-subtle text-info">
                                            <i class="bi bi-ticket-perforated fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">Hotspot Vouchers</h6>
                                            <p class="text-muted small mb-2">Batch voucher codes, assigned profiles, status (unused/active/expired), and timestamps.</p>
                                            <a href="export.php?type=vouchers" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-download me-1"></i>Export Vouchers CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="rounded p-2 bg-success-subtle text-success">
                                            <i class="bi bi-router fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">NAS Devices Inventory</h6>
                                            <p class="text-muted small mb-2">Configured MikroTik, Cisco, and generic access points with secrets and ports.</p>
                                            <a href="export.php?type=nas" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download me-1"></i>Export NAS CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="rounded p-2 bg-dark-subtle text-dark">
                                            <i class="bi bi-journal-text fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">Administrator Audit Trail</h6>
                                            <p class="text-muted small mb-2">All administrative modifications, operator IPs, and sensitive action timestamps.</p>
                                            <a href="export.php?type=audit" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-download me-1"></i>Export Audit CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="rounded p-2 bg-danger-subtle text-danger">
                                            <i class="bi bi-filetype-sql fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">Database Schema DDL</h6>
                                            <p class="text-muted small mb-2">Export complete MySQL table schemas and DDL structures for FreeRADIUS.</p>
                                            <a href="export.php?type=schema" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-download me-1"></i>Export Schema SQL
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script>
function toggleExportCols(selectAll) {
    document.querySelectorAll(".export-col-check:not([disabled])").forEach(c => {
        c.checked = selectAll;
    });
}

function resetDefaultExportCols() {
    const defaults = ["password", "group", "status", "online", "firstname", "lastname", "department", "email"];
    document.querySelectorAll(".export-col-check:not([disabled])").forEach(c => {
        c.checked = defaults.includes(c.value);
    });
}

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

<?php
/**
 * CENDANA — Force Re-Login / Session Disconnect (Admin Only)
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Allows administrators to force all accounts or specific accounts to re-authenticate
 * so that their network/internet connection is re-established.
 */

require_once __DIR__ . '/auth.php';
requireLogin();

// Strict Administrator-Only Access Guard
if (!hasRole('superadmin')) {
    header('Location: welcome.php');
    exit;
}

$page_title = 'Force Re-Login (Session Reset)';
$db = getDB();

$msg = '';
$msgType = 'info';

// Handle Disconnect Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'disconnect_all') {
        // 1. Force Disconnect ALL Accounts
        $nasList = $db->query("SELECT DISTINCT nasipaddress FROM radacct WHERE acctstoptime IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        $count = terminateAllRadiusSessions('Admin-Force-Reauth');
        
        foreach ($nasList as $nasIp) {
            if ($nasIp) {
                sendRadiusDisconnect($nasIp, 'all');
            }
        }
        
        auditLog('FORCE_DISCONNECT_ALL', 'ALL_ACCOUNTS', "Terminated $count active session(s) to force re-login for all online devices");
        $msg = "<strong>Success!</strong> A total of <strong>" . number_format($count) . " active session(s)</strong> have been disconnected simultaneously. All users are now required to log in again on their devices to restore internet connectivity.";
        $msgType = 'success';

    } elseif ($action === 'disconnect_user') {
        // 2. Force Disconnect Specific User by Username
        $targetUser = trim($_POST['username'] ?? '');
        if ($targetUser === '') {
            $msg = 'Please enter the username of the account you wish to disconnect.';
            $msgType = 'danger';
        } else {
            $sessStmt = $db->prepare("SELECT acctsessionid, nasipaddress, framedipaddress FROM radacct WHERE username = ? AND acctstoptime IS NULL");
            $sessStmt->execute([$targetUser]);
            $activeList = $sessStmt->fetchAll(PDO::FETCH_ASSOC);
            $count = terminateRadiusSession('', $targetUser, 'Admin-Force-Reauth');

            foreach ($activeList as $row) {
                sendRadiusDisconnect($row['nasipaddress'] ?? '', $targetUser, $row['acctsessionid'] ?? '', $row['framedipaddress'] ?? '');
            }

            auditLog('FORCE_DISCONNECT_USER', $targetUser, "Terminated $count active session(s) for user $targetUser to force re-login");
            if ($count > 0) {
                $msg = "<strong>Success!</strong> Account <strong>" . htmlspecialchars($targetUser) . "</strong> (" . number_format($count) . " session(s)) has been disconnected. The user must re-authenticate to re-establish internet access.";
                $msgType = 'success';
            } else {
                $msg = "Account <strong>" . htmlspecialchars($targetUser) . "</strong> currently has no active online sessions, but the session reset signal was recorded.";
                $msgType = 'warning';
            }
        }

    } elseif ($action === 'disconnect_single') {
        // 3. Force Disconnect Single Session from table
        $sid   = trim($_POST['session_id'] ?? '');
        $u     = trim($_POST['username'] ?? '');
        $nasIp = trim($_POST['nasip'] ?? '');
        $fIp   = trim($_POST['framedip'] ?? '');

        if ($sid !== '') {
            terminateRadiusSession($sid, '', 'Admin-Force-Reauth');
            sendRadiusDisconnect($nasIp, $u, $sid, $fIp);
            auditLog('FORCE_DISCONNECT_SESSION', $u, "Terminated session $sid on NAS $nasIp ($fIp)");
            $msg = "Online session for <strong>" . htmlspecialchars($u) . "</strong> (IP: $fIp) has been disconnected. The user's device must log in again.";
            $msgType = 'success';
        }

    } elseif ($action === 'disconnect_selected') {
        // 4. Force Disconnect Multi-Selected Sessions
        $selectedSids = $_POST['session_ids'] ?? [];
        if (!empty($selectedSids) && is_array($selectedSids)) {
            $count = 0;
            foreach ($selectedSids as $sid) {
                $sid = trim((string)$sid);
                if ($sid !== '') {
                    $sRow = $db->prepare("SELECT username, nasipaddress, framedipaddress FROM radacct WHERE acctsessionid = ? AND acctstoptime IS NULL LIMIT 1");
                    $sRow->execute([$sid]);
                    $row = $sRow->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        terminateRadiusSession($sid, '', 'Admin-Force-Reauth');
                        sendRadiusDisconnect($row['nasipaddress'] ?? '', $row['username'] ?? '', $sid, $row['framedipaddress'] ?? '');
                        auditLog('FORCE_DISCONNECT_SESSION', $row['username'] ?? '', "Terminated session $sid via batch kick");
                        $count++;
                    }
                }
            }
            $msg = "Successfully disconnected <strong>" . number_format($count) . " selected session(s)</strong>. Affected devices must log in again.";
            $msgType = 'success';
        } else {
            $msg = "No sessions selected.";
            $msgType = 'warning';
        }
    }
}

// Total online count
$onlineCount = (int)($db->query("SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL")->fetchColumn() ?? 0);

// Search & Filtering for active sessions
$search = trim($_GET['q'] ?? '');
$whereSql = "WHERE acctstoptime IS NULL";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (username LIKE :q1 OR framedipaddress LIKE :q2 OR callingstationid LIKE :q3)";
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM radacct $whereSql");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();

// Pagination
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$pag     = paginate($filteredTotal, $page, $perPage);
$lim     = (int)$pag['per_page'];
$off     = (int)$pag['offset'];

$sessQuery = $db->prepare("
    SELECT radacctid, acctsessionid, username, nasipaddress, framedipaddress,
           callingstationid, acctstarttime, acctinputoctets, acctoutputoctets
    FROM radacct
    $whereSql
    ORDER BY acctstarttime DESC
    LIMIT $lim OFFSET $off
");
$sessQuery->execute($params);
$activeSessions = $sessQuery->fetchAll(PDO::FETCH_ASSOC);

// Recent Audit Trail for Disconnects
$recentLogs = [];
if (dbTableExists('rm_audit_log')) {
    $recentLogs = $db->query("
        SELECT operator, action, target, detail, ip_address, created_at
        FROM rm_audit_log
        WHERE action LIKE 'FORCE_DISCONNECT%'
        ORDER BY id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="welcome.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Force Re-Login</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1"><i class="bi bi-broadcast-pin text-danger me-2"></i>Force Re-Login &amp; Session Reset</h4>
        <p class="text-secondary small mb-0">Administrator-only utility to terminate active account sessions and compel client devices to re-authenticate (re-login) to restore internet access.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
            <i class="bi bi-wifi me-1"></i> <?= number_format($onlineCount) ?> Online Accounts
        </span>
        <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Status">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i class="bi <?= $msgType === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?> fs-5"></i>
        <div><?= $msg ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Control Cards Grid -->
<div class="row g-3 mb-4">
    <!-- Card 1: Force Disconnect ALL Accounts -->
    <div class="col-lg-6">
        <div class="card h-100 border-danger border-opacity-25 shadow-sm">
            <div class="card-header bg-danger text-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="bi bi-radioactive me-2"></i>1. Force Disconnect ALL Accounts (Global Reset)</span>
                <span class="badge bg-white text-danger fw-bold"><?= number_format($onlineCount) ?> Online</span>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        <strong>Full-Scale Operation:</strong> This command terminates all currently active internet sessions. Every user across the network (Wi-Fi/Hotspot/PPPoE) will be disconnected and redirected to the captive portal / login prompt.
                    </div>
                    <p class="text-secondary small mb-3">
                        Use this feature during global bandwidth policy updates, router shared secret rotations, or emergency gateway reconfigurations where all subscribers must acquire new IP leases and policies.
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-danger w-100 py-2.5 fw-bold d-flex align-items-center justify-content-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#confirmAllModal" <?= $onlineCount === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-power fs-5"></i>
                        <span>Disconnect All Online Accounts (<?= number_format($onlineCount) ?>)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Force Disconnect Specific User by Username -->
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark"><i class="bi bi-person-slash me-2 text-primary"></i>2. Force Disconnect Specific User (Single Account)</span>
                <span class="badge bg-light text-muted border">Target User</span>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <p class="text-secondary small mb-3">
                        Enter a specific subscriber username whose session is misbehaving or non-compliant. Their active sessions will be terminated immediately and they will be prompted to re-authenticate to browse again.
                    </p>
                    <form method="POST" id="userDisconnectForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="disconnect_user">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Subscriber Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="e.g. john.doe / voucher_user" required>
                                <button type="submit" class="btn btn-primary px-3 fw-semibold">
                                    <i class="bi bi-power me-1"></i> Disconnect User Session
                                </button>
                            </div>
                            <div class="form-text small text-muted">The system will look up active sessions in radacct and send Disconnect (PoD/CoA) packets to the corresponding NAS.</div>
                        </div>
                    </form>
                </div>
                <div class="p-3 bg-light rounded-3 border small text-secondary">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    <strong>Tip:</strong> You can also interactively select and disconnect sessions directly from the active sessions table below.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Online Sessions List with Multi-Select Actions -->
<div class="card border shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-5">
                <h5 class="mb-0 fw-bold fs-6">
                    <i class="bi bi-list-check me-2 text-success"></i>Active Online Sessions (<?= number_format($filteredTotal) ?>)
                </h5>
            </div>
            <div class="col-md-7">
                <form method="GET" class="d-flex gap-2 justify-content-md-end">
                    <div class="input-group input-group-sm" style="max-width: 320px;">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search username, IP, MAC..." value="<?= htmlspecialchars($search) ?>">
                        <?php if ($search !== ''): ?>
                        <a href="reauth.php" class="btn btn-outline-secondary" title="Clear Filter"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-secondary">Search</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="batchDisconnectForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="disconnect_selected">

        <div class="card-body p-0">
            <!-- Batch Action Toolbar -->
            <div class="p-2.5 bg-light border-bottom d-flex align-items-center justify-content-between px-3">
                <div class="d-flex align-items-center gap-2">
                    <input type="checkbox" class="form-check-input" id="checkAll" title="Select All on This Page">
                    <label for="checkAll" class="form-check-label small text-secondary fw-semibold">Select All</label>
                </div>
                <div>
                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-3" id="batchBtn" disabled onclick="return confirm('Are you sure you want to disconnect all selected sessions?')">
                        <i class="bi bi-power me-1"></i> Disconnect Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Username</th>
                            <th>IP Address (Framed IP)</th>
                            <th>MAC Address (Calling Station)</th>
                            <th>NAS Gateway</th>
                            <th>Online Since</th>
                            <th>Session Duration</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeSessions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                No active online sessions matching your search criteria.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($activeSessions as $sess): ?>
                        <?php
                            $uptimeSec = time() - strtotime($sess['acctstarttime']);
                            $durationText = formatDuration(max(0, $uptimeSec));
                        ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="session_ids[]" value="<?= htmlspecialchars($sess['acctsessionid']) ?>" class="form-check-input session-checkbox">
                            </td>
                            <td>
                                <a href="users.php?search=<?= urlencode($sess['username']) ?>" class="fw-bold text-decoration-none text-dark">
                                    <?= htmlspecialchars($sess['username']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?= htmlspecialchars($sess['framedipaddress'] ?: '—') ?>
                                </span>
                            </td>
                            <td class="small font-monospace text-muted">
                                <?= htmlspecialchars($sess['callingstationid'] ?: '—') ?>
                            </td>
                            <td class="small">
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= htmlspecialchars($sess['nasipaddress'] ?: '—') ?>
                                </span>
                            </td>
                            <td class="small text-secondary">
                                <?= date('d M H:i', strtotime($sess['acctstarttime'])) ?>
                            </td>
                            <td class="small fw-semibold text-success">
                                <i class="bi bi-stopwatch me-1"></i><?= $durationText ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2.5"
                                        title="Force re-login for this session"
                                        onclick="kickSingleSession('<?= htmlspecialchars(addslashes($sess['acctsessionid'])) ?>', '<?= htmlspecialchars(addslashes($sess['username'])) ?>', '<?= htmlspecialchars(addslashes($sess['nasipaddress'])) ?>', '<?= htmlspecialchars(addslashes($sess['framedipaddress'])) ?>')">
                                    <i class="bi bi-power me-1"></i>Force Re-Login
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
        <span class="small text-muted">Showing <?= count($activeSessions) ?> of <?= number_format($filteredTotal) ?> sessions</span>
        <?= paginationLinks($pag, "reauth.php?q=" . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Force Disconnect Audit Trail -->
<?php if (!empty($recentLogs)): ?>
<div class="card border shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold fs-6 text-secondary">
            <i class="bi bi-clock-history me-2"></i>Recent Force Re-Login Audit Trail
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0 small">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Operator</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>Operator IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td class="text-muted"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($log['operator']) ?></td>
                    <td><span class="badge bg-danger-subtle text-danger border"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($log['target']) ?></td>
                    <td><?= htmlspecialchars($log['detail']) ?></td>
                    <td class="font-monospace text-muted"><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Confirmation Modal: Force All -->
<div class="modal fade" id="confirmAllModal" tabindex="-1" aria-labelledby="confirmAllLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="disconnect_all">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="confirmAllLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Disconnecting All Accounts
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-danger fw-bold mb-2">CRITICAL WARNING!</p>
                    <p class="small text-secondary mb-3">
                        This operation will <strong>disconnect <?= number_format($onlineCount) ?> online users</strong> across the network immediately. Disconnect packets will be dispatched to all NAS gateways, and all users will be required to authenticate again to resume internet access.
                    </p>
                    <div class="p-3 bg-light rounded-2 border small mb-3">
                        Type <strong>RESET</strong> below to confirm this action:
                        <input type="text" id="confirmInput" class="form-control form-control-sm mt-2" placeholder="RESET" autocomplete="off" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-3" id="confirmSubmitBtn" disabled>
                        <i class="bi bi-power me-1"></i> Yes, Disconnect All Accounts
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Single Session Disconnect Form -->
<form method="POST" id="singleKickForm" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="disconnect_single">
    <input type="hidden" name="session_id" id="kickSid">
    <input type="hidden" name="username" id="kickUser">
    <input type="hidden" name="nasip" id="kickNas">
    <input type="hidden" name="framedip" id="kickFip">
</form>

<script>
// Checkbox management for batch disconnect
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.session-checkbox');
    const batchBtn = document.getElementById('batchBtn');
    const selectedCount = document.getElementById('selectedCount');

    function updateSelected() {
        let count = 0;
        checkboxes.forEach(cb => { if (cb.checked) count++; });
        if (selectedCount) selectedCount.textContent = count;
        if (batchBtn) batchBtn.disabled = (count === 0);
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = checkAll.checked; });
            updateSelected();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelected);
    });

    // Confirmation input guard for Reset All
    const confirmInput = document.getElementById('confirmInput');
    const confirmBtn = document.getElementById('confirmSubmitBtn');
    if (confirmInput && confirmBtn) {
        confirmInput.addEventListener('input', function() {
            confirmBtn.disabled = (this.value.trim().toUpperCase() !== 'RESET');
        });
    }
});

function kickSingleSession(sid, user, nasip, fip) {
    if (confirm(`Are you sure you want to disconnect session for user '${user}' (${fip}) to force re-login?`)) {
        document.getElementById('kickSid').value = sid;
        document.getElementById('kickUser').value = user;
        document.getElementById('kickNas').value = nasip;
        document.getElementById('kickFip').value = fip;
        document.getElementById('singleKickForm').submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


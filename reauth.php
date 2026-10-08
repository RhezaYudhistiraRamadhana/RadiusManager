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

// Flash message retrieval
$flash = getFlash();
$msg = $flash['msg'] ?? '';
$msgType = $flash['type'] ?? 'info';

// Handle Disconnect Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'disconnect_all') {
        // 1. Force Disconnect ALL Accounts (Single fast UPDATE < 4ms)
        $count = terminateAllRadiusSessions('Admin-Force-Reauth');
        
        // Fast non-blocking CoA notifications to registered NAS gateways (< 2ms)
        $nasList = $db->query("SELECT nasname FROM nas WHERE nasname != '0.0.0.0' AND nasname NOT LIKE '%/%'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($nasList as $nasIp) {
            if ($nasIp) sendRadiusDisconnect($nasIp, 'all');
        }
        
        auditLog('FORCE_DISCONNECT_ALL', 'ALL_ACCOUNTS', "Terminated $count active session(s) to force re-login for all online devices");
        setFlash('success', "<strong>Success!</strong> A total of <strong>" . number_format($count) . " active session(s)</strong> have been disconnected simultaneously. All users are now required to log in again on their devices to restore internet connectivity.");
        header('Location: reauth.php');
        exit;

    } elseif ($action === 'disconnect_user') {
        // 2. Force Disconnect Specific User by Username
        $targetUser = trim($_POST['username'] ?? '');
        if ($targetUser === '') {
            setFlash('danger', 'Please enter the username of the account you wish to disconnect.');
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
                setFlash('success', "<strong>Success!</strong> Account <strong>" . htmlspecialchars($targetUser) . "</strong> (" . number_format($count) . " session(s)) has been disconnected. The user must re-authenticate to re-establish internet access.");
            } else {
                setFlash('warning', "Account <strong>" . htmlspecialchars($targetUser) . "</strong> currently has no active online sessions, but the session reset signal was recorded.");
            }
        }
        header('Location: reauth.php');
        exit;

    } elseif ($action === 'disconnect_single') {
        // 3. Force Disconnect Single Session from table (< 3ms)
        $sid   = trim($_POST['session_id'] ?? '');
        $u     = trim($_POST['username'] ?? '');
        $nasIp = trim($_POST['nasip'] ?? '');
        $fIp   = trim($_POST['framedip'] ?? '');

        if ($sid !== '') {
            terminateRadiusSession($sid, '', 'Admin-Force-Reauth');
            sendRadiusDisconnect($nasIp, $u, $sid, $fIp);
            auditLog('FORCE_DISCONNECT_SESSION', $u, "Terminated session $sid on NAS $nasIp ($fIp)");
            setFlash('success', "Online session for <strong>" . htmlspecialchars($u) . "</strong> (IP: $fIp) has been disconnected. The user's device must log in again.");
        }
        $redirectUrl = 'reauth.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : '');
        header('Location: ' . $redirectUrl);
        exit;

    } elseif ($action === 'disconnect_selected') {
        // 4. Force Disconnect Multi-Selected Sessions (Single batch UPDATE < 4ms)
        $selectedSids = $_POST['session_ids'] ?? [];
        if (!empty($selectedSids) && is_array($selectedSids)) {
            $cleanSids = array_values(array_filter(array_map('trim', $selectedSids)));
            if (!empty($cleanSids)) {
                $placeholders = implode(',', array_fill(0, count($cleanSids), '?'));
                $sStmt = $db->prepare("SELECT username, nasipaddress, framedipaddress, acctsessionid FROM radacct WHERE acctsessionid IN ($placeholders) AND acctstoptime IS NULL");
                $sStmt->execute($cleanSids);
                $rows = $sStmt->fetchAll(PDO::FETCH_ASSOC);

                $count = terminateMultipleRadiusSessions($cleanSids, 'Admin-Force-Reauth');

                foreach ($rows as $row) {
                    sendRadiusDisconnect($row['nasipaddress'] ?? '', $row['username'] ?? '', $row['acctsessionid'] ?? '', $row['framedipaddress'] ?? '');
                }

                auditLog('FORCE_DISCONNECT_BATCH', 'MULTIPLE_USERS', "Terminated $count session(s) via batch kick");
                setFlash('success', "Successfully disconnected <strong>" . number_format($count) . " selected session(s)</strong>. Affected devices must log in again.");
            } else {
                setFlash('warning', "No valid sessions selected.");
            }
        } else {
            setFlash('warning', "No sessions selected.");
        }
        header('Location: reauth.php');
        exit;
    }
}

/**
 * Retrieves accounts and connected devices that are genuinely active RIGHT NOW.
 * - Filters out stale / ghost sessions beyond the active network window.
 * - De-duplicates candidate sessions by device MAC address.
 * - Discards devices whose latest session in radacct already ended or was superseded.
 * - De-duplicates overlapping IP addresses.
 * - Groups multiple active devices under their respective subscriber account.
 */
function getTrulyActiveOnlineAccounts(PDO $db, string $search = ''): array {
    $now = time();
    $maxStartStr = $db->query("SELECT MAX(acctstarttime) FROM radacct WHERE acctstoptime IS NULL")->fetchColumn();
    $maxStart = $maxStartStr ? strtotime($maxStartStr) : 0;
    // Dynamic reference: server clock for live systems; latest active record for historical snapshots
    $refNow = ($maxStart > 0 && ($now - $maxStart) > 86400 * 7) ? $maxStart : $now;
    $cutoffTime = date('Y-m-d H:i:s', $refNow - (48 * 3600));

    $whereClauses = ["acctstoptime IS NULL", "acctstarttime >= ?"];
    $params = [$cutoffTime];

    $search = trim($search);
    if ($search !== '') {
        $isIp  = (bool)preg_match('/^[0-9\.:]+$/', $search) && str_contains($search, '.');
        $isMac = (bool)preg_match('/^[0-9a-fA-F:\-\.]{4,}$/', $search) && (str_contains($search, ':') || str_contains($search, '-'));

        if ($isIp) {
            $whereClauses[] = "framedipaddress LIKE ?";
            $params[] = $search . '%';
        } elseif ($isMac) {
            $cleanSearch = str_replace([':', '-', '.'], '', $search);
            $whereClauses[] = "(callingstationid LIKE ? OR REPLACE(REPLACE(REPLACE(callingstationid, ':', ''), '-', ''), '.', '') LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $cleanSearch . '%';
        } else {
            $whereClauses[] = "username LIKE ?";
            $params[] = '%' . $search . '%';
        }
    }

    $whereSql = "WHERE " . implode(' AND ', $whereClauses);
    $stmt = $db->prepare("
        SELECT radacctid, acctsessionid, username, nasipaddress, framedipaddress,
               callingstationid, acctstarttime, acctupdatetime
        FROM radacct
        $whereSql
        ORDER BY radacctid DESC
    ");
    $stmt->execute($params);
    $rawSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 1. De-duplicate candidate open sessions per physical MAC (keep newest radacctid)
    $candidateSessionsByMac = [];
    foreach ($rawSessions as $sess) {
        $rawMac = $sess['callingstationid'] ?? '';
        $cleanMac = strtoupper(trim(str_replace([':', '-', '.'], '', $rawMac)));
        if ($cleanMac === '') {
            $candidateSessionsByMac[] = $sess;
            continue;
        }
        if (!isset($candidateSessionsByMac[$cleanMac])) {
            $candidateSessionsByMac[$cleanMac] = $sess;
        }
    }

    // 2. Check each candidate MAC against radacct to verify it has not stopped in a later session
    $cStmt = $db->prepare("SELECT radacctid, acctstoptime FROM radacct WHERE callingstationid = ? ORDER BY radacctid DESC LIMIT 1");
    $validSessions = [];
    $seenIps = [];

    foreach ($candidateSessionsByMac as $k => $sess) {
        if (is_string($k) && $k !== '') {
            $cStmt->execute([$sess['callingstationid']]);
            $latest = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($latest) {
                // If device's latest session stopped, or current session was superseded, it's NOT online right now!
                if (!empty($latest['acctstoptime'])) continue;
                if ((int)$latest['radacctid'] > (int)$sess['radacctid']) continue;
            }
        }

        $ip = trim($sess['framedipaddress'] ?? '');
        if ($ip !== '' && $ip !== '0.0.0.0') {
            if (isset($seenIps[$ip])) continue;
            $seenIps[$ip] = true;
        }

        $validSessions[] = $sess;
    }

    // 3. Group valid sessions by subscriber account (username)
    $accounts = [];
    foreach ($validSessions as $sess) {
        $u = $sess['username'];
        if (!isset($accounts[$u])) {
            $accounts[$u] = [
                'username'         => $u,
                'devices'          => [],
                'session_ids'      => [],
                'latest_starttime' => $sess['acctstarttime'],
                'primary_ip'       => $sess['framedipaddress'],
                'primary_mac'      => $sess['callingstationid'],
                'primary_nas'      => $sess['nasipaddress'],
            ];
        }

        $uptimeSec = max(0, $refNow - strtotime($sess['acctstarttime']));
        $sess['duration_sec'] = $uptimeSec;
        $sess['duration_text'] = formatDuration($uptimeSec);

        $accounts[$u]['devices'][] = $sess;
        $accounts[$u]['session_ids'][] = $sess['acctsessionid'];

        if (strtotime($sess['acctstarttime']) > strtotime($accounts[$u]['latest_starttime'])) {
            $accounts[$u]['latest_starttime'] = $sess['acctstarttime'];
            $accounts[$u]['primary_ip'] = $sess['framedipaddress'];
            $accounts[$u]['primary_mac'] = $sess['callingstationid'];
            $accounts[$u]['primary_nas'] = $sess['nasipaddress'];
        }
    }

    foreach ($accounts as &$acc) {
        $acc['device_count'] = count($acc['devices']);
        $acc['duration_text'] = formatDuration(max(0, $refNow - strtotime($acc['latest_starttime'])));
    }
    unset($acc);

    return [
        'ref_now'        => $refNow,
        'accounts'       => array_values($accounts),
        'total_accounts' => count($accounts),
        'total_devices'  => count($validSessions),
    ];
}

// Fetch genuinely active accounts & search results
$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $overview = getTrulyActiveOnlineAccounts($db);
    $totalOnlineAccounts = $overview['total_accounts'];
    $totalOnlineDevices  = $overview['total_devices'];

    $result = getTrulyActiveOnlineAccounts($db, $search);
    $allAccounts   = $result['accounts'];
    $filteredTotal = count($allAccounts);
} else {
    $result = getTrulyActiveOnlineAccounts($db);
    $allAccounts         = $result['accounts'];
    $totalOnlineAccounts = $result['total_accounts'];
    $totalOnlineDevices  = $result['total_devices'];
    $filteredTotal       = count($allAccounts);
}

// Pagination
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$pag     = paginate($filteredTotal, $page, $perPage);
$displayAccounts = array_slice($allAccounts, $pag['offset'], $pag['per_page']);

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
            <i class="bi bi-wifi me-1"></i> <?= number_format($totalOnlineAccounts) ?> Online Accounts (<?= number_format($totalOnlineDevices) ?> Devices)
        </span>
        <a href="reauth.php?refresh=1<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="btn btn-outline-secondary btn-sm" title="Refresh Active Sessions">
            <i class="bi bi-arrow-clockwise"></i>
        </a>
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
                <span class="badge bg-white text-danger fw-bold"><?= number_format($totalOnlineAccounts) ?> Online</span>
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
                            data-bs-toggle="modal" data-bs-target="#confirmAllModal" <?= $totalOnlineAccounts === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-power fs-5"></i>
                        <span>Disconnect All Online Accounts (<?= number_format($totalOnlineAccounts) ?>)</span>
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
                                <input type="text" name="username" id="userDisconnectName" class="form-control" placeholder="e.g. john.doe / voucher_user" required>
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
                    <i class="bi bi-list-check me-2 text-success"></i>Active Online Sessions (<?= number_format($filteredTotal) ?> Accounts)
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
                            <th>IP Address &amp; Devices</th>
                            <th>MAC Address</th>
                            <th>NAS Gateway</th>
                            <th>Online Since</th>
                            <th>Session Duration</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($displayAccounts)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                No active online sessions matching your search criteria.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($displayAccounts as $i => $acc): ?>
                        <?php
                            $isMulti = ($acc['device_count'] > 1);
                            $primaryDev = $acc['devices'][0];
                        ?>
                        <tr>
                            <td class="text-center">
                                <?php if ($isMulti): ?>
                                    <!-- Multi-device account checkbox: checks all child session inputs -->
                                    <input type="checkbox" class="form-check-input account-checkbox" data-account-idx="<?= $i ?>" title="Select all devices for this account">
                                    <div class="d-none">
                                        <?php foreach ($acc['devices'] as $d): ?>
                                        <input type="checkbox" name="session_ids[]" value="<?= htmlspecialchars($d['acctsessionid']) ?>" class="session-checkbox account-child-<?= $i ?>">
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <input type="checkbox" name="session_ids[]" value="<?= htmlspecialchars($primaryDev['acctsessionid']) ?>" class="form-check-input session-checkbox" title="Select this session">
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <a href="users.php?search=<?= urlencode($acc['username']) ?>" class="fw-bold text-decoration-none text-dark">
                                        <?= htmlspecialchars($acc['username']) ?>
                                    </a>
                                    <?php if ($isMulti): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 0.72rem;" title="<?= $acc['device_count'] ?> devices online concurrently">
                                            <i class="bi bi-devices me-0.5"></i><?= $acc['device_count'] ?> Devices
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($isMulti): ?>
                                    <!-- Multiple devices: Grouped Dropdown Menu -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle py-0.5 px-2 font-monospace d-inline-flex align-items-center gap-1"
                                                type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                                            <i class="bi bi-hdd-network"></i>
                                            <span><?= $acc['device_count'] ?> Connected Devices</span>
                                        </button>
                                        <div class="dropdown-menu shadow-lg p-2 border" style="min-width: 380px;">
                                            <div class="d-flex align-items-center justify-content-between px-2 py-1 text-primary fw-bold small border-bottom mb-2">
                                                <span><i class="bi bi-devices me-1"></i><?= htmlspecialchars($acc['username']) ?>'s Devices</span>
                                                <span class="badge bg-primary"><?= $acc['device_count'] ?> Active</span>
                                            </div>
                                            <?php foreach ($acc['devices'] as $d): ?>
                                            <div class="p-2 mb-1.5 rounded border bg-light-subtle">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($d['framedipaddress'] ?: '—') ?></span>
                                                    <span class="font-monospace small text-muted"><?= htmlspecialchars(formatMac($d['callingstationid'])) ?></span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between small text-secondary mb-1">
                                                    <span><i class="bi bi-router me-1"></i>NAS: <?= htmlspecialchars($d['nasipaddress'] ?: '—') ?></span>
                                                    <span class="text-success fw-semibold"><i class="bi bi-stopwatch me-1"></i><?= $d['duration_text'] ?></span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between small text-muted pt-1 border-top">
                                                    <span class="small">Since: <?= date('d M H:i', strtotime($d['acctstarttime'])) ?></span>
                                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;"
                                                            title="Disconnect only this device"
                                                            onclick="kickSingleSession('<?= htmlspecialchars(addslashes($d['acctsessionid'])) ?>', '<?= htmlspecialchars(addslashes($acc['username'])) ?>', '<?= htmlspecialchars(addslashes($d['nasipaddress'])) ?>', '<?= htmlspecialchars(addslashes($d['framedipaddress'])) ?>')">
                                                        <i class="bi bi-power me-0.5"></i>Disconnect Device
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?= htmlspecialchars($primaryDev['framedipaddress'] ?: '—') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small font-monospace text-muted">
                                <?php if ($isMulti): ?>
                                    <span class="text-secondary small">
                                        <i class="bi bi-cpu me-1"></i>Multiple MACs (<?= $acc['device_count'] ?>)
                                    </span>
                                <?php else: ?>
                                    <?= htmlspecialchars(formatMac($primaryDev['callingstationid'])) ?>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= htmlspecialchars($acc['primary_nas'] ?: '—') ?>
                                </span>
                            </td>
                            <td class="small text-secondary">
                                <?= date('d M H:i', strtotime($acc['latest_starttime'])) ?>
                            </td>
                            <td class="small fw-semibold text-success">
                                <i class="bi bi-stopwatch me-1"></i><?= $acc['duration_text'] ?>
                            </td>
                            <td class="text-end">
                                <?php if ($isMulti): ?>
                                    <button type="button" class="btn btn-sm btn-danger py-0.5 px-2.5"
                                            title="Force re-login on all devices for this user"
                                            onclick="kickUserAccount('<?= htmlspecialchars(addslashes($acc['username'])) ?>', <?= $acc['device_count'] ?>)">
                                        <i class="bi bi-power me-1"></i>Force Re-Login All (<?= $acc['device_count'] ?>)
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2.5"
                                            title="Force re-login for this session"
                                            onclick="kickSingleSession('<?= htmlspecialchars(addslashes($primaryDev['acctsessionid'])) ?>', '<?= htmlspecialchars(addslashes($acc['username'])) ?>', '<?= htmlspecialchars(addslashes($primaryDev['nasipaddress'])) ?>', '<?= htmlspecialchars(addslashes($primaryDev['framedipaddress'])) ?>')">
                                        <i class="bi bi-power me-1"></i>Force Re-Login
                                    </button>
                                <?php endif; ?>
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
        <span class="small text-muted">Showing <?= count($displayAccounts) ?> of <?= number_format($filteredTotal) ?> accounts</span>
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
                        This operation will <strong>disconnect <?= number_format($totalOnlineAccounts) ?> online accounts (<?= number_format($totalOnlineDevices) ?> devices)</strong> across the network immediately. Disconnect packets will be dispatched to all NAS gateways, and all users will be required to authenticate again to resume internet access.
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

<!-- Hidden Account Force Re-Login Form -->
<form method="POST" id="accountKickForm" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="disconnect_user">
    <input type="hidden" name="username" id="kickAccountUser">
</form>

<script>
// Checkbox management for batch disconnect & multi-device grouping
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const sessionCheckboxes = document.querySelectorAll('.session-checkbox');
    const accountCheckboxes = document.querySelectorAll('.account-checkbox');
    const batchBtn = document.getElementById('batchBtn');
    const selectedCount = document.getElementById('selectedCount');

    function updateSelected() {
        let count = 0;
        sessionCheckboxes.forEach(cb => { if (cb.checked) count++; });
        if (selectedCount) selectedCount.textContent = count;
        if (batchBtn) batchBtn.disabled = (count === 0);
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            sessionCheckboxes.forEach(cb => { cb.checked = checkAll.checked; });
            accountCheckboxes.forEach(cb => { cb.checked = checkAll.checked; });
            updateSelected();
        });
    }

    // Individual session checkboxes
    sessionCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelected);
    });

    // Multi-device account checkboxes
    accountCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const idx = this.dataset.accountIdx;
            const children = document.querySelectorAll('.account-child-' + idx);
            children.forEach(child => { child.checked = cb.checked; });
            updateSelected();
        });
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

function kickUserAccount(user, count) {
    const devMsg = count > 1 ? ` (${count} connected devices)` : '';
    if (confirm(`Are you sure you want to force re-login for account '${user}'${devMsg}? All connected devices for this account will be disconnected immediately and must log in again.`)) {
        document.getElementById('kickAccountUser').value = user;
        document.getElementById('accountKickForm').submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


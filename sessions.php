<?php
/**
 * CENDANA — Active Online Sessions
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Live authenticated subscriber sessions across Access Points and Gateways.
 */

require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'Active Sessions';
$db = getDB();

// Handle Session Termination Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'disconnect') {
        // Disconnect a single device session
        $sid   = sanitize($_POST['session_id'] ?? '');
        $user  = sanitize($_POST['username'] ?? '');
        $nasip = sanitize($_POST['nasip'] ?? '');
        $fip   = sanitize($_POST['framedip'] ?? '');

        if ($sid !== '') {
            terminateRadiusSession($sid, '', 'Admin-Disconnect');
            sendRadiusDisconnect($nasip, $user, $sid, $fip);
            auditLog('DISCONNECT_SESSION', $user, "Terminated active session $sid on NAS $nasip (IP: $fip)");
            flash('success', "Session for user <strong>" . htmlspecialchars($user) . "</strong> (IP: " . htmlspecialchars($fip) . ") has been disconnected.");
        }
        $redirectUrl = 'sessions.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : '');
        header('Location: ' . $redirectUrl);
        exit;

    } elseif ($action === 'disconnect_user') {
        // Disconnect all sessions for an account
        $user = sanitize($_POST['username'] ?? '');
        if ($user !== '') {
            $sessStmt = $db->prepare("SELECT acctsessionid, nasipaddress, framedipaddress FROM radacct WHERE username = ? AND acctstoptime IS NULL");
            $sessStmt->execute([$user]);
            $activeList = $sessStmt->fetchAll(PDO::FETCH_ASSOC);
            $count = terminateRadiusSession('', $user, 'Admin-Disconnect');

            foreach ($activeList as $row) {
                sendRadiusDisconnect($row['nasipaddress'] ?? '', $user, $row['acctsessionid'] ?? '', $row['framedipaddress'] ?? '');
            }

            auditLog('DISCONNECT_USER_ALL', $user, "Terminated all $count active session(s) for user $user");
            flash('success', "All <strong>" . number_format($count) . " session(s)</strong> for user <strong>" . htmlspecialchars($user) . "</strong> have been disconnected.");
        }
        header('Location: sessions.php');
        exit;

    } elseif ($action === 'purge_stale') {
        // Purge orphaned sessions older than 24h
        requireRole('superadmin');
        $count = purgeStaleRadiusSessions(24);
        unset($_SESSION['stale_ghost_count'], $_SESSION['stale_ghost_time']);
        auditLog('PURGE_STALE_GHOSTS', 'RADACCT', "Purged $count orphaned ghost session(s) older than 24 hours");
        flash('success', "<strong>Maintenance Complete:</strong> Successfully purged <strong>" . number_format($count) . " orphaned ghost session(s)</strong> older than 24 hours.");
        header('Location: sessions.php');
        exit;
    }
}

// Flash messages
$flash = getFlash();
$msg = $flash['msg'] ?? '';
$msgType = $flash['type'] ?? 'info';

// Count orphaned ghost sessions (cached for 300 seconds to eliminate repeated queries)
if (isset($_SESSION['stale_ghost_time']) && (time() - $_SESSION['stale_ghost_time'] < 300) && isset($_SESSION['stale_ghost_count'])) {
    $staleGhostCount = (int)$_SESSION['stale_ghost_count'];
} else {
    $staleGhostCount = countStaleRadiusSessions(24);
    $_SESSION['stale_ghost_count'] = $staleGhostCount;
    $_SESSION['stale_ghost_time'] = time();
}

// Release session lock immediately to prevent blocking concurrent requests or other tabs
session_write_close();

// Snapshot preview toggle (primarily for local dev inspection of historical dumps)
$allowSnapshot = !empty($_GET['snapshot']) || !empty($_GET['demo']);

// Search & Data Retrieval (single query, < 2ms)
$search = trim($_GET['q'] ?? '');
$result = getTrulyActiveOnlineAccounts($db, '', $allowSnapshot);
$allAccounts         = $result['accounts'];
$totalOnlineAccounts = $result['total_accounts'];
$totalOnlineDevices  = $result['total_devices'];

// Instant in-memory filter (< 0.1ms) when search query is present
if ($search !== '') {
    $s = strtolower($search);
    $cleanMacSearch = str_replace([':', '-', '.'], '', $s);
    $allAccounts = array_values(array_filter($allAccounts, function($acc) use ($s, $cleanMacSearch) {
        if (str_contains(strtolower($acc['username']), $s)) return true;
        foreach ($acc['devices'] as $d) {
            if (str_contains(strtolower($d['framedipaddress'] ?? ''), $s)) return true;
            $mac = strtolower($d['callingstationid'] ?? '');
            if (str_contains($mac, $s)) return true;
            if ($cleanMacSearch !== '' && str_contains(str_replace([':', '-', '.'], '', $mac), $cleanMacSearch)) return true;
        }
        return false;
    }));
}
$filteredTotal = count($allAccounts);

// Pagination
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$pag     = paginate($filteredTotal, $page, $perPage);
$displayAccounts = array_slice($allAccounts, $pag['offset'], $pag['per_page']);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="welcome.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Active Sessions</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1"><i class="bi bi-activity text-success me-2"></i>Active Online Sessions</h4>
        <p class="text-secondary small mb-0">Live authenticated subscribers currently connected across Access Points and Gateways.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
            <i class="bi bi-people-fill me-1"></i> <?= number_format($totalOnlineAccounts) ?> Online Accounts (<?= number_format($totalOnlineDevices) ?> Devices)
        </span>
        <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Active Sessions">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
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

<!-- Maintenance Banner for Stale Ghost Sessions -->
<?php if ($staleGhostCount > 0 && hasRole('superadmin')): ?>
<div class="card border-warning border-opacity-50 bg-warning bg-opacity-10 mb-4 shadow-sm">
    <div class="card-body p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning text-dark p-2 fs-4 d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px;">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark">Database Maintenance: <?= number_format($staleGhostCount) ?> Orphaned Ghost Session(s) Detected</h6>
                <div class="text-secondary small">These accounting rows have no stop time but have been inactive for over 24 hours (devices disconnected without sending <code>Acct-Stop</code>). They are excluded from this active list, but can be cleanly closed in the database.</div>
            </div>
        </div>
        <form method="POST" class="m-0 flex-shrink-0" onsubmit="return confirm('Purge and cleanly close all <?= number_format($staleGhostCount) ?> orphaned ghost sessions older than 24 hours?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="purge_stale">
            <button type="submit" class="btn btn-warning btn-sm fw-bold px-3 py-2 text-dark shadow-sm">
                <i class="bi bi-trash3-fill me-1"></i>Purge Orphaned Sessions (<?= number_format($staleGhostCount) ?>)
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Access Point Telemetry & Timezone Notice -->
<div class="alert alert-light border shadow-sm py-2 px-3 small d-flex flex-wrap align-items-center justify-content-between gap-2 text-secondary mb-3">
    <div>
        <i class="bi bi-info-circle-fill text-primary me-1"></i>
        <strong>Network Telemetry Notice:</strong> Access Points (Ruijie/MikroTik) report bandwidth counters and refreshed DHCP IP leases periodically via RADIUS Interim-Updates (5-minute interval). Sessions in their initial handshake show <em>Awaiting DHCP</em> or <em>Buffering</em> until the first AP telemetry packet arrives.
    </div>
    <div class="font-monospace small text-muted">
        Server Timezone: <strong>Asia/Jakarta (WIB)</strong>
    </div>
</div>

<!-- Search Card -->
<div class="card shadow-sm border mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Filter by Username, IP (172.16...), or MAC address..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($search !== ''): ?>
                <a href="sessions.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col text-end text-muted small d-none d-md-block">
                <span>Showing <strong><?= count($displayAccounts) ?></strong> of <strong><?= number_format($filteredTotal) ?></strong> online account(s)</span>
            </div>
        </form>
    </div>
</div>

<!-- Active Online Sessions Table -->
<div class="card shadow-sm border">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-dark"><i class="bi bi-hdd-network text-success me-2"></i>Online Accounts &amp; Devices</span>
            <span class="badge bg-light text-muted border"><?= number_format($filteredTotal) ?> Online</span>
        </div>
        <div class="small text-muted">
            <i class="bi bi-clock-history me-1"></i>Auto-refreshes every 30s
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 140px;">Subscriber / Account</th>
                        <th>Device IP &amp; Multiple Connections</th>
                        <th>MAC / Station ID &amp; SSID</th>
                        <th>NAS Gateway</th>
                        <th>Duration / Online Since</th>
                        <th>Traffic (Up / Down)</th>
                        <th class="text-end" style="min-width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($displayAccounts)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-wifi-off fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <?= $search !== '' ? 'No active online accounts matching your search.' : 'No active online sessions right now.' ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($displayAccounts as $acc): ?>
                    <?php
                        $isMulti = ($acc['device_count'] > 1);
                        $primaryDev = $acc['devices'][0];
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                    <i class="bi bi-person-fill small"></i>
                                </span>
                                <div>
                                    <a href="users.php?search=<?= urlencode($acc['username']) ?>" class="fw-bold text-decoration-none text-dark">
                                        <?= htmlspecialchars($acc['username']) ?>
                                    </a>
                                    <?php if ($isMulti): ?>
                                    <div class="mt-0.5">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 0.70rem;">
                                            <i class="bi bi-devices me-0.5"></i><?= $acc['device_count'] ?> Devices Online
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($isMulti): ?>
                                <!-- Multiple Devices Dropdown Menu -->
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle py-1 px-2.5 font-monospace d-inline-flex align-items-center gap-1.5 shadow-sm"
                                            type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                                        <i class="bi bi-hdd-network text-primary"></i>
                                        <span><?= $acc['device_count'] ?> Connected Devices</span>
                                    </button>
                                    <div class="dropdown-menu shadow-lg p-3 border" style="min-width: 440px;">
                                        <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                                            <span class="fw-bold text-dark small"><i class="bi bi-devices me-1 text-primary"></i><?= htmlspecialchars($acc['username']) ?>'s Active Devices</span>
                                            <span class="badge bg-primary"><?= $acc['device_count'] ?> Online</span>
                                        </div>
                                        <?php foreach ($acc['devices'] as $d): ?>
                                        <div class="p-2 mb-2 rounded border bg-light-subtle">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <div>
                                                    <?php if (!empty($d['resolved_ip'])): ?>
                                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($d['resolved_ip']) ?></span>
                                                        <?php if ($d['ip_source'] !== 'live'): ?>
                                                            <span class="badge bg-info-subtle text-info border border-info-subtle small ms-1" title="Resolved from recent DHCP lease">DHCP</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace" title="Initial 802.1X handshake; awaiting 1st 5-minute AP DHCP update"><i class="bi bi-hourglass-split me-1"></i>Awaiting DHCP</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="font-monospace small text-dark fw-semibold" title="Device Hardware MAC"><?= htmlspecialchars(formatMac($d['callingstationid'])) ?></span>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between small text-secondary mb-1">
                                                <span><i class="bi bi-router me-1"></i>NAS: <?= htmlspecialchars($d['nasipaddress'] ?: '—') ?></span>
                                                <span class="text-success fw-semibold"><i class="bi bi-stopwatch me-1"></i><?= $d['duration_text'] ?></span>
                                            </div>
                                            <?php if (!empty($d['ssid'])): ?>
                                            <div class="small text-muted mb-1">
                                                <i class="bi bi-wifi me-1 text-primary"></i>SSID: <strong><?= htmlspecialchars($d['ssid']) ?></strong>
                                            </div>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center justify-content-between small text-muted mb-1.5">
                                                <?php if (($d['acctinputoctets'] ?? 0) > 0 || ($d['acctoutputoctets'] ?? 0) > 0): ?>
                                                    <span><i class="bi bi-arrow-up-down me-1"></i>↑ <?= formatBytes($d['acctinputoctets'] ?? 0) ?> | ↓ <?= formatBytes($d['acctoutputoctets'] ?? 0) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border" style="font-size:0.68rem;"><i class="bi bi-clock-history me-1"></i>Buffering (&lt; 5m)</span>
                                                <?php endif; ?>
                                                <span class="small">Since: <?= date('d M H:i:s', strtotime($d['acctstarttime'])) ?> WIB</span>
                                            </div>
                                            <div class="text-end pt-1 border-top">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Disconnect this specific device (<?= htmlspecialchars(addslashes($d['resolved_ip'] ?: formatMac($d['callingstationid']))) ?>)?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="disconnect">
                                                    <input type="hidden" name="session_id" value="<?= htmlspecialchars($d['acctsessionid']) ?>">
                                                    <input type="hidden" name="username" value="<?= htmlspecialchars($acc['username']) ?>">
                                                    <input type="hidden" name="nasip" value="<?= htmlspecialchars($d['nasipaddress']) ?>">
                                                    <input type="hidden" name="framedip" value="<?= htmlspecialchars($d['resolved_ip'] ?: $d['framedipaddress']) ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0.5 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-power me-1"></i>Disconnect Device
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <div class="pt-1 text-center">
                                            <form method="POST" onsubmit="return confirm('Disconnect ALL <?= $acc['device_count'] ?> devices for account <?= htmlspecialchars(addslashes($acc['username'])) ?>?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="disconnect_user">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($acc['username']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm w-100 py-1">
                                                    <i class="bi bi-plug me-1"></i>Disconnect All <?= $acc['device_count'] ?> Devices
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php if (!empty($primaryDev['resolved_ip'])): ?>
                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                        <i class="bi bi-broadcast me-1 text-success"></i><?= htmlspecialchars($primaryDev['resolved_ip']) ?>
                                    </span>
                                    <?php if ($primaryDev['ip_source'] !== 'live'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle small ms-1" title="Resolved from recent DHCP lease for this device MAC">DHCP</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace px-2 py-1" title="Connected to 802.1X; awaiting AP 5-minute interim DHCP update">
                                        <i class="bi bi-hourglass-split me-1"></i>Awaiting DHCP
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isMulti): ?>
                                <span class="text-secondary small font-monospace"><i class="bi bi-layers me-1"></i>Multiple MACs (<?= $acc['device_count'] ?>)</span>
                            <?php else: ?>
                                <span class="font-monospace text-dark small fw-semibold" title="Device Hardware MAC"><?= htmlspecialchars(formatMac($primaryDev['callingstationid'])) ?></span>
                                <?php if (!empty($primaryDev['ssid'])): ?>
                                <div class="text-muted small" style="font-size: 0.74rem;"><i class="bi bi-wifi me-1 text-primary"></i>SSID: <?= htmlspecialchars($primaryDev['ssid']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <i class="bi bi-router me-1"></i><?= htmlspecialchars($acc['primary_nas'] ?: '—') ?>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark"><i class="bi bi-stopwatch me-1 text-success"></i><?= $acc['duration_text'] ?></div>
                            <div class="text-muted small" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i><?= date('d/m/y H:i:s', strtotime($acc['latest_starttime'])) ?> WIB</div>
                        </td>
                        <td class="small">
                            <?php if ($acc['total_input'] > 0 || $acc['total_output'] > 0): ?>
                                <div><i class="bi bi-arrow-up text-primary me-1"></i><?= formatBytes($acc['total_input']) ?></div>
                                <div><i class="bi bi-arrow-down text-info me-1"></i><?= formatBytes($acc['total_output']) ?></div>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border small py-1 px-2" title="Access Point reports cumulative bytes on 5-minute interim update intervals">
                                    <i class="bi bi-clock-history me-1"></i>Buffering (&lt; 5m)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($isMulti): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Disconnect all <?= $acc['device_count'] ?> devices for account <?= htmlspecialchars(addslashes($acc['username'])) ?>?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="disconnect_user">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($acc['username']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5 shadow-sm" title="Disconnect All Devices">
                                        <i class="bi bi-power me-1"></i>Kick All
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Disconnect user <?= htmlspecialchars(addslashes($acc['username']), ENT_QUOTES) ?>?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="disconnect">
                                    <input type="hidden" name="session_id" value="<?= htmlspecialchars($primaryDev['acctsessionid']) ?>">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($acc['username']) ?>">
                                    <input type="hidden" name="nasip" value="<?= htmlspecialchars($primaryDev['nasipaddress']) ?>">
                                    <input type="hidden" name="framedip" value="<?= htmlspecialchars($primaryDev['framedipaddress']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5 shadow-sm">
                                        <i class="bi bi-power me-1"></i>Kick
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pag['total_pages'] > 1): ?>
        <div class="card-footer bg-white d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 py-3 border-top">
            <span class="text-muted small">Showing <?= $pag['offset'] + 1 ?>–<?= min($filteredTotal, $pag['offset'] + $pag['per_page']) ?> of <?= number_format($filteredTotal) ?> online accounts</span>
            <?php
            $pageParams = array_filter(['q' => $search]);
            $pageBaseUrl = 'sessions.php' . (!empty($pageParams) ? '?' . http_build_query($pageParams) : '');
            ?>
            <?= paginationLinks($pag, $pageBaseUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$extra_js = '<script>
// Auto-refresh active sessions every 30 seconds unless typing in search
let reloadTimer = setTimeout(() => {
    const activeEl = document.activeElement;
    if (!activeEl || activeEl.tagName !== "INPUT") {
        location.reload();
    }
}, 30000);
</script>';
include __DIR__ . '/includes/footer.php';

<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Accounting';
$db = getDB();

$search       = trim($_GET['q'] ?? '');
$ipSearch     = trim($_GET['ip'] ?? '');
$statusFilter = trim($_GET['status'] ?? ''); // '', 'traffic', 'active', 'completed'
$sortBy       = trim($_GET['sort'] ?? 'start_desc'); // 'start_desc', 'traffic_desc', 'duration_desc', 'start_asc'

if (isset($_GET['from'])) {
    $dateFrom = trim($_GET['from']);
    $dateTo   = trim($_GET['to'] ?? date('Y-m-d'));
} else {
    // Default to Today for instant high-speed page load (< 10ms)
    $today = date('Y-m-d');
    $hasToday = $db->query("SELECT 1 FROM radacct WHERE acctstarttime >= '$today 00:00:00' LIMIT 1")->fetch();
    if ($hasToday) {
        $dateFrom = $today;
        $dateTo   = $today;
    } else {
        $latest = $db->query("SELECT DATE(acctstarttime) d FROM radacct ORDER BY radacctid DESC LIMIT 1")->fetch();
        if ($latest && !empty($latest['d'])) {
            $dateFrom = $latest['d'];
            $dateTo   = $latest['d'];
        } else {
            $dateFrom = $today;
            $dateTo   = $today;
        }
    }
}

// Optimized index-friendly date condition (avoids DATE() wrapper function on acctstarttime)
$whereClauses = ["acctstarttime >= :from_dt", "acctstarttime <= :to_dt"];
$params = [
    ':from_dt' => "$dateFrom 00:00:00",
    ':to_dt'   => "$dateTo 23:59:59"
];

if ($search !== '') {
    $whereClauses[] = "username LIKE :q";
    $params[':q'] = "%$search%";
}

if ($ipSearch !== '') {
    $cleanIpSearch = str_replace([':', '-', '.'], '', $ipSearch);
    $whereClauses[] = "(framedipaddress LIKE :ip OR callingstationid LIKE :mac OR REPLACE(REPLACE(REPLACE(callingstationid, ':', ''), '-', ''), '.', '') LIKE :clean_mac)";
    $params[':ip']        = "%$ipSearch%";
    $params[':mac']       = "%$ipSearch%";
    $params[':clean_mac'] = "%$cleanIpSearch%";
}

if ($statusFilter === 'traffic') {
    // Only show sessions with real data traffic
    $whereClauses[] = "(acctinputoctets > 0 OR acctoutputoctets > 0)";
} elseif ($statusFilter === 'active') {
    // Only show sessions that are active right now (within last 24h and no stop time)
    $whereClauses[] = "acctstoptime IS NULL AND acctstarttime >= NOW() - INTERVAL 24 HOUR";
} elseif ($statusFilter === 'completed') {
    // Only show completed sessions with stop time
    $whereClauses[] = "acctstoptime IS NOT NULL";
}

$where = "WHERE " . implode(' AND ', $whereClauses);

// Determine SQL Sorting
switch ($sortBy) {
    case 'traffic_desc':
        $orderSql = "ORDER BY (COALESCE(acctinputoctets, 0) + COALESCE(acctoutputoctets, 0)) DESC, radacctid DESC";
        break;
    case 'duration_desc':
        $orderSql = "ORDER BY acctsessiontime DESC, radacctid DESC";
        break;
    case 'start_asc':
        $orderSql = "ORDER BY acctstarttime ASC, radacctid ASC";
        break;
    case 'start_desc':
    default:
        $sortBy   = 'start_desc';
        $orderSql = "ORDER BY acctstarttime DESC, radacctid DESC";
        break;
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

// Cache summary statistics in session for 120s to eliminate repeated heavy SUM() scans
$cacheKey = 'acct_sum_' . md5($where . serialize($params));
if (isset($_SESSION[$cacheKey]) && (time() - ($_SESSION[$cacheKey . '_time'] ?? 0) < 120)) {
    $summary = $_SESSION[$cacheKey];
} else {
    $statsStmt = $db->prepare("SELECT COUNT(*) AS sessions,
        COALESCE(SUM(acctinputoctets), 0) AS upload,
        COALESCE(SUM(acctoutputoctets), 0) AS download,
        COALESCE(SUM(acctsessiontime), 0) AS duration
      FROM radacct $where");
    $statsStmt->execute($params);
    $summary = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'sessions' => 0, 'upload' => 0, 'download' => 0, 'duration' => 0
    ];
    $_SESSION[$cacheKey] = $summary;
    $_SESSION[$cacheKey . '_time'] = time();
}
$total = (int)($summary['sessions'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));

// Release session lock immediately to prevent blocking concurrent requests or other tabs
session_write_close();

$lim = (int)$perPage;
$off = (int)$offset;

$stmt = $db->prepare("SELECT radacctid, username, nasipaddress, framedipaddress, callingstationid,
    acctstarttime, acctstoptime, acctsessiontime,
    acctinputoctets, acctoutputoctets, acctterminatecause
  FROM radacct $where
  $orderSql LIMIT $lim OFFSET $off");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// High-speed batch IP resolution for the 25 records on current page
$macsNeedingIp = [];
foreach ($records as $r) {
    $ip = trim($r['framedipaddress'] ?? '');
    $mac = trim($r['callingstationid'] ?? '');
    if (($ip === '' || $ip === '0.0.0.0') && $mac !== '') {
        $cleanM = strtoupper(str_replace([':', '-', '.'], '', $mac));
        $macsNeedingIp[$cleanM] = $mac;
    }
}

$resolvedIps = [];
if (!empty($macsNeedingIp)) {
    // 1. Check radippool (instant indexed lookup, < 0.2ms)
    if (dbTableExists('radippool')) {
        $phPool = implode(',', array_fill(0, count($macsNeedingIp), '?'));
        $poolStmt = $db->prepare("SELECT callingstationid, framedipaddress FROM radippool WHERE callingstationid IN ($phPool) AND framedipaddress != '0.0.0.0' AND framedipaddress != ''");
        $poolStmt->execute(array_values($macsNeedingIp));
        while ($pRow = $poolStmt->fetch(PDO::FETCH_ASSOC)) {
            $cM = strtoupper(str_replace([':', '-', '.'], '', $pRow['callingstationid']));
            $resolvedIps[$cM] = ['ip' => $pRow['framedipaddress'], 'source' => 'radippool'];
        }
    }
    // 2. Check recent radacct session with non-zero IP for remaining MACs (fast indexed point lookups)
    $remaining = array_diff_key($macsNeedingIp, $resolvedIps);
    if (!empty($remaining) && count($remaining) <= 25) {
        $recStmt = $db->prepare("
            SELECT framedipaddress
            FROM radacct
            WHERE callingstationid = ? AND framedipaddress != '0.0.0.0' AND framedipaddress != '' AND acctstarttime >= NOW() - INTERVAL 14 DAY
            ORDER BY radacctid DESC LIMIT 1
        ");
        foreach ($remaining as $cleanM => $rawM) {
            $recStmt->execute([$rawM]);
            $foundIp = $recStmt->fetchColumn();
            if ($foundIp) {
                $resolvedIps[$cleanM] = ['ip' => $foundIp, 'source' => 'recent_lease'];
            }
        }
    }
}

$nowTs = time();

include __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="welcome.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Accounting</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-primary"></i>Accounting Records</h4>
        <p class="text-secondary small mb-0">Historical subscriber sessions and bandwidth consumption records from FreeRADIUS.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="export.php?type=accounting&from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $ipSearch ? '&ip=' . urlencode($ipSearch) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $sortBy ? '&sort=' . urlencode($sortBy) : '' ?>" class="btn btn-outline-success btn-sm px-3 shadow-sm" style="border-radius: 8px;">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1 fw-semibold text-secondary">Username</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0"
                           placeholder="Search username..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small mb-1 fw-semibold text-secondary">IP / MAC Address</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-ethernet text-muted"></i></span>
                    <input type="text" name="ip" class="form-control border-start-0"
                           placeholder="e.g. 172.16.x or MAC..." value="<?= htmlspecialchars($ipSearch) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 fw-semibold text-secondary">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 fw-semibold text-secondary">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-6 col-md-1.5">
                <label class="form-label small mb-1 fw-semibold text-secondary">Status / Filter</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="" <?= ($statusFilter === '') ? 'selected' : '' ?>>All Records</option>
                    <option value="traffic" <?= ($statusFilter === 'traffic') ? 'selected' : '' ?>>With Traffic (> 0 B)</option>
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active / Online</option>
                    <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-6 col-md-1.5">
                <label class="form-label small mb-1 fw-semibold text-secondary">Order By</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="start_desc" <?= ($sortBy === 'start_desc') ? 'selected' : '' ?>>Newest First</option>
                    <option value="traffic_desc" <?= ($sortBy === 'traffic_desc') ? 'selected' : '' ?>>Highest Traffic</option>
                    <option value="duration_desc" <?= ($sortBy === 'duration_desc') ? 'selected' : '' ?>>Longest Duration</option>
                    <option value="start_asc" <?= ($sortBy === 'start_asc') ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-1 mt-2">
                <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm" style="border-radius: 6px;">
                    <i class="bi bi-funnel me-1"></i>Apply Filter
                </button>
                <a href="accounting.php" class="btn btn-outline-secondary btn-sm px-2" style="border-radius: 6px;" title="Reset filters">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>

        <!-- Quick Date & Status Presets -->
        <div class="d-flex flex-wrap align-items-center gap-1.5 mt-2.5 pt-2 border-top">
            <span class="text-secondary small me-2" style="font-size: .78rem;"><i class="bi bi-lightning-charge me-1"></i>Quick Presets:</span>
            <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>&status=traffic&sort=<?= urlencode($sortBy) ?>" class="badge <?= ($dateFrom === date('Y-m-d') && $dateTo === date('Y-m-d') && $statusFilter === 'traffic') ? 'bg-primary text-white' : 'bg-light text-secondary border' ?> text-decoration-none px-2 py-1">Today With Traffic</a>
            <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>&sort=traffic_desc" class="badge <?= ($dateFrom === date('Y-m-d') && $dateTo === date('Y-m-d') && $sortBy === 'traffic_desc') ? 'bg-primary text-white' : 'bg-light text-secondary border' ?> text-decoration-none px-2 py-1">Today Top Bandwidth</a>
            <a href="?from=<?= date('Y-m-d', strtotime('-1 day')) ?>&to=<?= date('Y-m-d', strtotime('-1 day')) ?>&status=traffic" class="badge <?= ($dateFrom === date('Y-m-d', strtotime('-1 day')) && $dateTo === date('Y-m-d', strtotime('-1 day'))) ? 'bg-primary text-white' : 'bg-light text-secondary border' ?> text-decoration-none px-2 py-1">Yesterday</a>
            <a href="?from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= date('Y-m-d') ?>&status=traffic" class="badge <?= ($dateFrom === date('Y-m-d', strtotime('-6 days'))) ? 'bg-primary text-white' : 'bg-light text-secondary border' ?> text-decoration-none px-2 py-1">Last 7 Days</a>
            <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="badge <?= ($dateFrom === date('Y-m-01') && $dateTo === date('Y-m-d') && $statusFilter === '') ? 'bg-primary text-white' : 'bg-light text-secondary border' ?> text-decoration-none px-2 py-1">This Month</a>
        </div>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff"><i class="bi bi-collection text-primary"></i></div>
            <div>
                <div class="stat-value"><?= number_format((int)($summary['sessions'] ?? 0)) ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4"><i class="bi bi-arrow-up-circle text-success"></i></div>
            <div>
                <div class="stat-value" style="font-size:1.15rem"><?= formatBytes($summary['upload'] ?? 0) ?></div>
                <div class="stat-label">Total Upload Volume</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fefce8"><i class="bi bi-arrow-down-circle text-warning"></i></div>
            <div>
                <div class="stat-value" style="font-size:1.15rem"><?= formatBytes($summary['download'] ?? 0) ?></div>
                <div class="stat-label">Total Download Volume</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fdf4ff"><i class="bi bi-stopwatch" style="color:#9333ea"></i></div>
            <div>
                <div class="stat-value" style="font-size:1.15rem"><?= formatDuration($summary['duration'] ?? 0) ?></div>
                <div class="stat-label">Aggregated Duration</div>
            </div>
        </div>
    </div>
</div>

<!-- Accounting Data Table -->
<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="bg-light">
                <tr class="small text-secondary text-uppercase" style="font-size:.73rem; letter-spacing:0.04em;">
                    <th class="ps-3 py-3">Subscriber</th>
                    <th>NAS Gateway</th>
                    <th>Client IP & Device MAC</th>
                    <th>Session Start</th>
                    <th>Session Stop</th>
                    <th>Duration</th>
                    <th>Upload</th>
                    <th>Download</th>
                    <th class="pe-3">Status / Cause</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                    No accounting records found matching the selected filter criteria.
                </td>
            </tr>
            <?php else: foreach ($records as $r): ?>
            <?php
                $rawIp = trim($r['framedipaddress'] ?? '');
                $rawMac = trim($r['callingstationid'] ?? '');
                $cleanMac = strtoupper(str_replace([':', '-', '.'], '', $rawMac));
                $formattedMac = formatMac($rawMac);

                $startSec = strtotime($r['acctstarttime']);
                $isActiveRecent = empty($r['acctstoptime']) && ($startSec >= $nowTs - 86400);
                $isOrphanedOld  = empty($r['acctstoptime']) && ($startSec < $nowTs - 86400);

                $inBytes  = (float)($r['acctinputoctets'] ?? 0);
                $outBytes = (float)($r['acctoutputoctets'] ?? 0);
                $duration = (int)($r['acctsessiontime'] ?? 0);
            ?>
            <tr>
                <!-- Username -->
                <td class="ps-3">
                    <a href="user-edit.php?user=<?= urlencode($r['username']) ?>" class="fw-semibold text-decoration-none text-dark" title="View subscriber profile">
                        <?= sanitize($r['username']) ?>
                    </a>
                </td>

                <!-- NAS Gateway -->
                <td>
                    <span class="font-monospace small text-secondary" style="font-size:.8rem;">
                        <?= sanitize($r['nasipaddress'] ?? '—') ?>
                    </span>
                </td>

                <!-- Client IP & Device MAC -->
                <td>
                    <?php if ($rawIp !== '' && $rawIp !== '0.0.0.0'): ?>
                        <code class="font-monospace text-dark px-1.5 py-0.5 rounded" style="background:#f1f5f9; font-size:.8rem;"><?= htmlspecialchars($rawIp) ?></code>
                    <?php elseif (isset($resolvedIps[$cleanMac])): ?>
                        <code class="font-monospace text-primary px-1.5 py-0.5 rounded" style="background:#eff6ff; font-size:.8rem;" title="Resolved from DHCP lease table"><?= htmlspecialchars($resolvedIps[$cleanMac]['ip']) ?></code>
                        <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:.65rem;" title="Resolved via recent DHCP lease">DHCP</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-1.5 py-0.5" style="font-size:.72rem;" title="Device authenticated via 802.1X; pending DHCP IP lease">
                            <i class="bi bi-hourglass-split me-1"></i>DHCP Pending
                        </span>
                    <?php endif; ?>

                    <?php if ($formattedMac): ?>
                        <div class="text-secondary small font-monospace mt-1" style="font-size:.72rem;" title="Client Device MAC / Station ID">
                            <i class="bi bi-laptop me-1 text-muted"></i><?= htmlspecialchars($formattedMac) ?>
                        </div>
                    <?php endif; ?>
                </td>

                <!-- Session Start (with seconds) -->
                <td>
                    <span class="text-secondary small" style="font-size:.82rem;">
                        <?= date('d/m/y H:i:s', $startSec) ?>
                    </span>
                </td>

                <!-- Session Stop -->
                <td>
                    <?php if (!empty($r['acctstoptime'])): ?>
                        <span class="text-secondary small" style="font-size:.82rem;">
                            <?= date('d/m/y H:i:s', strtotime($r['acctstoptime'])) ?>
                        </span>
                    <?php elseif ($isActiveRecent): ?>
                        <span class="badge badge-online px-2 py-1 shadow-sm" style="font-size:.72rem;">
                            <i class="bi bi-broadcast me-1"></i>Online
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1" style="font-size:.72rem;" title="Session ended without NAS transmitting an Accounting-Stop packet">
                            No Stop Packet
                        </span>
                    <?php endif; ?>
                </td>

                <!-- Duration -->
                <td>
                    <?php if ($duration > 0): ?>
                        <span class="fw-semibold text-dark small" style="font-size:.82rem;">
                            <?= formatDuration($duration) ?>
                        </span>
                    <?php elseif ($isActiveRecent): ?>
                        <?php $liveSec = max(0, $nowTs - $startSec); ?>
                        <span class="text-success fw-semibold small" style="font-size:.82rem;">
                            <?= formatDuration($liveSec) ?>
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:.65rem;">live</span>
                    <?php else: ?>
                        <span class="text-muted small">0s</span>
                    <?php endif; ?>
                </td>

                <!-- Upload -->
                <td>
                    <?php if ($inBytes > 0): ?>
                        <span class="fw-medium text-primary small" style="font-size:.82rem;"><?= formatBytes($inBytes) ?></span>
                    <?php elseif ($isActiveRecent): ?>
                        <span class="text-muted small" style="font-size:.82rem;">0 B <span class="badge bg-light text-secondary border ms-0.5" style="font-size:.65rem;" title="Session in progress; data accumulates upon Interim-Update or Stop">Starting</span></span>
                    <?php else: ?>
                        <span class="text-muted small" style="font-size:.82rem;">0 B</span>
                    <?php endif; ?>
                </td>

                <!-- Download -->
                <td>
                    <?php if ($outBytes > 0): ?>
                        <span class="fw-medium text-success small" style="font-size:.82rem;"><?= formatBytes($outBytes) ?></span>
                    <?php elseif ($isActiveRecent): ?>
                        <span class="text-muted small" style="font-size:.82rem;">0 B <span class="badge bg-light text-secondary border ms-0.5" style="font-size:.65rem;" title="Session in progress; data accumulates upon Interim-Update or Stop">Starting</span></span>
                    <?php else: ?>
                        <span class="text-muted small" style="font-size:.82rem;">0 B</span>
                    <?php endif; ?>
                </td>

                <!-- Terminate Cause / Status -->
                <td class="pe-3">
                    <?php $cause = trim($r['acctterminatecause'] ?? ''); ?>
                    <?php if ($cause !== ''): ?>
                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size:.72rem;">
                            <?= sanitize($cause) ?>
                        </span>
                    <?php elseif ($isActiveRecent): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.72rem;">
                            <i class="bi bi-check-circle me-1"></i>Connected
                        </span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted border px-2 py-1" style="font-size:.72rem;">
                            Unrecorded
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white border-top d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 py-3 px-3">
        <span class="small text-muted">
            Showing <?= number_format($offset + 1) ?> to <?= number_format(min($total, $offset + $perPage)) ?> of <?= number_format($total) ?> records | Page <?= $page ?> of <?= $pages ?>
        </span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                    $navQuery = "?q=" . urlencode($search) . "&ip=" . urlencode($ipSearch) . "&from=" . urlencode($dateFrom) . "&to=" . urlencode($dateTo) . "&status=" . urlencode($statusFilter) . "&sort=" . urlencode($sortBy);
                ?>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $navQuery ?>&page=1" title="First Page">&laquo;</a>
                </li>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $navQuery ?>&page=<?= max(1, $page - 1) ?>" title="Previous Page">&lsaquo;</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $navQuery ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $navQuery ?>&page=<?= min($pages, $page + 1) ?>" title="Next Page">&rsaquo;</a>
                </li>
                <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $navQuery ?>&page=<?= $pages ?>" title="Last Page">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

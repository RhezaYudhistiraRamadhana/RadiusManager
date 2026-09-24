<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'System Reports';
$current_page = 'reports';
$db = getDB();

// ── Date Range Parameters ──────────────────────────────────────────────────
$preset = $_GET['preset'] ?? '';
$today = date('Y-m-d');

if ($preset === 'last_month') {
    $dateFrom = date('Y-m-01', strtotime('first day of last month'));
    $dateTo   = date('Y-m-t', strtotime('last day of last month'));
} elseif ($preset === 'last_30_days') {
    $dateFrom = date('Y-m-d', strtotime('-29 days'));
    $dateTo   = $today;
} elseif ($preset === 'this_year') {
    $dateFrom = date('Y-01-01');
    $dateTo   = $today;
} elseif (!empty($_GET['from']) && !empty($_GET['to'])) {
    $dateFrom = trim($_GET['from']);
    $dateTo   = trim($_GET['to']);
} else {
    // Default: Current month to date
    $preset   = 'this_month';
    $dateFrom = date('Y-m-01');
    $dateTo   = $today;
}

$startTs = "$dateFrom 00:00:00";
$endTs   = "$dateTo 23:59:59";

// ── Check if radacct has sessions in this window ──────────────────────────
$latestAcctDate = $db->query("SELECT DATE(acctstarttime) d FROM radacct ORDER BY acctstarttime DESC LIMIT 1")->fetchColumn();
$noDataInSelectedRange = false;

// ── 1. Overall Summary KPIs ───────────────────────────────────────────────
$kpiStmt = $db->prepare("
    SELECT COUNT(*) AS total_sessions,
           COUNT(DISTINCT username) AS unique_users,
           COALESCE(SUM(acctinputoctets), 0) AS total_upload,
           COALESCE(SUM(acctoutputoctets), 0) AS total_download,
           COALESCE(SUM(acctsessiontime), 0) AS total_duration
    FROM radacct
    WHERE acctstarttime >= ? AND acctstarttime <= ?
");
$kpiStmt->execute([$startTs, $endTs]);
$kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

$totalSessions = (int)($kpis['total_sessions'] ?? 0);
$uniqueUsers   = (int)($kpis['unique_users'] ?? 0);
$totalUpload   = (float)($kpis['total_upload'] ?? 0);
$totalDownload = (float)($kpis['total_download'] ?? 0);
$totalTraffic  = $totalUpload + $totalDownload;
$totalDuration = (int)($kpis['total_duration'] ?? 0);
$avgDuration   = $totalSessions > 0 ? (int)($totalDuration / $totalSessions) : 0;

// ── 2. Top 10 Users by Bandwidth ──────────────────────────────────────────
$topUsersStmt = $db->prepare("
    SELECT username,
           COUNT(*) AS session_count,
           COALESCE(SUM(acctinputoctets), 0) AS upload,
           COALESCE(SUM(acctoutputoctets), 0) AS download,
           COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_bytes,
           COALESCE(SUM(acctsessiontime), 0) AS duration
    FROM radacct
    WHERE acctstarttime >= ? AND acctstarttime <= ?
    GROUP BY username
    ORDER BY total_bytes DESC
    LIMIT 10
");
$topUsersStmt->execute([$startTs, $endTs]);
$topUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);

// Batch resolve profiles from userinfo
$hasUserinfo = dbTableExists('userinfo');
$userProfiles = [];
if (!empty($topUsers) && $hasUserinfo) {
    $uNames = array_column($topUsers, 'username');
    $placeholders = implode(',', array_fill(0, count($uNames), '?'));
    $uStmt = $db->prepare("SELECT username, firstname, department, email FROM userinfo WHERE username IN ($placeholders)");
    $uStmt->execute($uNames);
    while ($p = $uStmt->fetch(PDO::FETCH_ASSOC)) {
        $userProfiles[$p['username']] = $p;
    }
}

// ── 3. Bandwidth & Sessions by Policy Group ───────────────────────────────
$groupStmt = $db->prepare("
    SELECT COALESCE(rug.groupname, 'Unassigned') AS grp,
           COUNT(DISTINCT ra.username) AS active_users,
           COUNT(*) AS session_count,
           COALESCE(SUM(ra.acctinputoctets + ra.acctoutputoctets), 0) AS total_bytes
    FROM radacct ra
    LEFT JOIN radusergroup rug ON rug.username = ra.username
    WHERE ra.acctstarttime >= ? AND ra.acctstarttime <= ?
    GROUP BY COALESCE(rug.groupname, 'Unassigned')
    ORDER BY total_bytes DESC
");
$groupStmt->execute([$startTs, $endTs]);
$groupUsage = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

// ── 4. Top NAS Devices ────────────────────────────────────────────────────
$nasStmt = $db->prepare("
    SELECT ra.nasipaddress,
           n.shortname,
           COUNT(*) AS session_count,
           COALESCE(SUM(ra.acctinputoctets + ra.acctoutputoctets), 0) AS total_bytes
    FROM radacct ra
    LEFT JOIN nas n ON n.nasname = ra.nasipaddress
    WHERE ra.acctstarttime >= ? AND ra.acctstarttime <= ?
    GROUP BY ra.nasipaddress
    ORDER BY total_bytes DESC
    LIMIT 10
");
$nasStmt->execute([$startTs, $endTs]);
$topNas = $nasStmt->fetchAll(PDO::FETCH_ASSOC);

// ── CSV Export Mode ───────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="radius_report_' . $dateFrom . '_to_' . $dateTo . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Summary Section
    fputcsv($out, ['RadiusManager Executive Report']);
    fputcsv($out, ['Reporting Period', "$dateFrom to $dateTo"]);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Total Sessions', $totalSessions]);
    fputcsv($out, ['Unique Active Users', $uniqueUsers]);
    fputcsv($out, ['Total Upload (Bytes)', $totalUpload]);
    fputcsv($out, ['Total Download (Bytes)', $totalDownload]);
    fputcsv($out, ['Total Bandwidth', formatBytes($totalTraffic)]);
    fputcsv($out, ['Average Session Duration', formatDuration($avgDuration)]);
    fputcsv($out, []);

    // Top Users Section
    fputcsv($out, ['--- TOP CONSUMERS ---']);
    fputcsv($out, ['Rank', 'Username', 'Name', 'Department', 'Sessions', 'Upload', 'Download', 'Total Bandwidth']);
    $rank = 1;
    foreach ($topUsers as $u) {
        $prof = $userProfiles[$u['username']] ?? [];
        fputcsv($out, [
            $rank++,
            $u['username'],
            $prof['firstname'] ?? '',
            $prof['department'] ?? '',
            $u['session_count'],
            formatBytes($u['upload']),
            formatBytes($u['download']),
            formatBytes($u['total_bytes'])
        ]);
    }
    fputcsv($out, []);

    // Top NAS Section
    fputcsv($out, ['--- TOP NAS ACCESS POINTS ---']);
    fputcsv($out, ['NAS IP', 'Short Name', 'Sessions', 'Total Bandwidth']);
    foreach ($topNas as $n) {
        fputcsv($out, [
            $n['nasipaddress'],
            $n['shortname'] ?? 'Unknown',
            $n['session_count'],
            formatBytes($n['total_bytes'])
        ]);
    }

    fclose($out);
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<style>
@media print {
    #sidebar, #topbar, .no-print, .btn, form { display: none !important; }
    #main { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    .content { padding: 0 !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
    body { background: #fff !important; color: #000 !important; font-size: 11pt !important; }
    .table td, .table th { padding: 4px 8px !important; }
}
</style>

<div class="page-header d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>System & Bandwidth Reports</h4>
        <p class="text-muted small mb-0">Periodic usage metrics, bandwidth utilization, and printable executive summaries</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Report
        </button>
        <a href="?from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Date Range Filter & Preset Selector -->
<div class="card mb-4 no-print">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Date From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Date To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-filter me-1"></i>Apply Range
                </button>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group btn-group-sm">
                    <a href="?preset=this_month" class="btn btn-outline-secondary <?= $preset === 'this_month' ? 'active' : '' ?>">This Month</a>
                    <a href="?preset=last_month" class="btn btn-outline-secondary <?= $preset === 'last_month' ? 'active' : '' ?>">Last Month</a>
                    <a href="?preset=last_30_days" class="btn btn-outline-secondary <?= $preset === 'last_30_days' ? 'active' : '' ?>">Last 30D</a>
                    <a href="?preset=this_year" class="btn btn-outline-secondary <?= $preset === 'this_year' ? 'active' : '' ?>">This Year</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Printable Header Document Title -->
<div class="d-none d-print-block mb-4 border-bottom pb-3">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h2 class="fw-bold text-dark mb-1"><?= APP_NAME ?> — Service & Bandwidth Report</h2>
            <div class="text-muted">Report Period: <strong><?= date('d M Y', strtotime($dateFrom)) ?></strong> &mdash; <strong><?= date('d M Y', strtotime($dateTo)) ?></strong></div>
        </div>
        <div class="text-end text-muted small">
            <div>Generated: <?= date('d M Y H:i:s') ?></div>
            <div>Admin: <?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin') ?></div>
        </div>
    </div>
</div>

<!-- Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-activity"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalSessions) ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($uniqueUsers) ?></div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle text-info">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div>
                <div class="stat-value"><?= formatBytes($totalTraffic) ?></div>
                <div class="stat-label">Total Bandwidth</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-stopwatch"></i>
            </div>
            <div>
                <div class="stat-value"><?= formatDuration($avgDuration) ?></div>
                <div class="stat-label">Avg Session Duration</div>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown Rows -->
<div class="row g-4 mb-4">
    <!-- Top 10 Consumers -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold text-secondary"><i class="bi bi-trophy text-warning me-1"></i>Top 10 Bandwidth Consumers</span>
                <span class="badge bg-light text-secondary border"><?= count($topUsers) ?> users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Subscriber</th>
                                <th>Sessions</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Total Usage</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($topUsers)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No session traffic recorded in selected date range.</td></tr>
                        <?php else: $rank = 1; foreach ($topUsers as $u): ?>
                            <tr>
                                <td class="text-muted small fw-semibold"><?= $rank++ ?></td>
                                <td>
                                    <strong><?= sanitize($u['username']) ?></strong>
                                    <?php if (!empty($userProfiles[$u['username']]['firstname'])): ?>
                                    <div class="small text-muted">
                                        <?= sanitize($userProfiles[$u['username']]['firstname']) ?>
                                        <?php if (!empty($userProfiles[$u['username']]['department'])): ?>
                                        <span class="badge bg-light text-secondary border ms-1"><?= sanitize($userProfiles[$u['username']]['department']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($u['session_count']) ?></td>
                                <td class="small text-muted"><?= formatBytes($u['upload']) ?></td>
                                <td class="small text-muted"><?= formatBytes($u['download']) ?></td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= formatBytes($u['total_bytes']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Group Breakdown -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold text-secondary"><i class="bi bi-collection text-primary me-1"></i>Bandwidth by Group</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Users</th>
                                <th>Traffic</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($groupUsage)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No group activity found</td></tr>
                        <?php else: foreach ($groupUsage as $g): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?= sanitize($g['grp']) ?></span></td>
                                <td class="small text-muted"><?= number_format($g['active_users']) ?></td>
                                <td class="small fw-semibold text-dark"><?= formatBytes($g['total_bytes']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top NAS Access Points -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-semibold text-secondary"><i class="bi bi-hdd-network text-info me-1"></i>Network Access Servers (NAS) Traffic Distribution</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>NAS IP Address</th>
                        <th>Identifier / Short Name</th>
                        <th>Total Sessions Handled</th>
                        <th>Cumulative Bandwidth Delivered</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($topNas)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No NAS records active in range</td></tr>
                <?php else: foreach ($topNas as $n): ?>
                    <tr>
                        <td><code><?= sanitize($n['nasipaddress']) ?></code></td>
                        <td><?= sanitize($n['shortname'] ?? '—') ?></td>
                        <td><?= number_format($n['session_count']) ?> sessions</td>
                        <td><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle"><?= formatBytes($n['total_bytes']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';

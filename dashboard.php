<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Dashboard';
$db = getDB();

// ── Stats ──────────────────────────────────────────────────────────────────
$totalUsers    = (int)($db->query("SELECT COUNT(DISTINCT username) c FROM radcheck")->fetch()['c'] ?? 0);
$activeSessions= (int)($db->query("SELECT COUNT(*) c FROM radacct WHERE acctstoptime IS NULL")->fetch()['c'] ?? 0);
$totalNas      = (int)($db->query("SELECT COUNT(*) c FROM nas")->fetch()['c'] ?? 0);

// Latest auth record for quick anchor (indexed on primary key id, 0.4ms)
$latestAuth    = $db->query("SELECT id, authdate FROM radpostauth ORDER BY id DESC LIMIT 1")->fetch();
$latestDate    = $latestAuth ? substr($latestAuth['authdate'], 0, 10) : date('Y-m-d');
$todayDate     = date('Y-m-d');

// ── Daily auth chart (cached in session for 120s to eliminate 24M table scans) ──
if (isset($_SESSION['dash_chart_time']) && (time() - $_SESSION['dash_chart_time'] < 120) && !empty($_SESSION['dash_chart_data'])) {
    $chartData = $_SESSION['dash_chart_data'];
} else {
    if ($latestAuth) {
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days", strtotime($latestDate)));
            $c = (int)($db->query("SELECT COUNT(*) FROM radpostauth WHERE authdate >= '$d 00:00:00' AND authdate <= '$d 23:59:59'")->fetchColumn() ?? 0);
            $chartData[] = ['d' => $d, 'c' => $c];
        }
        $_SESSION['dash_chart_data'] = $chartData;
        $_SESSION['dash_chart_time'] = time();
    } else {
        $chartData = [];
    }
}
$chartLabels = array_column($chartData, 'd');
$chartValues = array_column($chartData, 'c');

// Stat card for Auth count (derived instantly from 7-day chart data, 0ms)
if ($latestDate < $todayDate) {
    $authLabel        = "Recent Auth ($latestDate)";
    $authDateParam    = $latestDate;
    $authCountDisplay = !empty($chartData) ? end($chartData)['c'] : 0;
} else {
    $authLabel        = 'Auth Today';
    $authDateParam    = $todayDate;
    $authCountDisplay = !empty($chartData) ? end($chartData)['c'] : 0;
}

// ── Traffic today ──────────────────────────────────────────────────────────
$trafficRow = $db->query("SELECT
    COALESCE(SUM(acctinputoctets),0)  AS upload,
    COALESCE(SUM(acctoutputoctets),0) AS download
  FROM radacct WHERE acctstarttime >= CURDATE() AND acctstarttime < CURDATE() + INTERVAL 1 DAY")->fetch();

$trafficDateParam = $todayDate;
$trafficDateLabel = '';

if (($trafficRow['upload'] ?? 0) == 0 && ($trafficRow['download'] ?? 0) == 0) {
    // If today has no sessions, grab the latest day with traffic
    $latestAcct = $db->query("SELECT DATE(acctstarttime) d FROM radacct ORDER BY acctstarttime DESC LIMIT 1")->fetch();
    if ($latestAcct && !empty($latestAcct['d'])) {
        $trafficDateParam = $latestAcct['d'];
        $trafficDateLabel = " ({$latestAcct['d']})";
        $trafficRow = $db->query("SELECT
            COALESCE(SUM(acctinputoctets),0)  AS upload,
            COALESCE(SUM(acctoutputoctets),0) AS download
          FROM radacct WHERE acctstarttime >= '{$latestAcct['d']} 00:00:00' AND acctstarttime <= '{$latestAcct['d']} 23:59:59'")->fetch();
    }
}
$uploadToday   = formatBytes($trafficRow['upload'] ?? 0);
$downloadToday = formatBytes($trafficRow['download'] ?? 0);

// ── Active sessions (latest 8, primary key index scan, 1.4ms) ──────────────
$sessions = $db->query("SELECT username, nasipaddress, framedipaddress,
    acctstarttime, acctinputoctets, acctoutputoctets
  FROM radacct WHERE acctstoptime IS NULL
  ORDER BY radacctid DESC LIMIT 8")->fetchAll();

// ── Recent failed auth (ORDER BY id DESC uses PRIMARY KEY index, executes in 1ms) ──
$hasPostAuthNas = dbHasColumn('radpostauth', 'nasipaddress');
$nasCol = $hasPostAuthNas ? 'nasipaddress' : 'NULL AS nasipaddress';
$failedAuths = $db->query("SELECT username, reply, authdate, $nasCol
  FROM radpostauth WHERE reply != 'Access-Accept'
  ORDER BY id DESC LIMIT 6")->fetchAll();

// ── Top 5 traffic users (anchored to trafficDateParam) ──────────────────────
$topUsersStmt = $db->prepare("SELECT username,
    COALESCE(SUM(acctinputoctets),0) AS upload,
    COALESCE(SUM(acctoutputoctets),0) AS download,
    COALESCE(SUM(acctinputoctets + acctoutputoctets),0) AS total_bytes,
    COUNT(*) AS session_count
  FROM radacct
  WHERE acctstarttime >= ? AND acctstarttime <= ?
  GROUP BY username
  ORDER BY total_bytes DESC
  LIMIT 5");
$topUsersStmt->execute(["$trafficDateParam 00:00:00", "$trafficDateParam 23:59:59"]);
$topTrafficUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);

// Batch resolve profiles from userinfo
$topUserProfiles = [];
if (!empty($topTrafficUsers) && dbTableExists('userinfo')) {
    $topU = array_column($topTrafficUsers, 'username');
    $placeholders = implode(',', array_fill(0, count($topU), '?'));
    $uStmt = $db->prepare("SELECT username, firstname, lastname, department FROM userinfo WHERE username IN ($placeholders)");
    $uStmt->execute($topU);
    while ($prof = $uStmt->fetch(PDO::FETCH_ASSOC)) {
        $topUserProfiles[$prof['username']] = $prof;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
        <p>Overview of your FreeRADIUS system</p>
    </div>
    <span class="badge bg-light text-secondary border"><?= date('D, d M Y H:i') ?></span>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="users.php" class="stat-card" title="Go to Users">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-people text-primary"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Total Users <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="sessions.php" class="stat-card" title="Go to Active Sessions">
            <div class="stat-icon" style="background:#f0fdf4">
                <i class="bi bi-activity text-success"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($activeSessions) ?></div>
                <div class="stat-label">Active Sessions <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="nas.php" class="stat-card" title="Go to NAS Devices">
            <div class="stat-icon" style="background:#fefce8">
                <i class="bi bi-hdd-network text-warning"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalNas) ?></div>
                <div class="stat-label">NAS Devices <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="postauth.php?from=<?= $authDateParam ?>" class="stat-card" title="View Auth Log">
            <div class="stat-icon" style="background:#fdf4ff">
                <i class="bi bi-shield-check text-purple" style="color:#9333ea"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($authCountDisplay) ?></div>
                <div class="stat-label"><?= htmlspecialchars($authLabel) ?> <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
</div>

<!-- Traffic Cards -->
<div class="row g-3 mb-4">
    <div class="col-6">
        <a href="accounting.php?from=<?= $trafficDateParam ?>&to=<?= $trafficDateParam ?>" class="stat-card" title="View Accounting Records">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-arrow-up-circle text-primary"></i>
            </div>
            <div>
                <div class="stat-value" style="font-size:1.2rem"><?= $uploadToday ?></div>
                <div class="stat-label">Upload Today<?= htmlspecialchars($trafficDateLabel) ?> <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6">
        <a href="accounting.php?from=<?= $trafficDateParam ?>&to=<?= $trafficDateParam ?>" class="stat-card" title="View Accounting Records">
            <div class="stat-icon" style="background:#f0fdf4">
                <i class="bi bi-arrow-down-circle text-success"></i>
            </div>
            <div>
                <div class="stat-value" style="font-size:1.2rem"><?= $downloadToday ?></div>
                <div class="stat-label">Download Today<?= htmlspecialchars($trafficDateLabel) ?> <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Auth Chart -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small">Authentications — Last 7 Days</span>
                <a href="postauth.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">View Log</a>
            </div>
            <div class="card-body">
                <canvas id="authChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <!-- Failed Auth -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-x-circle text-danger me-1"></i>Recent Failed Logins</span>
                <a href="postauth.php?filter=reject" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:.75rem">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr>
                        <th>Username</th><th>Reason</th><th>Time</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($failedAuths)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No failed logins today</td></tr>
                    <?php else: foreach ($failedAuths as $f): ?>
                    <tr style="cursor:pointer" onclick="location.href='postauth.php?filter=reject&q=<?= urlencode($f['username']) ?>'" title="View auth log for <?= sanitize($f['username']) ?>">
                        <td><code><?= sanitize($f['username']) ?></code></td>
                        <td><span class="badge bg-danger-subtle text-danger"><?= sanitize($f['reply']) ?></span></td>
                        <td class="text-muted" style="font-size:.78rem"><?= date('H:i', strtotime($f['authdate'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Active Sessions & Top 5 Traffic Users Row -->
<div class="row g-4 mb-4">
    <!-- Active Sessions Table -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-activity me-1 text-success"></i>Active Sessions</span>
                <a href="sessions.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr>
                        <th>Username</th><th>NAS IP</th><th>Client IP</th>
                        <th>Started</th><th>Traffic</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($sessions)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No active sessions</td></tr>
                    <?php else: foreach ($sessions as $s): ?>
                    <tr style="cursor:pointer" onclick="location.href='sessions.php?q=<?= urlencode($s['username']) ?>'" title="View session details for <?= sanitize($s['username']) ?>">
                        <td><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem"></i>
                            <strong><?= sanitize($s['username']) ?></strong></td>
                        <td class="text-muted small"><?= sanitize($s['nasipaddress']) ?></td>
                        <td><code style="font-size:.78rem"><?= sanitize($s['framedipaddress']) ?></code></td>
                        <td class="text-muted" style="font-size:.78rem"><?= date('H:i', strtotime($s['acctstarttime'])) ?></td>
                        <td class="small"><?= formatBytes((float)($s['acctinputoctets'] ?? 0) + (float)($s['acctoutputoctets'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 Traffic Users -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small">
                    <i class="bi bi-fire me-1 text-danger"></i>Top 5 Traffic Users<?= $trafficDateLabel ? ' <span class="text-muted fw-normal">' . sanitize($trafficDateLabel) . '</span>' : '' ?>
                </span>
                <a href="accounting.php" class="btn btn-sm btn-outline-secondary">Details</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th style="width: 45px;">#</th>
                        <th>User</th>
                        <th class="text-end">Total Traffic</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($topTrafficUsers)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No traffic recorded</td></tr>
                    <?php else: 
                        $maxBytes = max(1, (float)($topTrafficUsers[0]['total_bytes'] ?? 1));
                        $rank = 0;
                        foreach ($topTrafficUsers as $tu): 
                            $rank++;
                            $pct = round(((float)$tu['total_bytes'] / $maxBytes) * 100);
                            $badgeClass = match($rank) {
                                1 => 'bg-warning text-dark',
                                2 => 'bg-secondary',
                                3 => 'text-dark" style="background:#fed7aa;',
                                default => 'bg-light text-secondary border'
                            };
                            $uProf = $topUserProfiles[$tu['username']] ?? [];
                    ?>
                    <tr style="cursor:pointer" onclick="location.href='user-edit.php?username=<?= urlencode($tu['username']) ?>'" title="Edit user <?= sanitize($tu['username']) ?>">
                        <td><span class="badge <?= $badgeClass ?> rounded-pill" style="font-size:.72rem">#<?= $rank ?></span></td>
                        <td>
                            <div class="fw-semibold small"><?= sanitize($tu['username']) ?></div>
                            <?php if (!empty($uProf['firstname'])): ?>
                            <div class="text-muted text-truncate" style="font-size:.72rem; max-width: 140px;">
                                <?= sanitize($uProf['firstname']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="fw-bold text-dark small"><?= formatBytes($tu['total_bytes']) ?></div>
                            <div class="text-muted" style="font-size:.68rem">
                                <span class="text-success"><i class="bi bi-arrow-down-short"></i><?= formatBytes($tu['download']) ?></span>
                                <span class="text-primary ms-1"><i class="bi bi-arrow-up-short"></i><?= formatBytes($tu['upload']) ?></span>
                            </div>
                            <div class="progress mt-1 ms-auto" style="height: 3px; width: 80px;">
                                <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script>
const ctx = document.getElementById("authChart");
new Chart(ctx, {
    type: "bar",
    data: {
        labels: ' . json_encode($chartLabels ?: ['No data']) . ',
        datasets: [{
            label: "Authentications",
            data: ' . json_encode($chartValues ?: [0]) . ',
            backgroundColor: "rgba(37,99,235,.15)",
            borderColor: "rgba(37,99,235,1)",
            borderWidth: 2, borderRadius: 4
        }]
    },
    options: {
        responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                  x: { grid: { display: false } } }
    }
});
</script>';
include __DIR__ . '/includes/footer.php';

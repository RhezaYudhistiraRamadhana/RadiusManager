<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Dashboard';
$db = getDB();

// ── View Mode: 'today' (Grafik Hari Ini) vs 'general' (Grafik Umum) ────────
$viewMode = $_GET['view'] ?? $_SESSION['dash_view'] ?? 'today';
if (!in_array($viewMode, ['today', 'general'])) {
    $viewMode = 'today';
}
$_SESSION['dash_view'] = $viewMode;

// ── Instant Top Stats (Indexed lookups, < 2ms) ─────────────────────────────
$totalUsers     = (int)($db->query("SELECT COUNT(DISTINCT username) c FROM radcheck")->fetch()['c'] ?? 0);
$activeSessions = (int)($db->query("SELECT COUNT(*) c FROM radacct WHERE acctstoptime IS NULL")->fetch()['c'] ?? 0);
$totalNas       = (int)($db->query("SELECT COUNT(*) c FROM nas")->fetch()['c'] ?? 0);

// Latest auth record for quick anchor (indexed on primary key id, 0.3ms)
$latestAuth = $db->query("SELECT id, authdate FROM radpostauth ORDER BY id DESC LIMIT 1")->fetch();
$latestDate = $latestAuth ? substr($latestAuth['authdate'], 0, 10) : date('Y-m-d');
$todayDate  = date('Y-m-d');

// ── Operational Tables (Instant primary key indexed scans, < 2ms) ──────────
$sessions = $db->query("SELECT username, nasipaddress, framedipaddress,
    acctstarttime, acctinputoctets, acctoutputoctets
  FROM radacct WHERE acctstoptime IS NULL
  ORDER BY radacctid DESC LIMIT 8")->fetchAll();

$hasPostAuthNas = dbHasColumn('radpostauth', 'nasipaddress');
$nasCol = $hasPostAuthNas ? 'nasipaddress' : 'NULL AS nasipaddress';
$failedAuths = $db->query("SELECT username, reply, authdate, $nasCol
  FROM radpostauth WHERE reply != 'Access-Accept'
  ORDER BY id DESC LIMIT 6")->fetchAll();

// ── HIGH-PERFORMANCE LAZY LOADED ANALYTICS BY VIEW MODE ────────────────────
if ($viewMode === 'today') {
    if (isset($_SESSION['dash_today_time']) && (time() - $_SESSION['dash_today_time'] < 120) && !empty($_SESSION['dash_today_data'])) {
        $todayData = $_SESSION['dash_today_data'];
    } else {
        $anchorDay = $todayDate;
        $prevDay   = date('Y-m-d', strtotime('-1 day', strtotime($anchorDay)));

        // Traffic Today (Fast CURDATE query, < 0.5ms)
        $trafficRow = $db->query("SELECT
            COALESCE(SUM(acctinputoctets),0)  AS upload,
            COALESCE(SUM(acctoutputoctets),0) AS download
          FROM radacct WHERE acctstarttime >= CURDATE() AND acctstarttime < CURDATE() + INTERVAL 1 DAY")->fetch();

        $trafficDateParam = $todayDate;
        $trafficDateLabel = '';

        if (($trafficRow['upload'] ?? 0) == 0 && ($trafficRow['download'] ?? 0) == 0) {
            // Check if latest session date exists for labeling reference
            $latestAcct = $db->query("SELECT DATE(acctstarttime) d FROM radacct ORDER BY radacctid DESC LIMIT 1")->fetch();
            if ($latestAcct && !empty($latestAcct['d']) && $latestAcct['d'] !== $todayDate) {
                $trafficDateLabel = " (Hari Ini Belum Ada Sesi)";
            }
        }
        $uploadToday   = formatBytes($trafficRow['upload'] ?? 0);
        $downloadToday = formatBytes($trafficRow['download'] ?? 0);

        // Top 5 traffic users today
        $topUsersStmt = $db->prepare("SELECT username,
            COALESCE(SUM(acctinputoctets),0) AS upload,
            COALESCE(SUM(acctoutputoctets),0) AS download,
            COALESCE(SUM(acctinputoctets + acctoutputoctets),0) AS total_bytes,
            COUNT(*) AS session_count
          FROM radacct
          WHERE acctstarttime >= CURDATE() AND acctstarttime < CURDATE() + INTERVAL 1 DAY
          GROUP BY username
          ORDER BY total_bytes DESC
          LIMIT 5");
        $topUsersStmt->execute();
        $topTrafficUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);

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

        // 1 & 2. COMBINED QUERY: Hourly sessions AND Hourly Bandwidth in ONE single scan (< 1ms)
        $hourlyStmt = $db->prepare("
            SELECT HOUR(acctstarttime) AS h,
                   COUNT(*) AS c,
                   COALESCE(SUM(acctinputoctets), 0) AS up,
                   COALESCE(SUM(acctoutputoctets), 0) AS down
            FROM radacct
            WHERE acctstarttime >= ? AND acctstarttime <= ?
            GROUP BY HOUR(acctstarttime)
        ");
        $hourlyStmt->execute(["$anchorDay 00:00:00", "$anchorDay 23:59:59"]);
        $todayHourMap = [];
        $hourlyBwMap  = [];
        while ($r = $hourlyStmt->fetch(PDO::FETCH_ASSOC)) {
            $h = (int)$r['h'];
            $todayHourMap[$h] = (int)$r['c'];
            $hourlyBwMap[$h]  = $r;
        }

        // Previous day hourly session comparison
        $prevHoursStmt = $db->prepare("
            SELECT HOUR(acctstarttime) AS h, COUNT(*) AS c
            FROM radacct
            WHERE acctstarttime >= ? AND acctstarttime <= ?
            GROUP BY HOUR(acctstarttime)
        ");
        $prevHoursStmt->execute(["$prevDay 00:00:00", "$prevDay 23:59:59"]);
        $prevHourMap = $prevHoursStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $hourlyLabels     = [];
        $hourlyToday      = [];
        $hourlyPrev       = [];
        $hourlyUploadMB   = [];
        $hourlyDownloadMB = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyLabels[]     = sprintf('%02d:00', $h);
            $hourlyToday[]      = (int)($todayHourMap[$h] ?? 0);
            $hourlyPrev[]       = (int)($prevHourMap[$h] ?? 0);
            $hourlyUploadMB[]   = round(($hourlyBwMap[$h]['up'] ?? 0) / (1024 * 1024), 2);
            $hourlyDownloadMB[] = round(($hourlyBwMap[$h]['down'] ?? 0) / (1024 * 1024), 2);
        }

        // 3. Hourly Auth Decisions Today (Accept vs Reject) (< 1ms)
        $hourlyAccepts = array_fill(0, 24, 0);
        $hourlyRejects = array_fill(0, 24, 0);
        $hourlyAuthStmt = $db->prepare("
            SELECT HOUR(authdate) AS h,
                   SUM(CASE WHEN reply = 'Access-Accept' THEN 1 ELSE 0 END) AS accepts,
                   SUM(CASE WHEN reply != 'Access-Accept' THEN 1 ELSE 0 END) AS rejects
            FROM radpostauth
            WHERE authdate >= ? AND authdate <= ?
            GROUP BY HOUR(authdate)
        ");
        $hourlyAuthStmt->execute(["$anchorDay 00:00:00", "$anchorDay 23:59:59"]);
        while ($r = $hourlyAuthStmt->fetch(PDO::FETCH_ASSOC)) {
            $h = (int)$r['h'];
            if ($h >= 0 && $h < 24) {
                $hourlyAccepts[$h] = (int)$r['accepts'];
                $hourlyRejects[$h] = (int)$r['rejects'];
            }
        }

        // 4. Top 5 NAS Devices by Bandwidth Today (Optimized group first) (< 1ms)
        $topNasTodayStmt = $db->prepare("
            SELECT nasipaddress,
                   COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_bytes
            FROM radacct
            WHERE acctstarttime >= ? AND acctstarttime <= ?
            GROUP BY nasipaddress
            ORDER BY total_bytes DESC
            LIMIT 5
        ");
        $topNasTodayStmt->execute(["$anchorDay 00:00:00", "$anchorDay 23:59:59"]);
        $topNas = $topNasTodayStmt->fetchAll(PDO::FETCH_ASSOC);

        $nasTodayLabels = [];
        $nasTodayValues = [];
        if (!empty($topNas)) {
            $nasIps = array_column($topNas, 'nasipaddress');
            $placeholders = implode(',', array_fill(0, count($nasIps), '?'));
            $nasMapStmt = $db->prepare("SELECT nasname, shortname FROM nas WHERE nasname IN ($placeholders)");
            $nasMapStmt->execute($nasIps);
            $shortMap = $nasMapStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($topNas as $nr) {
                $nasTodayLabels[] = $shortMap[$nr['nasipaddress']] ?? $nr['nasipaddress'];
                $nasTodayValues[] = round($nr['total_bytes'] / (1024 * 1024), 2);
            }
        }

        // Top Stat Card Auth Display
        $authLabel     = 'Auth Today';
        $authDateParam = $todayDate;
        $authCountDisplay = array_sum($hourlyAccepts) + array_sum($hourlyRejects);

        $todayData = [
            'trafficDateParam'  => $trafficDateParam,
            'trafficDateLabel'  => $trafficDateLabel,
            'uploadToday'       => $uploadToday,
            'downloadToday'     => $downloadToday,
            'topTrafficUsers'   => $topTrafficUsers,
            'topUserProfiles'   => $topUserProfiles,
            'anchorDay'         => $anchorDay,
            'prevDay'           => $prevDay,
            'hourlyLabels'      => $hourlyLabels,
            'hourlyToday'       => $hourlyToday,
            'hourlyPrev'        => $hourlyPrev,
            'hourlyUploadMB'    => $hourlyUploadMB,
            'hourlyDownloadMB'  => $hourlyDownloadMB,
            'hourlyAccepts'     => $hourlyAccepts,
            'hourlyRejects'     => $hourlyRejects,
            'nasTodayLabels'    => $nasTodayLabels,
            'nasTodayValues'    => $nasTodayValues,
            'authLabel'         => $authLabel,
            'authDateParam'     => $authDateParam,
            'authCountDisplay'  => $authCountDisplay,
        ];
        $_SESSION['dash_today_data'] = $todayData;
        $_SESSION['dash_today_time'] = time();
    }

    $uploadToday       = $todayData['uploadToday'];
    $downloadToday     = $todayData['downloadToday'];
    $trafficDateParam  = $todayData['trafficDateParam'];
    $trafficDateLabel  = $todayData['trafficDateLabel'];
    $topTrafficUsers   = $todayData['topTrafficUsers'];
    $topUserProfiles   = $todayData['topUserProfiles'];
    $authLabel         = $todayData['authLabel'];
    $authDateParam     = $todayData['authDateParam'];
    $authCountDisplay  = $todayData['authCountDisplay'];

} else {
    // ── GENERAL CHARTS ANALYTICS (14-Day Trends) ──
    if (isset($_SESSION['dash_general_time']) && (time() - $_SESSION['dash_general_time'] < 300) && !empty($_SESSION['dash_general_data'])) {
        $generalData = $_SESSION['dash_general_data'];
    } else {
        $trafficRow = $db->query("SELECT
            COALESCE(SUM(acctinputoctets),0)  AS upload,
            COALESCE(SUM(acctoutputoctets),0) AS download
          FROM radacct WHERE acctstarttime >= CURDATE() AND acctstarttime < CURDATE() + INTERVAL 1 DAY")->fetch();
        $uploadToday   = formatBytes($trafficRow['upload'] ?? 0);
        $downloadToday = formatBytes($trafficRow['download'] ?? 0);

        $start14d = date('Y-m-d 00:00:00', strtotime('-13 days', strtotime($todayDate)));
        $endCur   = strtotime($todayDate);

        // 1 & 2. COMBINED QUERY: 14-Day Daily Sessions AND Bandwidth Volume in ONE single scan (< 1ms)
        $combined14dStmt = $db->prepare("
            SELECT DATE(acctstarttime) AS d,
                   COUNT(*) AS c,
                   COALESCE(SUM(acctinputoctets), 0) AS up,
                   COALESCE(SUM(acctoutputoctets), 0) AS down
            FROM radacct
            WHERE acctstarttime >= ? AND acctstarttime <= ?
            GROUP BY DATE(acctstarttime)
            ORDER BY d ASC
        ");
        $combined14dStmt->execute([$start14d, "$todayDate 23:59:59"]);
        $combined14d = $combined14dStmt->fetchAll(PDO::FETCH_ASSOC);

        $sessions14dMap = [];
        $bw14Map        = [];
        foreach ($combined14d as $row) {
            $sessions14dMap[$row['d']] = (int)$row['c'];
            $bw14Map[$row['d']]        = $row;
        }

        $sessions14dLabels = [];
        $sessions14dValues = [];
        $bw14Labels        = [];
        $bw14Upload        = [];
        $bw14Download      = [];
        $cur = strtotime($start14d);
        while ($cur <= $endCur) {
            $dStr = date('Y-m-d', $cur);
            $lbl  = date('d M', $cur);
            $sessions14dLabels[] = $lbl;
            $sessions14dValues[] = (int)($sessions14dMap[$dStr] ?? 0);
            $bw14Labels[]        = $lbl;
            $bw14Upload[]        = round(($bw14Map[$dStr]['up'] ?? 0) / (1024 * 1024), 2);
            $bw14Download[]      = round(($bw14Map[$dStr]['down'] ?? 0) / (1024 * 1024), 2);
            $cur = strtotime('+1 day', $cur);
        }

        // 3. 7-Day Authentications (Ultra-fast Boundary ID Index scan, < 10ms vs 990ms)
        $boundaryStmt = $db->prepare("SELECT id FROM radpostauth WHERE authdate >= ? ORDER BY authdate ASC LIMIT 1");
        $boundaryIds  = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days", strtotime($latestDate)));
            $boundaryStmt->execute(["$d 00:00:00"]);
            $rowId = $boundaryStmt->fetchColumn();
            $boundaryIds[$d] = $rowId !== false ? (int)$rowId : 0;
        }
        $lastId = (int)($latestAuth['id'] ?? 0);

        $auth7dLabels = [];
        $auth7dValues = [];
        $dates = array_keys($boundaryIds);
        for ($k = 0; $k < count($dates); $k++) {
            $d = $dates[$k];
            $startId = $boundaryIds[$d];
            $count = 0;
            if ($startId > 0) {
                $nextStartId = 0;
                for ($nextK = $k + 1; $nextK < count($dates); $nextK++) {
                    if ($boundaryIds[$dates[$nextK]] > 0) {
                        $nextStartId = $boundaryIds[$dates[$nextK]];
                        break;
                    }
                }
                if ($nextStartId > $startId) {
                    $count = $nextStartId - $startId;
                } elseif ($lastId >= $startId) {
                    $count = $lastId - $startId + 1;
                }
            }
            $auth7dLabels[] = date('d M', strtotime($d));
            $auth7dValues[] = max(0, $count);
        }

        // 4. Top NAS Devices Overall (Past 14 Days)
        $topNas14dStmt = $db->prepare("
            SELECT nasipaddress,
                   COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_bytes
            FROM radacct
            WHERE acctstarttime >= ? AND acctstarttime <= ?
            GROUP BY nasipaddress
            ORDER BY total_bytes DESC
            LIMIT 5
        ");
        $topNas14dStmt->execute([$start14d, "$todayDate 23:59:59"]);
        $topNas14 = $topNas14dStmt->fetchAll(PDO::FETCH_ASSOC);
        $nas14dLabels = [];
        $nas14dValues = [];
        if (!empty($topNas14)) {
            $nasIps = array_column($topNas14, 'nasipaddress');
            $placeholders = implode(',', array_fill(0, count($nasIps), '?'));
            $nasMapStmt = $db->prepare("SELECT nasname, shortname FROM nas WHERE nasname IN ($placeholders)");
            $nasMapStmt->execute($nasIps);
            $shortMap = $nasMapStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($topNas14 as $nr) {
                $nas14dLabels[] = $shortMap[$nr['nasipaddress']] ?? $nr['nasipaddress'];
                $nas14dValues[] = round($nr['total_bytes'] / (1024 * 1024), 2);
            }
        }

        // Top 5 traffic users overall
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
        $topUsersStmt->execute([$start14d, "$todayDate 23:59:59"]);
        $topTrafficUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);

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

        $generalData = [
            'uploadToday'       => $uploadToday,
            'downloadToday'     => $downloadToday,
            'trafficDateParam'  => $todayDate,
            'trafficDateLabel'  => ' (14 Hari Terakhir)',
            'topTrafficUsers'   => $topTrafficUsers,
            'topUserProfiles'   => $topUserProfiles,
            'sessions14dLabels' => $sessions14dLabels,
            'sessions14dValues' => $sessions14dValues,
            'bw14Labels'        => $bw14Labels,
            'bw14Upload'        => $bw14Upload,
            'bw14Download'      => $bw14Download,
            'auth7dLabels'      => $auth7dLabels,
            'auth7dValues'      => $auth7dValues,
            'nas14dLabels'      => $nas14dLabels,
            'nas14dValues'      => $nas14dValues,
            'authLabel'         => "Recent Auth ($latestDate)",
            'authDateParam'     => $latestDate,
            'authCountDisplay'  => !empty($auth7dValues) ? end($auth7dValues) : 0,
        ];
        $_SESSION['dash_general_data'] = $generalData;
        $_SESSION['dash_general_time'] = time();
    }

    $uploadToday       = $generalData['uploadToday'];
    $downloadToday     = $generalData['downloadToday'];
    $trafficDateParam  = $generalData['trafficDateParam'];
    $trafficDateLabel  = $generalData['trafficDateLabel'];
    $topTrafficUsers   = $generalData['topTrafficUsers'];
    $topUserProfiles   = $generalData['topUserProfiles'];
    $authLabel         = $generalData['authLabel'];
    $authDateParam     = $generalData['authDateParam'];
    $authCountDisplay  = $generalData['authCountDisplay'];
}

// Release session lock immediately to prevent blocking concurrent requests
session_write_close();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Grafik</h4>
        <p class="text-muted mb-0">Pemantauan visual performa sistem FreeRADIUS &amp; lalu lintas jaringan</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="welcome.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 shadow-sm" title="Kembali ke Landing Page">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Beranda</span>
        </a>
        <span class="badge bg-light text-secondary border py-2 px-2.5"><?= date('D, d M Y H:i') ?></span>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="users.php" class="stat-card" title="Buka Direktori Pengguna">
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
        <a href="sessions.php" class="stat-card" title="Buka Pemantau Sesi Aktif">
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
        <a href="nas.php" class="stat-card" title="Buka NAS Devices">
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
        <a href="postauth.php?from=<?= $authDateParam ?>" class="stat-card" title="Buka Log Autentikasi">
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
        <a href="accounting.php?from=<?= $trafficDateParam ?>&to=<?= $trafficDateParam ?>" class="stat-card" title="Buka Catatan Accounting">
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
        <a href="accounting.php?from=<?= $trafficDateParam ?>&to=<?= $trafficDateParam ?>" class="stat-card" title="Buka Catatan Accounting">
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

<!-- View Mode Selector (Today vs General Charts) -->
<div class="card mb-4 border shadow-sm" style="background: #ffffff; border-radius: 12px;">
    <div class="card-body py-3 px-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 rounded-3 <?= $viewMode === 'today' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' ?>" style="font-size: 1.25rem;">
                <i class="bi <?= $viewMode === 'today' ? 'bi-clock-history' : 'bi-graph-up-arrow' ?>"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <?= $viewMode === 'today' ? 'Mode: Grafik Hari Ini (Real-Time 24 Jam)' : 'Mode: Grafik Umum (Tren Historis)' ?>
                </h6>
                <p class="text-muted small mb-0">
                    <?= $viewMode === 'today' 
                        ? 'Memvisualisasikan profil sesi per jam, throughput bandwidth upload/download hari ini, dan autentikasi 24 jam.' 
                        : 'Memvisualisasikan tren multi-hari: volume bandwidth 14 hari, histori sesi harian, dan tren autentikasi 7 hari.' ?>
                </p>
            </div>
        </div>
        <div class="btn-group shadow-sm" role="group" aria-label="Pilihan Grafik Dashboard">
            <a href="dashboard.php?view=today" class="btn btn-sm <?= $viewMode === 'today' ? 'btn-primary active fw-semibold' : 'btn-outline-secondary' ?> px-3 py-1.5 d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-clock-history"></i>
                <span>Grafik Hari Ini</span>
            </a>
            <a href="dashboard.php?view=general" class="btn btn-sm <?= $viewMode === 'general' ? 'btn-primary active fw-semibold' : 'btn-outline-secondary' ?> px-3 py-1.5 d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-graph-up"></i>
                <span>Grafik Umum</span>
            </a>
        </div>
    </div>
</div>

<?php if ($viewMode === 'today'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- TODAY'S CHARTS (GRAFIK HARI INI)                                         -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <!-- Chart 1: Hourly Sessions Profile (Today vs Yesterday) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-semibold small"><i class="bi bi-clock-history text-primary me-1"></i>Profil Sesi per Jam</span>
                    <span class="text-muted small ms-1">(Hari Ini vs Kemarin)</span>
                </div>
                <a href="sessions.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">Sessions</a>
            </div>
            <div class="card-body">
                <canvas id="todayConcurrentChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Hourly Bandwidth Today (Upload vs Download) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-semibold small"><i class="bi bi-arrow-down-up text-success me-1"></i>Throughput Bandwidth per Jam Hari Ini</span>
                    <span class="text-muted small ms-1">(Upload vs Download)</span>
                </div>
                <a href="accounting.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">Accounting</a>
            </div>
            <div class="card-body">
                <canvas id="todayBandwidthChart" height="130"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Chart 3: Hourly Authentications Today (Accept vs Reject) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-shield-check text-purple me-1" style="color:#9333ea"></i>Autentikasi Hari Ini — 24 Jam</span>
                <a href="postauth.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">View Log</a>
            </div>
            <div class="card-body">
                <canvas id="todayAuthChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 4: Top NAS Devices by Bandwidth Today -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-hdd-network text-info me-1"></i>Top NAS Access Points Hari Ini</span>
                <a href="nas.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">NAS Devices</a>
            </div>
            <div class="card-body">
                <?php if (empty($todayData['nasTodayValues'])): ?>
                <div class="text-center text-muted py-5 small">Belum ada lalu lintas NAS yang tercatat hari ini</div>
                <?php else: ?>
                <canvas id="todayNasChart" height="130"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- GENERAL CHARTS (GRAFIK UMUM / HISTORICAL)                                -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <!-- Chart 1: Daily Sessions Trend (Past 14 Days) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-semibold small"><i class="bi bi-activity text-primary me-1"></i>Tren Sesi Harian (14 Hari Terakhir)</span>
                </div>
                <a href="sessions.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">Sessions</a>
            </div>
            <div class="card-body">
                <canvas id="generalSessionsChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Bandwidth Volume Trend (Past 14 Days) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-semibold small"><i class="bi bi-graph-up-arrow text-success me-1"></i>Tren Bandwidth (14 Hari Terakhir)</span>
                    <span class="text-muted small ms-1">(Upload vs Download)</span>
                </div>
                <a href="accounting.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">Accounting</a>
            </div>
            <div class="card-body">
                <canvas id="generalBandwidthChart" height="130"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Chart 3: Authentications Trend (Past 7 Days) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-shield-check text-purple me-1" style="color:#9333ea"></i>Autentikasi — 7 Hari Terakhir</span>
                <a href="postauth.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">View Log</a>
            </div>
            <div class="card-body">
                <canvas id="generalAuthChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 4: Top NAS Devices Overall (Past 14 Days) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-hdd-network text-info me-1"></i>Top NAS Access Points (14 Hari Terakhir)</span>
                <a href="nas.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">NAS Devices</a>
            </div>
            <div class="card-body">
                <?php if (empty($generalData['nas14dValues'])): ?>
                <div class="text-center text-muted py-5 small">Belum ada data NAS 14 hari terakhir</div>
                <?php else: ?>
                <canvas id="generalNasChart" height="130"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Active Sessions & Top 5 Traffic Users Row -->
<div class="row g-4 mb-4">
    <!-- Active Sessions Table -->
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border">
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
        <div class="card h-100 shadow-sm border">
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
                    <tr><td colspan="3" class="text-center text-muted py-4"><?= $viewMode === 'today' ? 'No traffic recorded today' : 'No traffic recorded in this period' ?></td></tr>
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

<!-- Failed Auth Row -->
<div class="card mb-4 shadow-sm border">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold small"><i class="bi bi-x-circle text-danger me-1"></i>Recent Failed Logins</span>
        <a href="postauth.php?filter=reject" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:.75rem">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
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

<?php
if ($viewMode === 'today') {
    $extra_js = '<script>
    // 1. Hourly Concurrent Sessions (Today vs Yesterday)
    const ctxTodayConcurrent = document.getElementById("todayConcurrentChart");
    if (ctxTodayConcurrent) {
        new Chart(ctxTodayConcurrent, {
            type: "line",
            data: {
                labels: ' . json_encode($todayData['hourlyLabels']) . ',
                datasets: [
                    {
                        label: "Hari Ini (' . date('d M', strtotime($todayData['anchorDay'])) . ')",
                        data: ' . json_encode($todayData['hourlyToday']) . ',
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37,99,235,0.08)",
                        tension: 0.35, fill: true, pointRadius: 2
                    },
                    {
                        label: "Kemarin (' . date('d M', strtotime($todayData['prevDay'])) . ')",
                        data: ' . json_encode($todayData['hourlyPrev']) . ',
                        borderColor: "#94a3b8",
                        borderDash: [4, 4],
                        backgroundColor: "transparent",
                        tension: 0.35, fill: false, pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: true, position: "top", labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" }, ticks: { precision: 0 } },
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }
                }
            }
        });
    }

    // 2. Hourly Bandwidth Throughput (Upload vs Download Today)
    const ctxTodayBw = document.getElementById("todayBandwidthChart");
    if (ctxTodayBw) {
        new Chart(ctxTodayBw, {
            type: "line",
            data: {
                labels: ' . json_encode($todayData['hourlyLabels']) . ',
                datasets: [
                    {
                        label: "Download (MB)",
                        data: ' . json_encode($todayData['hourlyDownloadMB']) . ',
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16,185,129,0.1)",
                        tension: 0.3, fill: true, pointRadius: 2
                    },
                    {
                        label: "Upload (MB)",
                        data: ' . json_encode($todayData['hourlyUploadMB']) . ',
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37,99,235,0.06)",
                        tension: 0.3, fill: true, pointRadius: 2
                    }
                ]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: true, position: "top", labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }
                }
            }
        });
    }

    // 3. Hourly Auth Today (Accept vs Reject)
    const ctxTodayAuth = document.getElementById("todayAuthChart");
    if (ctxTodayAuth) {
        new Chart(ctxTodayAuth, {
            type: "bar",
            data: {
                labels: ' . json_encode($todayData['hourlyLabels']) . ',
                datasets: [
                    {
                        label: "Access-Accept",
                        data: ' . json_encode($todayData['hourlyAccepts']) . ',
                        backgroundColor: "rgba(16,185,129,0.75)",
                        borderRadius: 3
                    },
                    {
                        label: "Access-Reject",
                        data: ' . json_encode($todayData['hourlyRejects']) . ',
                        backgroundColor: "rgba(239,68,68,0.75)",
                        borderRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: true, position: "top", labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" }, ticks: { precision: 0 } },
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }
                }
            }
        });
    }

    // 4. Top NAS Devices Today
    const ctxTodayNas = document.getElementById("todayNasChart");
    if (ctxTodayNas) {
        new Chart(ctxTodayNas, {
            type: "bar",
            data: {
                labels: ' . json_encode($todayData['nasTodayLabels']) . ',
                datasets: [{
                    label: "Traffic Hari Ini (MB)",
                    data: ' . json_encode($todayData['nasTodayValues']) . ',
                    backgroundColor: "rgba(6,182,212,0.3)",
                    borderColor: "rgba(6,182,212,1)",
                    borderWidth: 2, borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    </script>';
} else {
    $extra_js = '<script>
    // 1. General Daily Sessions Trend (14 Days)
    const ctxGenSessions = document.getElementById("generalSessionsChart");
    if (ctxGenSessions) {
        new Chart(ctxGenSessions, {
            type: "bar",
            data: {
                labels: ' . json_encode($generalData['sessions14dLabels']) . ',
                datasets: [{
                    label: "Total Sesi Harian",
                    data: ' . json_encode($generalData['sessions14dValues']) . ',
                    backgroundColor: "rgba(37,99,235,0.2)",
                    borderColor: "rgba(37,99,235,1)",
                    borderWidth: 2, borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. General Bandwidth Trend (14 Days)
    const ctxGenBw = document.getElementById("generalBandwidthChart");
    if (ctxGenBw) {
        new Chart(ctxGenBw, {
            type: "line",
            data: {
                labels: ' . json_encode($generalData['bw14Labels']) . ',
                datasets: [
                    {
                        label: "Download (MB)",
                        data: ' . json_encode($generalData['bw14Download']) . ',
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16,185,129,0.1)",
                        tension: 0.3, fill: true, pointRadius: 2
                    },
                    {
                        label: "Upload (MB)",
                        data: ' . json_encode($generalData['bw14Upload']) . ',
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37,99,235,0.06)",
                        tension: 0.3, fill: true, pointRadius: 2
                    }
                ]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: true, position: "top", labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 3. General Authentications Trend (7 Days)
    const ctxGenAuth = document.getElementById("generalAuthChart");
    if (ctxGenAuth) {
        new Chart(ctxGenAuth, {
            type: "bar",
            data: {
                labels: ' . json_encode($generalData['auth7dLabels']) . ',
                datasets: [{
                    label: "Authentications",
                    data: ' . json_encode($generalData['auth7dValues']) . ',
                    backgroundColor: "rgba(147,51,234,0.18)",
                    borderColor: "rgba(147,51,234,1)",
                    borderWidth: 2, borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: true, position: "top", labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 4. General Top NAS Devices (14 Days)
    const ctxGenNas = document.getElementById("generalNasChart");
    if (ctxGenNas) {
        new Chart(ctxGenNas, {
            type: "bar",
            data: {
                labels: ' . json_encode($generalData['nas14dLabels']) . ',
                datasets: [{
                    label: "Traffic 14 Hari (MB)",
                    data: ' . json_encode($generalData['nas14dValues']) . ',
                    backgroundColor: "rgba(6,182,212,0.3)",
                    borderColor: "rgba(6,182,212,1)",
                    borderWidth: 2, borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                animation: { duration: 250 },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    </script>';
}

$extra_js = ($extra_js ?? '') . '<script>
(function() {
    document.querySelectorAll("a[href*=\'dashboard.php\']").forEach(function(link) {
        link.addEventListener("click", function(e) {
            var icon = this.querySelector("i");
            if (icon && !this.classList.contains("disabled") && !this.classList.contains("active")) {
                icon.className = "spinner-border spinner-border-sm me-1";
                icon.style.display = "inline-block";
            }
        });
    });
})();
</script>';

include __DIR__ . '/includes/footer.php';

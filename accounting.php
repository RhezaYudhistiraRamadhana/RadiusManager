<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Accounting';
$db = getDB();

$search   = trim($_GET['q'] ?? '');
$ipSearch = trim($_GET['ip'] ?? '');
if (isset($_GET['from'])) {
    $dateFrom = trim($_GET['from']);
    $dateTo   = trim($_GET['to'] ?? date('Y-m-d'));
} else {
    $currMonthStart = date('Y-m-01');
    $hasCurrent = $db->query("SELECT 1 FROM radacct WHERE acctstarttime >= '$currMonthStart 00:00:00' LIMIT 1")->fetch();
    if ($hasCurrent) {
        $dateFrom = $currMonthStart;
        $dateTo   = date('Y-m-d');
    } else {
        $latest = $db->query("SELECT DATE(acctstarttime) d FROM radacct ORDER BY acctstarttime DESC LIMIT 1")->fetch();
        if ($latest && !empty($latest['d'])) {
            $dateFrom = substr($latest['d'], 0, 7) . '-01';
            $dateTo   = $latest['d'];
        } else {
            $dateFrom = date('Y-m-01');
            $dateTo   = date('Y-m-d');
        }
    }
}

// Optimized index-friendly date condition (avoids DATE() wrapper function on acctstarttime)
$where  = "WHERE acctstarttime >= :from_dt AND acctstarttime <= :to_dt";
$params = [
    ':from_dt' => "$dateFrom 00:00:00",
    ':to_dt'   => "$dateTo 23:59:59"
];

if ($search) {
    $where .= " AND username LIKE :q";
    $params[':q'] = "%$search%";
}

if ($ipSearch) {
    $where .= " AND framedipaddress LIKE :ip";
    $params[':ip'] = "%$ipSearch%";
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$totalStmt = $db->prepare("SELECT COUNT(*) FROM radacct $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$lim = (int)$perPage;
$off = (int)$offset;

$stmt = $db->prepare("SELECT username, nasipaddress, framedipaddress,
    acctstarttime, acctstoptime, acctsessiontime,
    acctinputoctets, acctoutputoctets, acctterminatecause
  FROM radacct $where
  ORDER BY acctstarttime DESC LIMIT $lim OFFSET $off");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Summary stats for the period
$statsStmt = $db->prepare("SELECT COUNT(*) AS sessions,
    COALESCE(SUM(acctinputoctets), 0) AS upload,
    COALESCE(SUM(acctoutputoctets), 0) AS download,
    COALESCE(SUM(acctsessiontime), 0) AS duration
  FROM radacct $where");
$statsStmt->execute($params);
$summary = $statsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-clock-history me-2 text-primary"></i>Accounting</h4>
        <p>Session history and traffic records from FreeRADIUS</p>
    </div>
    <a href="export.php?type=accounting&from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $ipSearch ? '&ip=' . urlencode($ipSearch) : '' ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1 fw-semibold">Username</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search username..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Framed IP</label>
                <input type="text" name="ip" class="form-control form-control-sm"
                       placeholder="e.g. 172.16.0.70" value="<?= htmlspecialchars($ipSearch) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Apply Filter</button>
                <a href="accounting.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff"><i class="bi bi-collection text-primary"></i></div>
            <div><div class="stat-value"><?= number_format((int)($summary['sessions'] ?? 0)) ?></div>
                 <div class="stat-label">Sessions</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4"><i class="bi bi-arrow-up-circle text-success"></i></div>
            <div><div class="stat-value" style="font-size:1.1rem"><?= formatBytes($summary['upload'] ?? 0) ?></div>
                 <div class="stat-label">Total Upload</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fefce8"><i class="bi bi-arrow-down-circle text-warning"></i></div>
            <div><div class="stat-value" style="font-size:1.1rem"><?= formatBytes($summary['download'] ?? 0) ?></div>
                 <div class="stat-label">Total Download</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fdf4ff"><i class="bi bi-stopwatch" style="color:#9333ea"></i></div>
            <div><div class="stat-value" style="font-size:1.1rem"><?= formatDuration($summary['duration'] ?? 0) ?></div>
                 <div class="stat-label">Total Duration</div></div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr>
                <th>Username</th><th>NAS IP</th><th>Client IP</th>
                <th>Start</th><th>Stop</th><th>Duration</th>
                <th>Upload</th><th>Download</th><th>Terminate Cause</th>
            </tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>No accounting records found for selected period
            </td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td><strong><?= sanitize($r['username']) ?></strong></td>
                <td class="text-muted small"><?= sanitize($r['nasipaddress']) ?></td>
                <td><code style="font-size:.78rem"><?= sanitize($r['framedipaddress']) ?></code></td>
                <td class="text-muted small"><?= date('d/m/y H:i', strtotime($r['acctstarttime'])) ?></td>
                <td class="text-muted small">
                    <?= $r['acctstoptime'] ? date('d/m/y H:i', strtotime($r['acctstoptime'])) : '<span class="badge badge-online">Online</span>' ?>
                </td>
                <td><?= $r['acctsessiontime'] ? formatDuration($r['acctsessiontime']) : '—' ?></td>
                <td><?= formatBytes($r['acctinputoctets'] ?? 0) ?></td>
                <td><?= formatBytes($r['acctoutputoctets'] ?? 0) ?></td>
                <td><span class="badge bg-light text-secondary border" style="font-size:.7rem">
                    <?= sanitize($r['acctterminatecause'] ?: '—') ?>
                </span></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white border-top d-flex align-items-center justify-content-between">
        <span class="small text-muted"><?= number_format($total) ?> records | Page <?= $page ?> of <?= $pages ?></span>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&ip=<?= urlencode($ipSearch) ?>&from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

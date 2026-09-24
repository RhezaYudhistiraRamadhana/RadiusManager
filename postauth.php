<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Auth Log';
$db = getDB();

$search   = trim($_GET['q'] ?? '');
$filter   = trim($_GET['filter'] ?? '');
if (empty($filter)) {
    $replyParam = trim($_GET['reply'] ?? '');
    if ($replyParam === 'Access-Accept') {
        $filter = 'accept';
    } elseif ($replyParam === 'Access-Reject') {
        $filter = 'reject';
    } else {
        $filter = 'all';
    }
}

if (isset($_GET['from'])) {
    $dateFrom = trim($_GET['from']);
} else {
    $hasToday = $db->query("SELECT 1 FROM radpostauth WHERE authdate >= CURDATE() LIMIT 1")->fetch();
    if ($hasToday) {
        $dateFrom = date('Y-m-d');
    } else {
        $latest = $db->query("SELECT DATE(authdate) d FROM radpostauth ORDER BY id DESC LIMIT 1")->fetch();
        $dateFrom = $latest['d'] ?? date('Y-m-d');
    }
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 30;
$offset   = ($page - 1) * $perPage;

// Check if nasipaddress exists in radpostauth (legacy schema compatibility)
$hasNasIp = dbHasColumn('radpostauth', 'nasipaddress');
$nasCol   = $hasNasIp ? 'nasipaddress' : 'NULL AS nasipaddress';

// Optimized index-friendly datetime condition (avoids DATE(authdate) function call)
$where  = "WHERE authdate >= :from_dt";
$params = [':from_dt' => "$dateFrom 00:00:00"];

if ($search) {
    $where .= " AND username LIKE :q";
    $params[':q'] = "%$search%";
}
if ($filter === 'accept') {
    $where .= " AND reply = 'Access-Accept'";
}
if ($filter === 'reject') {
    $where .= " AND reply != 'Access-Accept'";
}

$totalStmt = $db->prepare("SELECT COUNT(*) FROM radpostauth $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$lim = (int)$perPage;
$off = (int)$offset;

$stmt = $db->prepare("SELECT username, pass, reply, authdate, $nasCol
  FROM radpostauth $where ORDER BY id DESC LIMIT $lim OFFSET $off");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Quick stats
$statsStmt = $db->prepare("SELECT
    SUM(reply = 'Access-Accept') AS accepted,
    SUM(reply != 'Access-Accept') AS rejected
  FROM radpostauth WHERE authdate >= :from_dt");
$statsStmt->execute([':from_dt' => "$dateFrom 00:00:00"]);
$stats = $statsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-check me-2 text-primary"></i>Authentication Log</h4>
        <p>Record of all authentication attempts from FreeRADIUS</p>
    </div>
    <a href="export.php?type=postauth<?= ($filter && $filter !== 'all') ? '&status=' . urlencode($filter) : '' ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $dateFrom ? '&from=' . urlencode($dateFrom) : '' ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <a href="postauth.php?filter=accept<?= !empty($search) ? '&q=' . urlencode($search) : '' ?><?= !empty($dateFrom) ? '&from=' . urlencode($dateFrom) : '' ?>"
           class="stat-card text-decoration-none <?= $filter === 'accept' ? 'border-success shadow-sm' : '' ?>"
           title="Click to filter Accepted authentications">
            <div class="stat-icon" style="background:#f0fdf4">
                <i class="bi bi-check-circle text-success"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value"><?= number_format((int)($stats['accepted'] ?? 0)) ?></div>
                <div class="stat-label d-flex align-items-center justify-content-between">
                    <span>Accepted</span>
                    <span class="badge bg-success-subtle text-success py-0 px-1 font-monospace" style="font-size:.65rem">
                        <?= $filter === 'accept' ? 'Filtered' : 'Filter' ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="postauth.php?filter=reject<?= !empty($search) ? '&q=' . urlencode($search) : '' ?><?= !empty($dateFrom) ? '&from=' . urlencode($dateFrom) : '' ?>"
           class="stat-card text-decoration-none <?= $filter === 'reject' ? 'border-danger shadow-sm' : '' ?>"
           title="Click to filter Rejected authentications">
            <div class="stat-icon" style="background:#fef2f2">
                <i class="bi bi-x-circle text-danger"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value"><?= number_format((int)($stats['rejected'] ?? 0)) ?></div>
                <div class="stat-label d-flex align-items-center justify-content-between">
                    <span>Rejected</span>
                    <span class="badge bg-danger-subtle text-danger py-0 px-1 font-monospace" style="font-size:.65rem">
                        <?= $filter === 'reject' ? 'Filtered' : 'Filter' ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-journal-text text-primary"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($total) ?></div>
                <div class="stat-label">Filtered Records</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fefce8">
                <i class="bi bi-percent text-warning"></i>
            </div>
            <div>
                <?php
                $totAuth = (int)($stats['accepted'] ?? 0) + (int)($stats['rejected'] ?? 0);
                $rate = ($totAuth > 0) ? round(((int)$stats['accepted'] / $totAuth) * 100) : 0;
                ?>
                <div class="stat-value"><?= $rate ?>%</div>
                <div class="stat-label">Success Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search username..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <select name="filter" class="form-select form-select-sm">
                    <option value="all"    <?= ($filter === 'all')    ? 'selected' : '' ?>>All results</option>
                    <option value="accept" <?= ($filter === 'accept') ? 'selected' : '' ?>>Accepted only</option>
                    <option value="reject" <?= ($filter === 'reject') ? 'selected' : '' ?>>Rejected only</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Apply</button>
                <a href="postauth.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr>
                <th>Username</th>
                <?php if ($hasNasIp): ?><th>NAS IP</th><?php endif; ?>
                <th>Result</th>
                <th>Date/Time</th>
            </tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="<?= $hasNasIp ? 4 : 3 ?>" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>No authentication attempts recorded
            </td></tr>
            <?php else: foreach ($records as $r):
                $isAccept = ($r['reply'] === 'Access-Accept'); ?>
            <tr>
                <td>
                    <a href="user-edit.php?username=<?= urlencode($r['username']) ?>" class="text-decoration-none fw-semibold text-primary">
                        <?= sanitize($r['username']) ?>
                    </a>
                </td>
                <?php if ($hasNasIp): ?>
                <td class="text-muted small">
                    <?php if (!empty($r['nasipaddress'])): ?>
                        <a href="nas.php" class="text-decoration-none text-muted font-monospace" title="View in NAS Devices">
                            <?= sanitize($r['nasipaddress']) ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td>
                    <?php if ($isAccept): ?>
                    <a href="postauth.php?filter=accept<?= !empty($dateFrom) ? '&from='.urlencode($dateFrom) : '' ?>" class="badge badge-online rounded-pill text-decoration-none" title="Filter Accepted attempts">
                        <i class="bi bi-check-circle me-1"></i>Accept
                    </a>
                    <?php else: ?>
                    <a href="postauth.php?filter=reject<?= !empty($dateFrom) ? '&from='.urlencode($dateFrom) : '' ?>" class="badge badge-offline rounded-pill text-decoration-none" title="Filter Rejected attempts">
                        <i class="bi bi-x-circle me-1"></i><?= sanitize($r['reply']) ?>
                    </a>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($r['authdate']) ?></td>
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
                <a class="page-link" href="?q=<?= urlencode($search) ?>&from=<?= urlencode($dateFrom) ?>&filter=<?= urlencode($filter) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

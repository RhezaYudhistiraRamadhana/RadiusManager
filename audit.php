<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'Audit Log';
$current_page = 'audit';
$db = getDB();

// Ensure rm_audit_log table exists
$db->exec("CREATE TABLE IF NOT EXISTS `rm_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `operator` VARCHAR(64) NOT NULL,
    `action` VARCHAR(64) NOT NULL,
    `target` VARCHAR(128) DEFAULT NULL,
    `detail` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_operator` (`operator`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Filters
$operator = trim($_GET['operator'] ?? '');
$action   = trim($_GET['action'] ?? '');
$search   = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;

// Build WHERE query
$where = [];
$params = [];

if ($operator !== '') {
    $where[] = "operator = :op";
    $params[':op'] = $operator;
}
if ($action !== '') {
    $where[] = "action = :act";
    $params[':act'] = $action;
}
if ($search !== '') {
    $where[] = "(target LIKE :search OR detail LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($dateFrom !== '') {
    $where[] = "created_at >= :from_dt";
    $params[':from_dt'] = "$dateFrom 00:00:00";
}
if ($dateTo !== '') {
    $where[] = "created_at <= :to_dt";
    $params[':to_dt'] = "$dateTo 23:59:59";
}

$whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM rm_audit_log $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag = paginate($total, $page, $perPage);
$offset = $pag['offset'];
$limit  = $pag['per_page'];

// Fetch paginated logs
$logStmt = $db->prepare("SELECT * FROM rm_audit_log $whereSql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$logStmt->execute($params);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct operators and actions for filter dropdowns
$operators = $db->query("SELECT DISTINCT operator FROM rm_audit_log ORDER BY operator ASC")->fetchAll(PDO::FETCH_COLUMN);
$actions   = $db->query("SELECT DISTINCT action FROM rm_audit_log ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

// Stat counts
$totalEventsToday = (int)$db->query("SELECT COUNT(*) FROM rm_audit_log WHERE created_at >= CURDATE()")->fetchColumn();
$totalAllEvents   = (int)$db->query("SELECT COUNT(*) FROM rm_audit_log")->fetchColumn();
$uniqueOperators  = count($operators);

// Action badge styling helper
function getActionBadge(string $action): string {
    if (str_contains($action, 'create') || str_contains($action, 'enable')) {
        return 'bg-success-subtle text-success border border-success-subtle';
    }
    if (str_contains($action, 'delete') || str_contains($action, 'disable')) {
        return 'bg-danger-subtle text-danger border border-danger-subtle';
    }
    if (str_contains($action, 'batch')) {
        return 'bg-info-subtle text-info-emphasis border border-info-subtle';
    }
    if (str_contains($action, 'update') || str_contains($action, 'edit')) {
        return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    }
    return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
}

// Build export query string
$exportQuery = http_build_query([
    'type'     => 'audit',
    'operator' => $operator,
    'action'   => $action,
    'q'        => $search,
    'from'     => $dateFrom,
    'to'       => $dateTo,
]);

// Build pagination base URL preserving filter query parameters
$paginationQuery = $_GET;
unset($paginationQuery['page']);
$paginationBaseUrl = 'audit.php?' . http_build_query($paginationQuery);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-check me-2 text-primary"></i>Audit Log</h4>
        <p class="text-muted small mb-0">Track administrator activities, security events, and configuration modifications</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export.php?<?= $exportQuery ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-journal-text"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalAllEvents) ?></div>
                <div class="stat-label">Total Logged Events</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalEventsToday) ?></div>
                <div class="stat-label">Events Today</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle text-info">
                <i class="bi bi-person-badge"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($uniqueOperators) ?></div>
                <div class="stat-label">Active Operators</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Search Target / Details</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="e.g. username, plan name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Operator</label>
                <select name="operator" class="form-select form-select-sm">
                    <option value="">All Operators</option>
                    <?php foreach ($operators as $op): ?>
                    <option value="<?= htmlspecialchars($op) ?>" <?= $operator === $op ? 'selected' : '' ?>>
                        <?= htmlspecialchars($op) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Action Type</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= $action === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Date From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Date To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Filter logs">
                    <i class="bi bi-filter"></i>
                </button>
                <?php if ($search || $operator || $action || $dateFrom || $dateTo): ?>
                <a href="audit.php" class="btn btn-sm btn-outline-secondary" title="Reset filters">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold text-secondary">Activity Records</span>
        <span class="badge bg-light text-secondary border"><?= number_format($total) ?> records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width: 170px;">Timestamp</th>
                        <th style="width: 130px;">Operator</th>
                        <th style="width: 150px;">Action</th>
                        <th style="width: 160px;">Target</th>
                        <th>Detail</th>
                        <th style="width: 130px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary opacity-50"></i>
                            No audit records found matching criteria.
                        </td>
                    </tr>
                <?php else: foreach ($logs as $l): ?>
                    <tr>
                        <td class="small text-muted font-monospace">
                            <?= htmlspecialchars($l['created_at']) ?>
                        </td>
                        <td>
                            <span class="fw-semibold text-dark">
                                <i class="bi bi-person-fill text-secondary me-1"></i><?= sanitize($l['operator']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge rounded-pill <?= getActionBadge($l['action']) ?>">
                                <?= sanitize($l['action']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($l['target'])): ?>
                            <code class="text-primary fw-semibold"><?= sanitize($l['target']) ?></code>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary">
                            <?= sanitize($l['detail'] ?? '—') ?>
                        </td>
                        <td class="small text-muted font-monospace">
                            <?= sanitize($l['ip_address'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-top d-flex align-items-center justify-content-between py-2">
        <span class="small text-muted">
            Page <?= $pag['page'] ?> of <?= $pag['total_pages'] ?> (<?= number_format($total) ?> total)
        </span>
        <?= paginationLinks($pag, $paginationBaseUrl) ?>
    </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/includes/footer.php';


<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'Vouchers & Hotspot';
$current_page = 'vouchers';
$db = getDB();

// Ensure rm_vouchers table exists
$db->exec("CREATE TABLE IF NOT EXISTS `rm_vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(64) NOT NULL,
    `plan_id` INT DEFAULT NULL,
    `status` ENUM('unused','active','expired') NOT NULL DEFAULT 'unused',
    `created_by` VARCHAR(64) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `used_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_batch` (`batch_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Optional: Background synchronization of voucher status
// 1. Mark 'active' if session exists in radacct and currently 'unused'
try {
    $db->exec("
        UPDATE rm_vouchers v
        INNER JOIN (SELECT DISTINCT username, MIN(acctstarttime) AS first_seen FROM radacct GROUP BY username) ra
            ON ra.username = v.username
        SET v.status = 'active', v.used_at = COALESCE(v.used_at, ra.first_seen)
        WHERE v.status = 'unused'
    ");
} catch (Exception $e) {}

// Filters & Search
$q       = trim($_GET['q'] ?? '');
$batch   = trim($_GET['batch'] ?? '');
$status  = trim($_GET['status'] ?? '');
$plan_id = (int)($_GET['plan_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = defined('ROWS_PER_PAGE') ? ROWS_PER_PAGE : 20;
$offset  = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(v.username LIKE ? OR v.batch_name LIKE ? OR v.created_by LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($batch !== '') {
    $where[] = "v.batch_name = ?";
    $params[] = $batch;
}

if (in_array($status, ['unused', 'active', 'expired'], true)) {
    $where[] = "v.status = ?";
    $params[] = $status;
}

if ($plan_id > 0) {
    $where[] = "v.plan_id = ?";
    $params[] = $plan_id;
}

$whereSql = implode(' AND ', $where);

// Count total matching
$countStmt = $db->prepare("SELECT COUNT(*) FROM rm_vouchers v WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Fetch vouchers with plan details and expiration from radcheck
$sql = "
    SELECT v.*,
           p.name AS plan_name,
           p.groupname AS plan_group,
           p.dl_kbps, p.ul_kbps,
           rc_exp.value AS expiration_date
    FROM rm_vouchers v
    LEFT JOIN rm_plans p ON p.id = v.plan_id
    LEFT JOIN radcheck rc_exp ON rc_exp.username = v.username AND rc_exp.attribute = 'Expiration'
    WHERE $whereSql
    ORDER BY v.id DESC
    LIMIT $limit OFFSET $offset
";
$dataStmt = $db->prepare($sql);
$dataStmt->execute($params);
$vouchers = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Overall Stats
$statsStmt = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) AS unused,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired
    FROM rm_vouchers
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'unused' => 0, 'active' => 0, 'expired' => 0];

// Batches list for filter
$batches = $db->query("SELECT batch_name, COUNT(*) AS count FROM rm_vouchers GROUP BY batch_name ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Plans list for filter
$plans = [];
if (dbTableExists('rm_plans')) {
    $plans = $db->query("SELECT id, name FROM rm_plans ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-ticket-perforated me-2 text-primary"></i>Hotspot Vouchers</h4>
        <p class="text-muted mb-0">Prepaid subscriber vouchers and guest WiFi ticket management</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($batch)): ?>
        <a href="voucher-print.php?batch=<?= urlencode($batch) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Print Batch "<?= htmlspecialchars($batch) ?>"
        </a>
        <?php endif; ?>
        <a href="voucher-generate.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Generate Vouchers
        </a>
    </div>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($_SESSION['flash_success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($_SESSION['flash_error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- Stats Overview -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="vouchers.php" class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-ticket-perforated"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format((int)$stats['total']) ?></div>
                <div class="stat-label">Total Vouchers</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="vouchers.php?status=unused" class="stat-card">
            <div class="stat-icon bg-secondary-subtle text-secondary">
                <i class="bi bi-inbox"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format((int)$stats['unused']) ?></div>
                <div class="stat-label">Unused / Ready</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="vouchers.php?status=active" class="stat-card">
            <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-broadcast"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format((int)$stats['active']) ?></div>
                <div class="stat-label">Active / In Use</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="vouchers.php?status=expired" class="stat-card">
            <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format((int)$stats['expired']) ?></div>
                <div class="stat-label">Expired</div>
            </div>
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="vouchers.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search username, batch, admin..." value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="batch" class="form-select form-select-sm">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                    <option value="<?= htmlspecialchars($b['batch_name']) ?>" <?= $batch === $b['batch_name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['batch_name']) ?> (<?= $b['count'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="unused" <?= $status === 'unused' ? 'selected' : '' ?>>Unused</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
            <?php if (!empty($plans)): ?>
            <div class="col-6 col-md-2">
                <select name="plan_id" class="form-select form-select-sm">
                    <option value="">All Plans</option>
                    <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $plan_id === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto ms-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($q !== '' || $batch !== '' || $status !== '' || $plan_id > 0): ?>
                <a href="vouchers.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Vouchers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Voucher Inventory (<?= number_format($totalRows) ?> records)</h6>
        <?php if (!empty($batch)): ?>
        <form method="POST" action="voucher-delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete the entire batch <?= htmlspecialchars($batch) ?>? This deletes all associated RADIUS users!');">
            <?= csrfField() ?>
            <input type="hidden" name="delete_batch" value="<?= htmlspecialchars($batch) ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i> Delete Batch
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 38px;">
                        <input type="checkbox" class="form-check-input" id="checkAll">
                    </th>
                    <th>Batch</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Rate Plan</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Expiry / Used</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vouchers)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-ticket-perforated fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        No vouchers found matching your filter criteria.
                        <div class="mt-3">
                            <a href="voucher-generate.php" class="btn btn-sm btn-primary">Generate New Batch</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($vouchers as $v): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input row-select" value="<?= $v['id'] ?>" data-username="<?= htmlspecialchars($v['username']) ?>">
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['batch_name']) ?></span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1.5">
                            <span class="fw-bold font-monospace text-primary"><?= htmlspecialchars($v['username']) ?></span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($v['username']) ?>')" title="Copy username">
                                <i class="bi bi-clipboard" style="font-size:.75rem"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1.5">
                            <span class="font-monospace text-secondary pass-text" id="pass-<?= $v['id'] ?>" data-pass="<?= htmlspecialchars($v['password']) ?>">••••••••</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" onclick="togglePass(<?= $v['id'] ?>)" title="Show/Hide">
                                <i class="bi bi-eye" id="eye-<?= $v['id'] ?>" style="font-size:.75rem"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($v['plan_name'])): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bi bi-speedometer2 me-1"></i><?= htmlspecialchars($v['plan_name']) ?>
                            </span>
                        <?php elseif (!empty($v['plan_group'])): ?>
                            <span class="badge bg-secondary-subtle text-secondary">
                                <?= htmlspecialchars($v['plan_group']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted small">Standard</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($v['status'] === 'active'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check-circle-fill me-1"></i>Active
                            </span>
                        <?php elseif ($v['status'] === 'expired'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bi bi-x-circle-fill me-1"></i>Expired
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                <i class="bi bi-hourglass me-1"></i>Unused
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted"><?= date('d M Y, H:i', strtotime($v['created_at'])) ?></small>
                        <?php if (!empty($v['created_by'])): ?>
                        <div class="text-muted" style="font-size:.7rem">by <?= htmlspecialchars($v['created_by']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($v['expiration_date'])): ?>
                            <small class="text-dark d-block"><?= htmlspecialchars($v['expiration_date']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($v['used_at'])): ?>
                            <small class="text-muted" style="font-size:.7rem">First seen: <?= date('d M Y H:i', strtotime($v['used_at'])) ?></small>
                        <?php elseif (empty($v['expiration_date'])): ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <a href="voucher-print.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Print Voucher Card">
                                <i class="bi bi-printer"></i>
                            </a>
                            <form method="POST" action="voucher-delete.php" class="d-inline" onsubmit="return confirm('Delete voucher <?= htmlspecialchars($v['username']) ?>?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Delete Voucher">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= number_format($totalRows) ?> vouchers</small>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- Floating Action Bar for Batch Operations -->
<div id="voucherBatchBar" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 bg-dark text-white rounded-3 shadow-lg px-4 py-2.5 d-none align-items-center gap-3" style="z-index: 1050;">
    <span class="small fw-semibold"><span id="selectedCount">0</span> selected</span>
    <form method="POST" action="voucher-delete.php" id="bulkDeleteForm" class="m-0" onsubmit="return confirm('Delete selected vouchers from RADIUS?');">
        <?= csrfField() ?>
        <input type="hidden" name="bulk_ids" id="bulkIdsInput" value="">
        <button type="submit" class="btn btn-sm btn-danger">
            <i class="bi bi-trash me-1"></i> Delete
        </button>
    </form>
    <button type="button" class="btn btn-sm btn-light" id="btnPrintSelected">
        <i class="bi bi-printer me-1"></i> Print Selected
    </button>
</div>

<script>
function togglePass(id) {
    const el = document.getElementById('pass-' + id);
    const eye = document.getElementById('eye-' + id);
    if (el.innerText === '••••••••') {
        el.innerText = el.getAttribute('data-pass');
        eye.className = 'bi bi-eye-slash';
    } else {
        el.innerText = '••••••••';
        eye.className = 'bi bi-eye';
    }
}

const checkAll = document.getElementById('checkAll');
const rowCheckboxes = document.querySelectorAll('.row-select');
const batchBar = document.getElementById('voucherBatchBar');
const selectedCount = document.getElementById('selectedCount');
const bulkIdsInput = document.getElementById('bulkIdsInput');
const btnPrintSelected = document.getElementById('btnPrintSelected');

function updateBatchBar() {
    const checked = Array.from(rowCheckboxes).filter(cb => cb.checked);
    const count = checked.length;
    if (count > 0) {
        batchBar.classList.remove('d-none');
        batchBar.classList.add('d-flex');
        selectedCount.textContent = count;
        bulkIdsInput.value = checked.map(cb => cb.value).join(',');
    } else {
        batchBar.classList.add('d-none');
        batchBar.classList.remove('d-flex');
    }
}

if (checkAll) {
    checkAll.addEventListener('change', () => {
        rowCheckboxes.forEach(cb => cb.checked = checkAll.checked);
        updateBatchBar();
    });
}

rowCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
        if (checkAll) {
            checkAll.checked = Array.from(rowCheckboxes).every(c => c.checked);
            checkAll.indeterminate = Array.from(rowCheckboxes).some(c => c.checked) && !checkAll.checked;
        }
        updateBatchBar();
    });
});

if (btnPrintSelected) {
    btnPrintSelected.addEventListener('click', () => {
        const ids = bulkIdsInput.value;
        if (ids) {
            window.open('voucher-print.php?ids=' + encodeURIComponent(ids), '_blank');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

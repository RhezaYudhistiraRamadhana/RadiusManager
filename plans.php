<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'Rate Plans';
$current_page = 'plans';
$db = getDB();

// Ensure rm_plans table exists
$db->exec("CREATE TABLE IF NOT EXISTS `rm_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `groupname` VARCHAR(64) NOT NULL,
    `description` TEXT,
    `dl_kbps` INT DEFAULT 0,
    `ul_kbps` INT DEFAULT 0,
    `data_mb` INT DEFAULT 0,
    `time_hours` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Fetch all plans with subscriber counts
$stmt = $db->query("
    SELECT p.*, COUNT(DISTINCT rug.username) AS subscriber_count
    FROM rm_plans p
    LEFT JOIN radusergroup rug ON rug.groupname = p.groupname
    GROUP BY p.id
    ORDER BY p.name ASC
");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPlans = count($plans);
$totalSubscribers = 0;
$maxSpeed = 0;
foreach ($plans as $p) {
    $totalSubscribers += (int)$p['subscriber_count'];
    if ((int)$p['dl_kbps'] > $maxSpeed) {
        $maxSpeed = (int)$p['dl_kbps'];
    }
}

function formatSpeed(int $kbps): string {
    if ($kbps <= 0) return 'Unlimited';
    if ($kbps >= 1024) {
        $mbps = round($kbps / 1024, 1);
        return $mbps . ' Mbps';
    }
    return number_format($kbps) . ' Kbps';
}

function formatDataCap(int $mb): string {
    if ($mb <= 0) return 'Unlimited';
    if ($mb >= 1024) {
        $gb = round($mb / 1024, 1);
        return $gb . ' GB';
    }
    return number_format($mb) . ' MB';
}

function formatValidity(int $hours): string {
    if ($hours <= 0) return 'Unlimited';
    if ($hours >= 24 && $hours % 24 === 0) {
        $days = (int)($hours / 24);
        return $days . ' ' . ($days === 1 ? 'Day' : 'Days');
    }
    return $hours . ' ' . ($hours === 1 ? 'Hour' : 'Hours');
}

$flash = getFlash();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-speedometer2 me-2 text-primary"></i>Rate Plans & Bandwidth</h4>
        <p class="text-muted small mb-0">Manage service tiers, speed limits, data quotas, and RADIUS reply profiles</p>
    </div>
    <div>
        <a href="plan-add.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Create New Plan
        </a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= $flash['msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-layers"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalPlans) ?></div>
                <div class="stat-label">Active Rate Plans</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalSubscribers) ?></div>
                <div class="stat-label">Subscribers on Plans</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle text-info">
                <i class="bi bi-lightning-charge"></i>
            </div>
            <div>
                <div class="stat-value"><?= formatSpeed($maxSpeed) ?></div>
                <div class="stat-label">Max Bandwidth Tier</div>
            </div>
        </div>
    </div>
</div>

<!-- Plans Table -->
<div class="card">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold text-secondary">Configured Service Packages</span>
        <span class="badge bg-light text-secondary border"><?= $totalPlans ?> plan<?= $totalPlans !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Target Group</th>
                        <th>Speed Limits (Down / Up)</th>
                        <th>Data Quota</th>
                        <th>Session Limit</th>
                        <th>Subscribers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($plans)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-speedometer2 fs-2 d-block mb-2 text-secondary opacity-50"></i>
                            No rate plans defined yet.
                            <div class="mt-2">
                                <a href="plan-add.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Create First Plan
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($plans as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark"><?= sanitize($p['name']) ?></div>
                            <?php if (!empty($p['description'])): ?>
                            <div class="text-muted small text-truncate" style="max-width: 240px;" title="<?= sanitize($p['description']) ?>">
                                <?= sanitize($p['description']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-collection me-1 text-secondary"></i><?= sanitize($p['groupname']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bi bi-arrow-down me-1"></i><?= formatSpeed((int)$p['dl_kbps']) ?>
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <i class="bi bi-arrow-up me-1"></i><?= formatSpeed((int)$p['ul_kbps']) ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ((int)$p['data_mb'] > 0): ?>
                            <span class="fw-semibold text-dark"><?= formatDataCap((int)$p['data_mb']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">Unlimited</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$p['time_hours'] > 0): ?>
                            <span class="fw-semibold text-dark"><?= formatValidity((int)$p['time_hours']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">Unlimited</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$p['subscriber_count'] > 0): ?>
                            <a href="users.php?q=<?= urlencode($p['groupname']) ?>" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none">
                                <i class="bi bi-person me-1"></i><?= number_format($p['subscriber_count']) ?> users
                            </a>
                            <?php else: ?>
                            <span class="badge bg-light text-muted border">0 users</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="plan-edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit Plan">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                    onclick="confirmDeletePlan(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name']), ENT_QUOTES) ?>')"
                                    title="Delete Plan">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden Plan Delete Form -->
<form id="deletePlanForm" method="POST" action="plan-delete.php">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deletePlanId">
</form>

<?php
$extra_js = '<script>
function confirmDeletePlan(id, name) {
    if (confirm("Are you sure you want to delete rate plan \\"" + name + "\\"?\\nAssociated bandwidth attributes in radgroupreply will be removed.")) {
        document.getElementById("deletePlanId").value = id;
        document.getElementById("deletePlanForm").submit();
    }
}
</script>';

include __DIR__ . '/includes/footer.php';


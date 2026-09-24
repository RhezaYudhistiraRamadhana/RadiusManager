<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Detect CLI execution
$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || in_array('--cli', $argv ?? []) || in_array('--cron', $argv ?? []));

if (!$isCli) {
    requireLogin();
}

$page_title = 'Account Expiry Warnings';
$current_page = 'expiry-check';
$db = getDB();

$warnDays = defined('EXPIRY_WARN_DAYS') ? (int)EXPIRY_WARN_DAYS : 7;
$now = time();
$warnHorizon = $now + ($warnDays * 86400);

// ── Handle Action POSTs (Web UI only) ──────────────────────────────────────
if (!$isCli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action   = trim($_POST['action'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if ($action === 'extend' && !empty($username)) {
        $days = max(1, (int)($_POST['days'] ?? 30));
        // Check current expiry
        $curStmt = $db->prepare("SELECT value FROM radcheck WHERE username = ? AND attribute = 'Expiration' LIMIT 1");
        $curStmt->execute([$username]);
        $curVal = $curStmt->fetchColumn();

        $baseTime = ($curVal && strtotime($curVal) > $now) ? strtotime($curVal) : $now;
        $newExpiry = date('d M Y', strtotime("+$days days", $baseTime));

        $db->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'")->execute([$username]);
        $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)")
           ->execute([$username, $newExpiry]);

        auditLog('user.extend_expiry', $username, "Extended by $days days until $newExpiry");
        flash("Expiry date for user <strong>" . sanitize($username) . "</strong> extended by $days days (until $newExpiry).", 'success');
        header('Location: expiry-check.php');
        exit;
    }

    if ($action === 'disable' && !empty($username)) {
        $exists = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ? AND attribute = 'Auth-Type' AND value = 'Reject'");
        $exists->execute([$username]);
        if ($exists->fetchColumn() == 0) {
            $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')")
               ->execute([$username]);
        }
        auditLog('user.disable', $username, 'Disabled from Expiry Warning panel');
        flash("User <strong>" . sanitize($username) . "</strong> has been disabled (Auth-Type := Reject).", 'warning');
        header('Location: expiry-check.php');
        exit;
    }

    if ($action === 'remove_expiry' && !empty($username)) {
        $db->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'")->execute([$username]);
        auditLog('user.remove_expiry', $username, 'Removed Expiration attribute');
        flash("Expiration limit removed for user <strong>" . sanitize($username) . "</strong>.", 'info');
        header('Location: expiry-check.php');
        exit;
    }
}

// ── Query all users with an Expiration attribute ──────────────────────────
$stmt = $db->query("
    SELECT rc.username, rc.value AS expiry_str,
           MAX(CASE WHEN rc2.attribute = 'Auth-Type' AND rc2.value = 'Reject' THEN 1 ELSE 0 END) AS is_disabled,
           GROUP_CONCAT(DISTINCT rug.groupname ORDER BY rug.priority SEPARATOR ', ') AS groupname
    FROM radcheck rc
    LEFT JOIN radcheck rc2 ON rc2.username = rc.username
    LEFT JOIN radusergroup rug ON rug.username = rc.username
    WHERE rc.attribute = 'Expiration'
    GROUP BY rc.username, rc.value
");
$allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resolve profile information
$hasUserinfo = dbTableExists('userinfo');
$userProfiles = [];
if (!empty($allRows) && $hasUserinfo) {
    $uList = array_unique(array_column($allRows, 'username'));
    $placeholders = implode(',', array_fill(0, count($uList), '?'));
    $uStmt = $db->prepare("SELECT username, firstname, lastname, department, email FROM userinfo WHERE username IN ($placeholders)");
    $uStmt->execute($uList);
    while ($p = $uStmt->fetch(PDO::FETCH_ASSOC)) {
        $userProfiles[$p['username']] = $p;
    }
}

$expiredUsers  = [];
$expiringUsers = [];
$validUsers    = [];

foreach ($allRows as $r) {
    $ts = strtotime($r['expiry_str']);
    if (!$ts) continue; // invalid date string

    $r['timestamp'] = $ts;
    $r['profile']   = $userProfiles[$r['username']] ?? null;

    if ($ts < $now) {
        $r['status_type'] = 'expired';
        $r['days_diff']   = round(($now - $ts) / 86400, 1);
        $expiredUsers[]   = $r;
    } elseif ($ts <= $warnHorizon) {
        $r['status_type'] = 'expiring_soon';
        $r['days_diff']   = round(($ts - $now) / 86400, 1);
        $expiringUsers[]  = $r;
    } else {
        $r['status_type'] = 'valid';
        $r['days_diff']   = round(($ts - $now) / 86400, 1);
        $validUsers[]     = $r;
    }
}

// Sort each by closest date
usort($expiredUsers, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']); // Most recently expired first
usort($expiringUsers, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']); // Expiring soonest first
usort($validUsers, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

// ── CLI / Cron Execution Mode ─────────────────────────────────────────────
if ($isCli) {
    echo "====================================================\n";
    echo "  RadiusManager Account Expiry Audit (CLI/Cron)\n";
    echo "====================================================\n";
    echo "Timestamp:      " . date('Y-m-d H:i:s') . "\n";
    echo "Warning Window: Next $warnDays days (until " . date('Y-m-d H:i:s', $warnHorizon) . ")\n\n";

    echo "1. Already Expired Accounts (" . count($expiredUsers) . "):\n";
    foreach ($expiredUsers as $u) {
        echo "   - {$u['username']} (Expired: {$u['expiry_str']}, ~{$u['days_diff']} days ago, Disabled: " . ($u['is_disabled'] ? 'Yes' : 'No') . ")\n";
    }

    echo "\n2. Expiring Within $warnDays Days (" . count($expiringUsers) . "):\n";
    foreach ($expiringUsers as $u) {
        echo "   - {$u['username']} (Expires: {$u['expiry_str']}, in ~{$u['days_diff']} days)\n";
    }

    echo "\n3. Healthy Monitored Accounts (" . count($validUsers) . ")\n";
    echo "----------------------------------------------------\n";

    auditLog('cron.expiry_check', 'system', "Scanned: " . count($allRows) . " accounts. Expired: " . count($expiredUsers) . ", Expiring soon: " . count($expiringUsers));
    echo "[DONE] Audit log recorded.\n";
    exit(0);
}

// ── Web UI ────────────────────────────────────────────────────────────────
$filterView = $_GET['view'] ?? 'attention'; // 'attention' | 'expired' | 'expiring' | 'all'

if ($filterView === 'expired') {
    $displayRows = $expiredUsers;
} elseif ($filterView === 'expiring') {
    $displayRows = $expiringUsers;
} elseif ($filterView === 'all') {
    $displayRows = array_merge($expiringUsers, $expiredUsers, $validUsers);
} else {
    // 'attention' default: expiring soon + expired
    $displayRows = array_merge($expiringUsers, $expiredUsers);
}

$flash = getFlash();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-clock-history me-2 text-warning"></i>Account Expiry Warnings</h4>
        <p class="text-muted small mb-0">Track upcoming account expirations and manage renewals within <?= $warnDays ?> days</p>
    </div>
    <div class="d-flex gap-2">
        <a href="users.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>All Users
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
    <div class="col-sm-6 col-lg-3">
        <a href="?view=expiring" class="stat-card <?= $filterView === 'expiring' ? 'border-warning shadow-sm' : '' ?>">
            <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-value text-warning-emphasis"><?= count($expiringUsers) ?></div>
                <div class="stat-label">Expiring &le; <?= $warnDays ?> Days</div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a href="?view=expired" class="stat-card <?= $filterView === 'expired' ? 'border-danger shadow-sm' : '' ?>">
            <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= count($expiredUsers) ?></div>
                <div class="stat-label">Already Expired</div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a href="?view=attention" class="stat-card <?= $filterView === 'attention' ? 'border-primary shadow-sm' : '' ?>">
            <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-bell"></i>
            </div>
            <div>
                <div class="stat-value"><?= count($expiringUsers) + count($expiredUsers) ?></div>
                <div class="stat-label">Action Required</div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a href="?view=all" class="stat-card <?= $filterView === 'all' ? 'border-secondary shadow-sm' : '' ?>">
            <div class="stat-icon bg-light text-secondary">
                <i class="bi bi-calendar3"></i>
            </div>
            <div>
                <div class="stat-value"><?= count($allRows) ?></div>
                <div class="stat-label">Total Monitored</div>
            </div>
        </a>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item">
        <a class="nav-link btn-sm <?= $filterView === 'attention' ? 'active' : 'bg-white border text-secondary' ?>" href="?view=attention">
            <i class="bi bi-bell me-1"></i>Action Required (<?= count($expiringUsers) + count($expiredUsers) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link btn-sm <?= $filterView === 'expiring' ? 'active' : 'bg-white border text-secondary' ?>" href="?view=expiring">
            <i class="bi bi-hourglass-split me-1"></i>Expiring Soon (<?= count($expiringUsers) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link btn-sm <?= $filterView === 'expired' ? 'active' : 'bg-white border text-secondary' ?>" href="?view=expired">
            <i class="bi bi-slash-circle me-1"></i>Expired (<?= count($expiredUsers) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link btn-sm <?= $filterView === 'all' ? 'active' : 'bg-white border text-secondary' ?>" href="?view=all">
            <i class="bi bi-list-check me-1"></i>All Monitored (<?= count($allRows) ?>)
        </a>
    </li>
</ul>

<!-- Table Card -->
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Username & Info</th>
                        <th>Assigned Group</th>
                        <th>Expiration Date</th>
                        <th>Time Remaining</th>
                        <th>Account Status</th>
                        <th>Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($displayRows)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success opacity-75"></i>
                            No accounts in this category require attention.
                        </td>
                    </tr>
                <?php else: foreach ($displayRows as $u): ?>
                    <tr class="<?= $u['status_type'] === 'expired' ? 'table-danger-subtle' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-person-circle fs-5 me-2 text-secondary"></i>
                                <div>
                                    <strong><?= sanitize($u['username']) ?></strong>
                                    <?php if (!empty($u['profile']['firstname'])): ?>
                                    <div class="small text-muted">
                                        <?= sanitize($u['profile']['firstname']) ?>
                                        <?php if (!empty($u['profile']['department'])): ?>
                                        <span class="badge bg-light text-secondary border ms-1"><?= sanitize($u['profile']['department']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($u['groupname'])): ?>
                            <span class="badge bg-light text-dark border"><?= sanitize($u['groupname']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?= sanitize($u['expiry_str']) ?></code>
                        </td>
                        <td>
                            <?php if ($u['status_type'] === 'expired'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                Expired <?= $u['days_diff'] ?>d ago
                            </span>
                            <?php elseif ($u['status_type'] === 'expiring_soon'): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="bi bi-clock me-1"></i>In <?= $u['days_diff'] ?> days
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                In <?= $u['days_diff'] ?> days
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($u['is_disabled'])): ?>
                            <span class="badge bg-danger">Disabled</span>
                            <?php else: ?>
                            <span class="badge bg-success-subtle text-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <!-- Extend Dropdown -->
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Extend
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                        <li><h6 class="dropdown-header">Extend Validity</h6></li>
                                        <li>
                                            <form method="POST">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="extend">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                                <input type="hidden" name="days" value="7">
                                                <button type="submit" class="dropdown-item">+ 7 Days</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="extend">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                                <input type="hidden" name="days" value="30">
                                                <button type="submit" class="dropdown-item">+ 30 Days (1 Month)</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="extend">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                                <input type="hidden" name="days" value="90">
                                                <button type="submit" class="dropdown-item">+ 90 Days</button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <form method="POST" onsubmit="return confirm('Remove expiration date from <?= htmlspecialchars($u['username']) ?> completely?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="remove_expiry">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                                <button type="submit" class="dropdown-item text-secondary">No Expiry Limit</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Disable button if active -->
                                <?php if (empty($u['is_disabled'])): ?>
                                <form method="POST" class="d-inline mb-0" onsubmit="return confirm('Disable account for <?= htmlspecialchars($u['username']) ?>?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="disable">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2" title="Disable user">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Edit link -->
                                <a href="user-edit.php?username=<?= urlencode($u['username']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Edit Profile">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card bg-light border-0 mb-4">
    <div class="card-body py-3">
        <h6 class="fw-semibold text-dark mb-1"><i class="bi bi-terminal me-1 text-primary"></i>Automated Cron Monitoring</h6>
        <p class="small text-muted mb-0">
            You can configure a daily system cron job to audit and log expiration states automatically:
            <code class="d-block mt-1 p-2 bg-white rounded border">0 2 * * * php <?= __DIR__ ?>/expiry-check.php --cli &gt;&gt; /var/log/radius_expiry.log 2&gt;&amp;1</code>
        </p>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';

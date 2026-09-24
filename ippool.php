<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'IP Pools';
$db = getDB();

$tableExists = dbTableExists('radippool');
$flash = getFlash();

// Handle Form Actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $tableExists) {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');
    $returnQuery = trim($_POST['return_query'] ?? '');

    if ($action === 'release') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $ipRow = dbFetch("SELECT framedipaddress, username, pool_name FROM radippool WHERE id = ?", [$id]);
            if ($ipRow) {
                // Release lease by setting expiry_time to past and clearing username
                dbQuery("UPDATE radippool SET expiry_time = NOW() - INTERVAL 1 SECOND, username = '' WHERE id = ?", [$id]);
                flash('success', "IP address <strong>" . sanitize($ipRow['framedipaddress']) . "</strong> successfully released back to pool <strong>" . sanitize($ipRow['pool_name']) . "</strong>.");
            } else {
                flash('danger', "IP record not found.");
            }
        }
        header('Location: ippool.php' . ($returnQuery ? '?' . $returnQuery : ''));
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $ipRow = dbFetch("SELECT framedipaddress, pool_name FROM radippool WHERE id = ?", [$id]);
            if ($ipRow) {
                dbQuery("DELETE FROM radippool WHERE id = ?", [$id]);
                flash('success', "IP address <strong>" . sanitize($ipRow['framedipaddress']) . "</strong> removed from pool <strong>" . sanitize($ipRow['pool_name']) . "</strong>.");
            } else {
                flash('danger', "IP record not found.");
            }
        }
        header('Location: ippool.php' . ($returnQuery ? '?' . $returnQuery : ''));
        exit;
    }

    if ($action === 'add') {
        $poolName = trim($_POST['pool_name'] ?? '');
        $newPoolName = trim($_POST['new_pool_name'] ?? '');
        if (!empty($newPoolName)) {
            $poolName = $newPoolName;
        }

        $ipMode = trim($_POST['ip_mode'] ?? 'single');
        $nasIp = trim($_POST['nasipaddress'] ?? '');

        if (empty($poolName)) {
            flash('danger', "Please specify a valid pool name.");
            header('Location: ippool.php');
            exit;
        }

        if ($ipMode === 'range') {
            $rangeStart = trim($_POST['range_start'] ?? '');
            $rangeEnd = trim($_POST['range_end'] ?? '');

            if (!filter_var($rangeStart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($rangeEnd, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                flash('danger', "Both starting and ending IP addresses must be valid IPv4 addresses.");
                header('Location: ippool.php');
                exit;
            }

            $startLong = ip2long($rangeStart);
            $endLong = ip2long($rangeEnd);

            if ($startLong === false || $endLong === false || $endLong < $startLong) {
                flash('danger', "End IP address must be greater than or equal to start IP address.");
                header('Location: ippool.php');
                exit;
            }

            $count = ($endLong - $startLong) + 1;
            if ($count > 512) {
                flash('danger', "Range exceeds maximum batch limit of 512 IP addresses (requested: $count).");
                header('Location: ippool.php');
                exit;
            }

            $added = 0;
            $skipped = 0;
            $checkStmt = $db->prepare("SELECT id FROM radippool WHERE pool_name = ? AND framedipaddress = ? LIMIT 1");
            $insertStmt = $db->prepare("INSERT INTO radippool (pool_name, framedipaddress, nasipaddress, expiry_time, username) VALUES (?, ?, ?, NOW() - INTERVAL 1 SECOND, '')");

            for ($current = $startLong; $current <= $endLong; $current++) {
                $ipStr = long2ip($current);
                $checkStmt->execute([$poolName, $ipStr]);
                if ($checkStmt->fetch()) {
                    $skipped++;
                } else {
                    $insertStmt->execute([$poolName, $ipStr, $nasIp]);
                    $added++;
                }
            }

            $msg = "Provisioned $added IP addresses into pool <strong>" . sanitize($poolName) . "</strong>.";
            if ($skipped > 0) {
                $msg .= " ($skipped existing IPs were skipped).";
            }
            flash('success', $msg);
            header('Location: ippool.php?pool=' . urlencode($poolName));
            exit;
        } else {
            // Single IP
            $singleIp = trim($_POST['single_ip'] ?? '');
            if (!filter_var($singleIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                flash('danger', "Invalid IPv4 address entered: " . sanitize($singleIp));
                header('Location: ippool.php');
                exit;
            }

            $exists = dbFetch("SELECT id FROM radippool WHERE pool_name = ? AND framedipaddress = ?", [$poolName, $singleIp]);
            if ($exists) {
                flash('warning', "IP address <strong>" . sanitize($singleIp) . "</strong> is already registered in pool <strong>" . sanitize($poolName) . "</strong>.");
            } else {
                dbQuery("INSERT INTO radippool (pool_name, framedipaddress, nasipaddress, expiry_time, username) VALUES (?, ?, ?, NOW() - INTERVAL 1 SECOND, '')", [$poolName, $singleIp, $nasIp]);
                flash('success', "IP address <strong>" . sanitize($singleIp) . "</strong> added to pool <strong>" . sanitize($poolName) . "</strong>.");
            }
            header('Location: ippool.php?pool=' . urlencode($poolName));
            exit;
        }
    }
}

// Data Fetching
$pools = [];
$totalIps = 0;
$activeLeases = 0;
$availableIps = 0;
$totalPoolCount = 0;
$rows = [];
$pagination = null;

if ($tableExists) {
    // Distinct pools list for dropdowns
    $poolRows = dbFetchAll("SELECT DISTINCT pool_name FROM radippool WHERE pool_name != '' ORDER BY pool_name ASC");
    $pools = array_column($poolRows, 'pool_name');

    // Stats
    $totalIps = dbCount("SELECT COUNT(*) FROM radippool");
    $activeLeases = dbCount("SELECT COUNT(*) FROM radippool WHERE expiry_time > NOW() AND username != ''");
    $availableIps = dbCount("SELECT COUNT(*) FROM radippool WHERE expiry_time <= NOW() OR username = ''");
    $totalPoolCount = count($pools);

    // Filters
    $filterPool = trim($_GET['pool'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');
    $search = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;

    $where = [];
    $params = [];

    if (!empty($filterPool)) {
        $where[] = "pool_name = :pool";
        $params[':pool'] = $filterPool;
    }

    if ($filterStatus === 'active') {
        $where[] = "(expiry_time > NOW() AND username != '')";
    } elseif ($filterStatus === 'available') {
        $where[] = "(expiry_time <= NOW() OR username = '')";
    }

    if (!empty($search)) {
        $where[] = "(framedipaddress LIKE :q1 OR username LIKE :q2)";
        $params[':q1'] = '%' . $search . '%';
        $params[':q2'] = '%' . $search . '%';
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count matching rows
    $countSql = "SELECT COUNT(*) FROM radippool $whereSql";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalMatching = (int)$countStmt->fetchColumn();

    $pagination = paginate($totalMatching, $page, $perPage);

    // Fetch page rows
    $dataSql = "SELECT id, pool_name, framedipaddress, nasipaddress, calledstationid, callingstationid, expiry_time, username, pool_key
                FROM radippool
                $whereSql
                ORDER BY pool_name ASC, INET_ATON(framedipaddress) ASC, id ASC
                LIMIT :limit OFFSET :offset";
    $dataStmt = $db->prepare($dataSql);
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Batch resolve user profiles from userinfo
    $userProfiles = [];
    $usernames = array_filter(array_unique(array_column($rows, 'username')));
    if (!empty($usernames) && dbTableExists('userinfo')) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $uStmt = $db->prepare("SELECT username, firstname, lastname, department FROM userinfo WHERE username IN ($placeholders)");
        $uStmt->execute(array_values($usernames));
        while ($p = $uStmt->fetch(PDO::FETCH_ASSOC)) {
            $userProfiles[$p['username']] = $p;
        }
    }
}

// Registered NAS list for selection in modal
$nasList = dbTableExists('nas') ? dbFetchAll("SELECT nasname, shortname FROM nas ORDER BY shortname ASC") : [];

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-diagram-3 me-2 text-primary"></i>IP Pool Management</h4>
        <p>Manage dynamic FreeRADIUS IP pools, active lease assignments, and manual release</p>
    </div>
    <?php if ($tableExists): ?>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addIpModal">
            <i class="bi bi-plus-lg me-1"></i>Add IPs to Pool
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= $flash['msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$tableExists): ?>
<div class="card shadow-sm border-warning">
    <div class="card-body py-4">
        <div class="d-flex align-items-start gap-3">
            <div class="text-warning fs-1"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <h5 class="fw-bold text-dark">IP Pool Table (<code>radippool</code>) Not Installed</h5>
                <p class="text-muted mb-3">
                    The <code>radippool</code> table is required for FreeRADIUS SQL IP Pool (<code>sqlippool</code>) allocations. It is currently missing from the database.
                </p>
                <div class="bg-dark text-light p-3 rounded small font-monospace mb-3">
                    CREATE TABLE IF NOT EXISTS `radippool` (<br>
                    &nbsp;&nbsp;`id` int(11) unsigned NOT NULL AUTO_INCREMENT,<br>
                    &nbsp;&nbsp;`pool_name` varchar(30) NOT NULL,<br>
                    &nbsp;&nbsp;`framedipaddress` varchar(15) NOT NULL DEFAULT '',<br>
                    &nbsp;&nbsp;`nasipaddress` varchar(15) NOT NULL DEFAULT '',<br>
                    &nbsp;&nbsp;`calledstationid` varchar(30) NOT NULL DEFAULT '',<br>
                    &nbsp;&nbsp;`callingstationid` varchar(30) NOT NULL DEFAULT '',<br>
                    &nbsp;&nbsp;`expiry_time` datetime NOT NULL DEFAULT current_timestamp(),<br>
                    &nbsp;&nbsp;`username` varchar(64) NOT NULL DEFAULT '',<br>
                    &nbsp;&nbsp;`pool_key` varchar(30) NOT NULL DEFAULT '',<br>
                    &nbsp;&nbsp;PRIMARY KEY (`id`),<br>
                    &nbsp;&nbsp;KEY `radippool_poolname_expire` (`pool_name`,`expiry_time`),<br>
                    &nbsp;&nbsp;KEY `framedipaddress` (`framedipaddress`),<br>
                    &nbsp;&nbsp;KEY `radippool_nasip_poolkey_ipaddress` (`nasipaddress`,`pool_key`,`framedipaddress`)<br>
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Stat Cards (Interactive Auto-Filters & Navigation) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="ippool.php<?= !empty($filterPool) ? '?pool=' . urlencode($filterPool) : '' ?>"
           class="stat-card text-decoration-none <?= empty($filterStatus) ? 'border-primary shadow-sm' : '' ?>"
           title="Click to show all IP addresses in this pool">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-hdd-stack text-primary"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value"><?= number_format($totalIps) ?></div>
                <div class="stat-label d-flex align-items-center justify-content-between">
                    <span>Total Pool IPs</span>
                    <i class="bi bi-chevron-right small text-muted"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card position-relative <?= $filterStatus === 'active' ? 'border-success shadow-sm' : '' ?>"
             style="cursor: pointer;"
             onclick="location.href='ippool.php?status=active<?= !empty($filterPool) ? '&pool=' . urlencode($filterPool) : '' ?>'"
             title="Click to filter Active Leases">
            <div class="stat-icon" style="background:#f0fdf4">
                <i class="bi bi-broadcast text-success"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="stat-value"><?= number_format($activeLeases) ?></div>
                    <a href="sessions.php" class="badge bg-white text-success border border-success-subtle text-decoration-none px-2 py-1 small"
                       onclick="event.stopPropagation();" title="View live active sessions in FreeRADIUS">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Sessions
                    </a>
                </div>
                <div class="stat-label d-flex align-items-center justify-content-between">
                    <span>Active Leases</span>
                    <span class="badge bg-success-subtle text-success py-0 px-1 font-monospace" style="font-size:.65rem">Filter</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <a href="ippool.php?status=available<?= !empty($filterPool) ? '&pool=' . urlencode($filterPool) : '' ?>"
           class="stat-card text-decoration-none <?= $filterStatus === 'available' ? 'border-secondary shadow-sm' : '' ?>"
           title="Click to filter Available / Free IPs">
            <div class="stat-icon" style="background:#f8fafc">
                <i class="bi bi-check2-circle text-secondary"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value"><?= number_format($availableIps) ?></div>
                <div class="stat-label d-flex align-items-center justify-content-between">
                    <span>Available / Free</span>
                    <span class="badge bg-secondary-subtle text-secondary py-0 px-1 font-monospace" style="font-size:.65rem">Filter</span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card position-relative"
             style="cursor: pointer;"
             onclick="focusPoolFilter()"
             title="Click to focus Pool filter selector">
            <div class="stat-icon" style="background:#faf5ff">
                <i class="bi bi-diagram-2 text-purple" style="color:#9333ea"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="stat-value"><?= number_format($totalPoolCount) ?></div>
                    <button type="button" class="badge bg-white border px-2 py-1 small btn p-0 text-decoration-none"
                            style="color:#9333ea;border-color:#e9d5ff!important"
                            data-bs-toggle="modal" data-bs-target="#addIpModal"
                            onclick="event.stopPropagation();" title="Add New Pool or IPs">
                        <i class="bi bi-plus-circle me-1"></i>+ Add
                    </button>
                </div>
                <div class="stat-label d-flex align-items-center justify-content-between">
                    <span>Configured Pools</span>
                    <i class="bi bi-filter-circle small text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search IP or username..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="pool" id="poolSelect" class="form-select form-select-sm">
                    <option value="">All IP Pools</option>
                    <?php foreach ($pools as $p): ?>
                    <option value="<?= sanitize($p) ?>" <?= $filterPool === $p ? 'selected' : '' ?>><?= sanitize($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active (Leased)</option>
                    <option value="available" <?= $filterStatus === 'available' ? 'selected' : '' ?>>Available (Free)</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if (!empty($search) || !empty($filterPool) || !empty($filterStatus)): ?>
                <a href="ippool.php" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- IP Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>IP Address</th>
                        <th>Pool Name</th>
                        <th>Assigned User</th>
                        <th>Status</th>
                        <th>NAS IP</th>
                        <th>Calling Station (MAC)</th>
                        <th>Lease Expiry</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-diagram-3 fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No IP address records found matching your filters.
                            <?php if ($totalIps === 0): ?>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addIpModal">
                                    <i class="bi bi-plus-lg me-1"></i>Provision your first IP Pool
                                </button>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $r):
                        $isActive = (!empty($r['username']) && strtotime($r['expiry_time']) > time());
                        $expiryTime = strtotime($r['expiry_time']);
                        $now = time();
                        $diffSec = $expiryTime - $now;
                        $uName = $r['username'] ?? '';
                        $prof = $userProfiles[$uName] ?? null;
                    ?>
                    <tr>
                        <td>
                            <code class="fw-bold fs-6 text-dark"><?= sanitize($r['framedipaddress']) ?></code>
                        </td>
                        <td>
                            <a href="ippool.php?pool=<?= urlencode($r['pool_name']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none" title="Filter by pool <?= sanitize($r['pool_name']) ?>">
                                <?= sanitize($r['pool_name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if (!empty($uName)): ?>
                                <a href="user-edit.php?username=<?= urlencode($uName) ?>" class="text-decoration-none fw-semibold text-primary">
                                    <?= sanitize($uName) ?>
                                </a>
                                <?php if ($prof && (!empty($prof['firstname']) || !empty($prof['department']))): ?>
                                <div class="text-muted small">
                                    <?= sanitize(trim(($prof['firstname'] ?? '') . ' ' . ($prof['lastname'] ?? ''))) ?>
                                    <?= !empty($prof['department']) ? ' · ' . sanitize($prof['department']) : '' ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small italic">— None (Free) —</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <a href="ippool.php?status=active<?= !empty($filterPool) ? '&pool=' . urlencode($filterPool) : '' ?>" class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 text-decoration-none" title="Filter Active Leases">
                                    <span class="spinner-grow spinner-grow-sm" style="width:0.45rem;height:0.45rem" role="status"></span> Active
                                </a>
                            <?php else: ?>
                                <a href="ippool.php?status=available<?= !empty($filterPool) ? '&pool=' . urlencode($filterPool) : '' ?>" class="badge bg-light text-secondary border text-decoration-none" title="Filter Available IPs">
                                    Available
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['nasipaddress'])): ?>
                                <a href="nas.php" class="small font-monospace text-secondary text-decoration-none" title="View in NAS Devices">
                                    <?= sanitize($r['nasipaddress']) ?> <i class="bi bi-box-arrow-up-right" style="font-size:.65rem"></i>
                                </a>
                            <?php else: ?>
                                <span class="small font-monospace text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="small font-monospace text-secondary"><?= sanitize($r['callingstationid'] ?: '—') ?></span>
                        </td>
                        <td>
                            <span class="small text-dark d-block"><?= sanitize($r['expiry_time']) ?></span>
                            <?php if ($isActive): ?>
                                <?php
                                    $minLeft = max(1, round($diffSec / 60));
                                    $timeBadge = ($minLeft > 60) ? round($minLeft / 60, 1) . 'h left' : $minLeft . 'm left';
                                ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle small" style="font-size:.65rem">
                                    <i class="bi bi-clock me-1"></i><?= $timeBadge ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small" style="font-size:.7rem">Expired / Idle</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <?php if ($isActive): ?>
                                <button type="button" class="btn btn-outline-warning py-0 px-2"
                                    onclick="confirmRelease(<?= (int)$r['id'] ?>, '<?= htmlspecialchars(addslashes($r['framedipaddress']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($uName), ENT_QUOTES) ?>')"
                                    title="Manually Release IP Lease">
                                    <i class="bi bi-arrow-return-left me-1"></i>Release
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-danger py-0 px-2"
                                    onclick="confirmDelete(<?= (int)$r['id'] ?>, '<?= htmlspecialchars(addslashes($r['framedipaddress']), ENT_QUOTES) ?>')"
                                    title="Delete IP from Pool">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-top d-flex flex-wrap align-items-center justify-content-between py-3">
        <div class="text-muted small">
            Showing <?= number_format($pagination['offset'] + 1) ?> to <?= number_format(min($pagination['offset'] + $pagination['per_page'], $pagination['total'])) ?> of <?= number_format($pagination['total']) ?> IPs
        </div>
        <div>
            <?php
            $pageParams = array_filter(['pool' => $filterPool, 'status' => $filterStatus, 'q' => $search]);
            $pageBaseUrl = 'ippool.php' . (!empty($pageParams) ? '?' . http_build_query($pageParams) : '');
            ?>
            <?= paginationLinks($pagination, $pageBaseUrl) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add IP Modal -->
<div class="modal fade" id="addIpModal" tabindex="-1" aria-labelledby="addIpModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ippool.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addIpModalLabel"><i class="bi bi-plus-circle me-2 text-primary"></i>Add IPs to Pool</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">IP Pool Name <span class="text-danger">*</span></label>
                        <select name="pool_name" id="modalPoolSelect" class="form-select form-select-sm mb-1" onchange="toggleNewPoolInput(this.value)">
                            <?php foreach ($pools as $p): ?>
                            <option value="<?= sanitize($p) ?>"><?= sanitize($p) ?></option>
                            <?php endforeach; ?>
                            <option value="__new__">+ Create New Pool...</option>
                        </select>
                        <input type="text" name="new_pool_name" id="newPoolInput" class="form-control form-control-sm mt-2 d-none" placeholder="Enter new pool name (e.g. guest_pool)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Mode</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ip_mode" id="modeSingle" value="single" checked onchange="toggleIpMode('single')">
                                <label class="form-check-label small" for="modeSingle">Single IP</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ip_mode" id="modeRange" value="range" onchange="toggleIpMode('range')">
                                <label class="form-check-label small" for="modeRange">IP Range (Batch)</label>
                            </div>
                        </div>
                    </div>

                    <!-- Single IP field -->
                    <div id="singleIpField" class="mb-3">
                        <label class="form-label fw-semibold small">IP Address <span class="text-danger">*</span></label>
                        <input type="text" name="single_ip" class="form-control form-control-sm font-monospace" placeholder="e.g. 192.168.10.50">
                    </div>

                    <!-- Range fields -->
                    <div id="rangeIpFields" class="mb-3 d-none">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Start IP <span class="text-danger">*</span></label>
                                <input type="text" name="range_start" class="form-control form-control-sm font-monospace" placeholder="e.g. 192.168.10.100">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">End IP <span class="text-danger">*</span></label>
                                <input type="text" name="range_end" class="form-control form-control-sm font-monospace" placeholder="e.g. 192.168.10.150">
                            </div>
                        </div>
                        <div class="text-muted small mt-1" style="font-size:.75rem">Batch limit: maximum 512 IPs per submission.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Associated NAS (Optional)</label>
                        <select name="nasipaddress" class="form-select form-select-sm">
                            <option value="">None / Any NAS</option>
                            <?php foreach ($nasList as $nasItem): ?>
                            <option value="<?= sanitize($nasItem['nasname']) ?>">
                                <?= sanitize($nasItem['shortname']) ?> (<?= sanitize($nasItem['nasname']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Add to Pool</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Action Forms -->
<form id="actionForm" method="POST" action="ippool.php">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="actionType" value="">
    <input type="hidden" name="id" id="actionId" value="">
    <input type="hidden" name="return_query" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">
</form>

<?php endif; ?>

<?php
$extra_js = '<script>
function toggleNewPoolInput(val) {
    const input = document.getElementById("newPoolInput");
    if (val === "__new__") {
        input.classList.remove("d-none");
        input.focus();
    } else {
        input.classList.add("d-none");
    }
}
function toggleIpMode(mode) {
    if (mode === "range") {
        document.getElementById("singleIpField").classList.add("d-none");
        document.getElementById("rangeIpFields").classList.remove("d-none");
    } else {
        document.getElementById("singleIpField").classList.remove("d-none");
        document.getElementById("rangeIpFields").classList.add("d-none");
    }
}
function confirmRelease(id, ip, user) {
    if (confirm("Release IP lease: " + ip + " currently assigned to " + user + "?\nThe IP will immediately become available for new connections.")) {
        document.getElementById("actionType").value = "release";
        document.getElementById("actionId").value = id;
        document.getElementById("actionForm").submit();
    }
}
function confirmDelete(id, ip) {
    if (confirm("Permanently delete IP " + ip + " from this pool?")) {
        document.getElementById("actionType").value = "delete";
        document.getElementById("actionId").value = id;
        document.getElementById("actionForm").submit();
    }
}
function focusPoolFilter() {
    const el = document.getElementById("poolSelect");
    if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.focus();
        el.classList.add("border-primary", "shadow-sm");
        setTimeout(() => el.classList.remove("border-primary", "shadow-sm"), 1500);
    }
}
</script>';
include __DIR__ . '/includes/footer.php';

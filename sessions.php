<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Active Sessions';
$db = getDB();

$search   = trim($_GET['q'] ?? '');
$ipSearch = trim($_GET['ip'] ?? '');
$where    = "WHERE acctstoptime IS NULL";
$params   = [];

if ($search) {
    $where .= " AND (username LIKE :q1 OR callingstationid LIKE :q2)";
    $qVal = "%$search%";
    $params[':q1'] = $qVal;
    $params[':q2'] = $qVal;
}

if ($ipSearch) {
    $where .= " AND framedipaddress LIKE :ip";
    $params[':ip'] = "%$ipSearch%";
}

// Total count
$totalStmt = $db->prepare("SELECT COUNT(*) FROM radacct $where");
$totalStmt->execute($params);
$totalActive = (int)$totalStmt->fetchColumn();

// Pagination
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));
$pag     = paginate($totalActive, $page, $perPage);
$lim     = (int)$pag['per_page'];
$off     = (int)$pag['offset'];

$sessions = $db->prepare("SELECT username, nasipaddress, framedipaddress,
    acctstarttime, acctinputoctets, acctoutputoctets,
    callingstationid, calledstationid, acctsessionid
  FROM radacct $where ORDER BY acctstarttime DESC LIMIT $lim OFFSET $off");
$sessions->execute($params);
$records = $sessions->fetchAll();

// Disconnect a session (sends a Disconnect-Request via CoA / radclient)
$disconnectMsg = '';
$disconnectType = 'info';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['disconnect'])) {
    verifyCsrf();
    $sid      = sanitize($_POST['session_id'] ?? '');
    $user     = sanitize($_POST['username'] ?? '');
    $nasip    = sanitize($_POST['nasip'] ?? '');

    $nas = $db->prepare("SELECT secret FROM nas WHERE nasname = ? LIMIT 1");
    $nas->execute([$nasip]);
    $secret = $nas->fetchColumn() ?: 'secret';

    $disconnectMsg = "Disconnect command generated for user <strong>$user</strong> on NAS <strong>$nasip</strong>:<br>"
                   . "<code>echo \"User-Name=$user,Acct-Session-Id=$sid\" | radclient -x $nasip:3799 disconnect " . htmlspecialchars($secret) . "</code>";
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-activity me-2 text-success"></i>Active Sessions</h4>
        <p><?= number_format($totalActive) ?> users currently online</p>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </button>
</div>

<?php if ($disconnectMsg): ?>
<div class="alert alert-info alert-dismissible fade show">
    <?= $disconnectMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search username or MAC address..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="ip" class="form-control form-control-sm"
                       placeholder="Filter by Framed IP (e.g. 172.16...)" value="<?= htmlspecialchars($ipSearch) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm px-3">Search</button>
                <?php if ($search || $ipSearch): ?>
                <a href="sessions.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr>
                <th>Username</th>
                <th>NAS IP</th>
                <th>Client IP</th>
                <th>MAC / Station ID</th>
                <th>Session Start</th>
                <th>Upload</th>
                <th>Download</th>
                <th>Action</th>
            </tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-wifi-off fs-3 d-block mb-2"></i>No active sessions right now
            </td></tr>
            <?php else: foreach ($records as $r): ?>
            <tr>
                <td>
                    <i class="bi bi-circle-fill text-success me-1" style="font-size:.45rem"></i>
                    <strong><?= sanitize($r['username']) ?></strong>
                </td>
                <td class="text-muted small"><?= sanitize($r['nasipaddress']) ?></td>
                <td><code style="font-size:.78rem"><?= sanitize($r['framedipaddress']) ?></code></td>
                <td class="text-muted small"><?= sanitize($r['callingstationid'] ?: '—') ?></td>
                <td class="text-muted small">
                    <?= date('d/m/y H:i:s', strtotime($r['acctstarttime'])) ?>
                </td>
                <td><?= formatBytes($r['acctinputoctets'] ?? 0) ?></td>
                <td><?= formatBytes($r['acctoutputoctets'] ?? 0) ?></td>
                <td>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($r['acctsessionid']) ?>">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($r['username']) ?>">
                        <input type="hidden" name="nasip" value="<?= htmlspecialchars($r['nasipaddress']) ?>">
                        <button type="submit" name="disconnect" value="1"
                                class="btn btn-sm btn-outline-danger py-0 px-2"
                                onclick="return confirm('Disconnect user <?= htmlspecialchars(addslashes($r['username']), ENT_QUOTES) ?>?')">
                            <i class="bi bi-plug me-1"></i>Kick
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <?php if ($pag['total_pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <span class="text-muted small">Showing <?= $off + 1 ?>–<?= min($totalActive, $off + $lim) ?> of <?= number_format($totalActive) ?> active sessions</span>
            <?php
            $pageParams = array_filter(['q' => $search, 'ip' => $ipSearch]);
            $pageBaseUrl = 'sessions.php' . (!empty($pageParams) ? '?' . http_build_query($pageParams) : '');
            ?>
            <?= paginationLinks($pag, $pageBaseUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$extra_js = '<script>
// Auto-refresh every 30 seconds
setTimeout(() => location.reload(), 30000);
</script>';
include __DIR__ . '/includes/footer.php';

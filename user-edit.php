<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Edit User';
$db = getDB();

$username = trim($_GET['username'] ?? $_POST['username'] ?? '');
if (!$username) {
    header('Location: users.php');
    exit;
}

$errors = [];
$groups = $db->query("SELECT DISTINCT groupname FROM radusergroup UNION SELECT DISTINCT groupname FROM radgroupcheck ORDER BY groupname")->fetchAll(PDO::FETCH_COLUMN);
$hasUserinfo = dbTableExists('userinfo');

// Load existing attributes from radcheck
$attrs = $db->prepare("SELECT attribute, value FROM radcheck WHERE username=?");
$attrs->execute([$username]);
$attrMap = [];
foreach ($attrs->fetchAll() as $a) {
    $attrMap[$a['attribute']] = $a['value'];
}
$isDisabled = isset($attrMap['Auth-Type']) && $attrMap['Auth-Type'] === 'Reject';

// Load static IP from radreply if assigned
$staticIpStmt = $db->prepare("SELECT value FROM radreply WHERE username=? AND attribute='Framed-IP-Address' LIMIT 1");
$staticIpStmt->execute([$username]);
$currentStaticIp = $staticIpStmt->fetchColumn() ?: '';

// Current group
$userGroup = $db->prepare("SELECT groupname FROM radusergroup WHERE username=? ORDER BY priority ASC LIMIT 1");
$userGroup->execute([$username]);
$currentGroup = $userGroup->fetchColumn();

// Load userinfo if exists
$userInfo = null;
if ($hasUserinfo) {
    $uStmt = $db->prepare("SELECT firstname, lastname, department, email FROM userinfo WHERE username=? LIMIT 1");
    $uStmt->execute([$username]);
    $userInfo = $uStmt->fetch() ?: [];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();

    $newPw      = trim($_POST['password'] ?? '');
    $newGroup   = trim($_POST['group'] ?? '');
    $simul      = (int)($_POST['simul_count'] ?? 0);
    $expiry     = trim($_POST['expiry'] ?? '');
    $static_ip  = trim($_POST['static_ip'] ?? '');
    $fullname   = trim($_POST['fullname'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email      = trim($_POST['email'] ?? '');

    if ($static_ip && !filter_var($static_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $errors[] = 'Invalid IPv4 address for Static IP.';
    }

    if (!$errors) {
        $db->beginTransaction();
        try {
            // Update password
            if ($newPw) {
                $exists = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username=? AND attribute IN ('Cleartext-Password', 'User-Password')");
                $exists->execute([$username]);
                if ($exists->fetchColumn() > 0) {
                    $db->prepare("UPDATE radcheck SET value=?, attribute='Cleartext-Password' WHERE username=? AND attribute IN ('Cleartext-Password', 'User-Password')")
                       ->execute([$newPw, $username]);
                } else {
                    $db->prepare("INSERT INTO radcheck (username,attribute,op,value) VALUES (?,?,?,?)")
                       ->execute([$username, 'Cleartext-Password', ':=', $newPw]);
                }
                $attrMap['Cleartext-Password'] = $newPw;
            }

            // Simultaneous-Use
            $db->prepare("DELETE FROM radcheck WHERE username=? AND attribute='Simultaneous-Use'")->execute([$username]);
            if ($simul > 0) {
                $db->prepare("INSERT INTO radcheck (username,attribute,op,value) VALUES (?,?,?,?)")
                   ->execute([$username, 'Simultaneous-Use', ':=', (string)$simul]);
                $attrMap['Simultaneous-Use'] = (string)$simul;
            } else {
                unset($attrMap['Simultaneous-Use']);
            }

            // Expiry
            $db->prepare("DELETE FROM radcheck WHERE username=? AND attribute='Expiration'")->execute([$username]);
            if ($expiry) {
                $expFormatted = date('d M Y', strtotime($expiry));
                $db->prepare("INSERT INTO radcheck (username,attribute,op,value) VALUES (?,?,?,?)")
                   ->execute([$username, 'Expiration', ':=', $expFormatted]);
                $attrMap['Expiration'] = $expFormatted;
            } else {
                unset($attrMap['Expiration']);
            }

            // Static IP (radreply Framed-IP-Address)
            $db->prepare("DELETE FROM radreply WHERE username=? AND attribute='Framed-IP-Address'")->execute([$username]);
            if ($static_ip) {
                $db->prepare("INSERT INTO radreply (username,attribute,op,value) VALUES (?, 'Framed-IP-Address', ':=', ?)")
                   ->execute([$username, $static_ip]);
                $currentStaticIp = $static_ip;
            } else {
                $currentStaticIp = '';
            }

            // Group
            $db->prepare("DELETE FROM radusergroup WHERE username=?")->execute([$username]);
            if ($newGroup) {
                $db->prepare("INSERT INTO radusergroup (username,groupname,priority) VALUES (?,?,0)")
                   ->execute([$username, $newGroup]);
                $currentGroup = $newGroup;
            } else {
                $currentGroup = '';
            }

            // userinfo update
            if ($hasUserinfo) {
                $checkUi = $db->prepare("SELECT COUNT(*) FROM userinfo WHERE username=?");
                $checkUi->execute([$username]);
                if ($checkUi->fetchColumn() > 0) {
                    $db->prepare("UPDATE userinfo SET firstname=?, lastname=?, department=?, email=?, updatedate=NOW(), updateby=? WHERE username=?")
                       ->execute([$fullname, $department, $department, $email, $_SESSION['admin_user'] ?? 'admin', $username]);
                } elseif ($fullname || $department || $email) {
                    $db->prepare("INSERT INTO userinfo (username, firstname, lastname, email, department, creationdate, creationby) VALUES (?,?,?,?,?,NOW(),?)")
                       ->execute([$username, $fullname, $department, $email, $department, $_SESSION['admin_user'] ?? 'admin']);
                }
                $userInfo = ['firstname' => $fullname, 'lastname' => $department, 'department' => $department, 'email' => $email];
            }

            $db->commit();

            $changes = [];
            if ($newPw) $changes[] = 'Password updated';
            if ($newGroup !== ($userGroup->fetchColumn() ?: '')) $changes[] = "Group: " . ($newGroup ?: 'None');
            if ($static_ip) $changes[] = "Static IP: $static_ip";
            auditLog('user.update', $username, implode(', ', $changes) ?: 'Settings updated');

            flash("User '$username' updated successfully.", 'success');
            header('Location: users.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Session history for this user
$history = $db->prepare("SELECT acctstarttime, acctstoptime, nasipaddress, framedipaddress,
    acctinputoctets, acctoutputoctets, acctsessiontime
  FROM radacct WHERE username=? ORDER BY acctstarttime DESC LIMIT 5");
$history->execute([$username]);
$sessions = $history->fetchAll();

// 30-day bandwidth query for this user
$latestUserAcct = $db->prepare("SELECT DATE(acctstarttime) FROM radacct WHERE username=? ORDER BY acctstarttime DESC LIMIT 1");
$latestUserAcct->execute([$username]);
$latestDateVal = $latestUserAcct->fetchColumn();

$chartLabels = [];
$chartUploads = [];
$chartDownloads = [];
$total30dBytes = 0;
$bwRows = [];

if ($latestDateVal) {
    // Take 30 days up to the latest activity date (or today if activity is current)
    $anchorDate = (strtotime($latestDateVal) > strtotime('-30 days')) ? date('Y-m-d') : $latestDateVal;
    $startDateVal = date('Y-m-d 00:00:00', strtotime('-29 days', strtotime($anchorDate)));

    $bwStmt = $db->prepare("SELECT DATE(acctstarttime) AS d,
            COALESCE(SUM(acctinputoctets), 0) AS upload,
            COALESCE(SUM(acctoutputoctets), 0) AS download
        FROM radacct
        WHERE username = ? AND acctstarttime >= ? AND acctstarttime <= ?
        GROUP BY DATE(acctstarttime)
        ORDER BY d ASC");
    $bwStmt->execute([$username, $startDateVal, "$anchorDate 23:59:59"]);
    $bwRows = $bwStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map by date for zero-filling all 30 days
    $bwByDate = [];
    foreach ($bwRows as $r) {
        $bwByDate[$r['d']] = [
            'upload'   => (float)$r['upload'],
            'download' => (float)$r['download']
        ];
        $total30dBytes += (float)$r['upload'] + (float)$r['download'];
    }

    // Fill all 30 continuous calendar days
    $cur = strtotime($startDateVal);
    $end = strtotime($anchorDate);
    while ($cur <= $end) {
        $dStr = date('Y-m-d', $cur);
        $chartLabels[] = date('d M', $cur);
        $chartUploads[] = round(($bwByDate[$dStr]['upload'] ?? 0) / (1024 * 1024), 2); // MB
        $chartDownloads[] = round(($bwByDate[$dStr]['download'] ?? 0) / (1024 * 1024), 2); // MB
        $cur = strtotime('+1 day', $cur);
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h4 class="mb-0"><i class="bi bi-pencil me-2 text-primary"></i>Edit User</h4>
            <?php if ($isDisabled): ?>
            <span class="badge bg-danger rounded-pill"><i class="bi bi-slash-circle me-1"></i>Disabled</span>
            <?php else: ?>
            <span class="badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i>Active</span>
            <?php endif; ?>
        </div>
        <p class="mb-0 mt-1">Editing: <strong><?= sanitize($username) ?></strong>
           <?= !empty($userInfo['firstname']) ? ' &mdash; ' . sanitize($userInfo['firstname']) : '' ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="POST" action="user-toggle.php" class="d-inline mb-0">
            <?= csrfField() ?>
            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
            <input type="hidden" name="state" value="<?= $isDisabled ? 'enable' : 'disable' ?>">
            <input type="hidden" name="return_url" value="user-edit.php?username=<?= urlencode($username) ?>">
            <?php if ($isDisabled): ?>
            <button type="submit" class="btn btn-outline-success btn-sm">
                <i class="bi bi-check-circle me-1"></i>Enable Account
            </button>
            <?php else: ?>
            <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('Disable user <?= htmlspecialchars($username) ?>? RADIUS will reject all authentication attempts.')">
                <i class="bi bi-slash-circle me-1"></i>Disable Account
            </button>
            <?php endif; ?>
        </form>
        <a href="users.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
<?php endif; ?>

<div class="row">
<div class="col-lg-7">
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold py-3">Account Settings</div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Username</label>
                    <input type="text" class="form-control bg-light" value="<?= sanitize($username) ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                    <div class="input-group">
                        <input type="text" name="password" class="form-control" id="pwField"
                                placeholder="Enter new password">
                        <button type="button" class="btn btn-outline-secondary" onclick="generatePw()" title="Generate password">
                            <i class="bi bi-shuffle"></i>
                        </button>
                    </div>
                    <?php $curPw = $attrMap['Cleartext-Password'] ?? $attrMap['User-Password'] ?? null; ?>
                    <div class="form-text">Current: <code><?= htmlspecialchars($curPw ?? 'Encrypted / Not set') ?></code></div>
                </div>
            </div>

            <?php if ($hasUserinfo): ?>
            <div class="mb-3 border-top pt-3">
                <span class="fw-semibold small text-muted text-uppercase mb-2 d-block">Profile Info</span>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label small">Full Name</label>
                        <input type="text" name="fullname" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($userInfo['firstname'] ?? '') ?>" placeholder="e.g. John Doe">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label small">Department / Class</label>
                        <input type="text" name="department" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($userInfo['department'] ?? $userInfo['lastname'] ?? '') ?>" placeholder="e.g. Teknik Mesin / BPU">
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label small">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>" placeholder="e.g. user@example.com">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="border-top pt-3">
                <span class="fw-semibold small text-muted text-uppercase mb-2 d-block">RADIUS Policies</span>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Group</label>
                        <select name="group" class="form-select">
                            <option value="">— No Group —</option>
                            <?php foreach ($groups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= ($currentGroup === $g) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Max Simultaneous Logins</label>
                        <input type="number" name="simul_count" class="form-control"
                               min="0" value="<?= (int)($attrMap['Simultaneous-Use'] ?? 0) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Expiry Date</label>
                        <input type="date" name="expiry" class="form-control"
                               value="<?= isset($attrMap['Expiration']) ? date('Y-m-d', strtotime($attrMap['Expiration'])) : '' ?>">
                        <div class="form-text">Leave blank for no expiry.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small">Static IP Address <span class="text-muted">(Framed-IP-Address)</span></label>
                        <input type="text" name="static_ip" class="form-control"
                               value="<?= htmlspecialchars($currentStaticIp) ?>"
                               placeholder="e.g. 192.168.10.50">
                        <div class="form-text">Leave blank to use dynamic pool IP.</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
            <a href="users.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>
</div>

<div class="col-lg-5">
    <!-- 30-Day Bandwidth Graph Card -->
    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold py-3 d-flex align-items-center justify-content-between">
            <div><i class="bi bi-graph-up me-1 text-primary"></i>Bandwidth (Past 30 Days)</div>
            <?php if ($total30dBytes > 0): ?>
            <span class="badge bg-light text-primary border"><?= formatBytes($total30dBytes) ?> Total</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($bwRows)): ?>
            <div style="height: 180px;">
                <canvas id="userBwChart"></canvas>
            </div>
            <div class="d-flex justify-content-center gap-3 mt-2 small text-muted">
                <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#2563eb;"></span> Upload</span>
                <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#10b981;"></span> Download</span>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-reception-0 fs-3 d-block mb-1 text-secondary opacity-50"></i>
                No traffic recorded in the past 30 days
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sessions Card -->
    <div class="card">
        <div class="card-header bg-white fw-semibold py-3">
            <i class="bi bi-clock-history me-1"></i>Recent Sessions
        </div>
        <div class="card-body p-0">
            <table class="table small mb-0">
                <thead><tr><th>Start</th><th>NAS IP</th><th>Duration</th><th>Traffic</th></tr></thead>
                <tbody>
                <?php if (empty($sessions)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No sessions found</td></tr>
                <?php else: foreach ($sessions as $s): ?>
                <tr>
                    <td><?= date('d/m H:i', strtotime($s['acctstarttime'])) ?></td>
                    <td><?= sanitize($s['nasipaddress']) ?></td>
                    <td><?= $s['acctsessiontime'] ? formatDuration($s['acctsessiontime']) : ($s['acctstoptime'] ? '—' : '<span class="badge badge-online">Online</span>') ?></td>
                    <td><?= formatBytes((float)($s['acctinputoctets'] ?? 0) + (float)($s['acctoutputoctets'] ?? 0)) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php
$extra_js = '<script>
function generatePw() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$";
    let pw = "";
    for (let i = 0; i < 10; i++) pw += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById("pwField").value = pw;
}
' . (!empty($bwRows) ? '
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById("userBwChart");
    if (ctx) {
        new Chart(ctx, {
            type: "line",
            data: {
                labels: ' . json_encode($chartLabels) . ',
                datasets: [
                    {
                        label: "Download (MB)",
                        data: ' . json_encode($chartDownloads) . ',
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16, 185, 129, 0.08)",
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: "Upload (MB)",
                        data: ' . json_encode($chartUploads) . ',
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37, 99, 235, 0.08)",
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: "index",
                        intersect: false,
                        callbacks: {
                            label: function(c) {
                                return " " + c.dataset.label.replace(" (MB)", "") + ": " + c.parsed.y.toLocaleString() + " MB";
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 7, font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: "#f1f5f9" },
                        ticks: {
                            font: { size: 10 },
                            callback: function(v) { return v >= 1024 ? (v / 1024).toFixed(1) + " GB" : v + " MB"; }
                        }
                    }
                }
            }
        });
    }
});
' : '') . '
</script>';
include __DIR__ . '/includes/footer.php';

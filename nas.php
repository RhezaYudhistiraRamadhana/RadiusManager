<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('superadmin');
$page_title = 'NAS Devices';
$db = getDB();

$flash = getFlash();

// Force refresh status if requested
if (isset($_GET['refresh_status'])) {
    unset($_SESSION['nas_status_cache']);
}

$nas = $db->query("SELECT id, nasname, shortname, type, ports, secret, description FROM nas ORDER BY shortname, nasname")->fetchAll();

// Probing and Session Caching (60 seconds)
if (!isset($_SESSION['nas_status_cache']) || !is_array($_SESSION['nas_status_cache'])) {
    $_SESSION['nas_status_cache'] = [];
}

$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$nasStatuses = [];

foreach ($nas as $n) {
    $host = trim($n['nasname'] ?? '');
    
    // Subnet / Wildcard check (e.g. 0.0.0.0/0, /24, etc.)
    if (str_contains($host, '/') || $host === '0.0.0.0') {
        $nasStatuses[$n['id']] = [
            'type' => 'wildcard',
            'status' => 'wildcard',
            'label' => 'Subnet / Any',
            'latency' => 0
        ];
        continue;
    }

    // Check 60-second cache
    $cached = $_SESSION['nas_status_cache'][$host] ?? null;
    if ($cached && isset($cached['time']) && (time() - $cached['time'] < 60)) {
        $nasStatuses[$n['id']] = $cached;
        continue;
    }

    // Probe host
    $t0 = microtime(true);
    $cmd = $isWin ? "ping -n 1 -w 350 " . escapeshellarg($host) : "ping -c 1 -W 1 " . escapeshellarg($host);
    @exec($cmd, $pingOutput, $pingRet);
    $t1 = microtime(true);
    $latency = round(($t1 - $t0) * 1000, 1);

    if ($pingRet === 0) {
        $st = [
            'type' => 'host',
            'status' => 'online',
            'label' => 'Online',
            'latency' => $latency,
            'time' => time()
        ];
    } else {
        // Fallback socket check on port 1812
        $fp = @fsockopen($host, 1812, $errno, $errstr, 0.3);
        if ($fp) {
            fclose($fp);
            $st = [
                'type' => 'host',
                'status' => 'online',
                'label' => 'Online (Port 1812)',
                'latency' => $latency,
                'time' => time()
            ];
        } else {
            $st = [
                'type' => 'host',
                'status' => 'offline',
                'label' => 'Offline',
                'latency' => $latency,
                'time' => time()
            ];
        }
    }

    $_SESSION['nas_status_cache'][$host] = $st;
    $nasStatuses[$n['id']] = $st;
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-hdd-network me-2 text-primary"></i>NAS Devices</h4>
        <p><?= count($nas) ?> devices registered in FreeRADIUS</p>
    </div>
    <div class="d-flex gap-2">
        <a href="nas.php?refresh_status=1" class="btn btn-outline-secondary btn-sm" title="Re-check status of all NAS devices">
            <i class="bi bi-arrow-clockwise me-1"></i>Check Status
        </a>
        <a href="nas-add.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add NAS
        </a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Status</th>
                <th>Short Name</th>
                <th>IP / Subnet / Host</th>
                <th>Type</th>
                <th>Ports</th>
                <th>Secret</th>
                <th>Description</th>
                <th class="text-end pe-3">Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($nas)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-hdd-network fs-3 d-block mb-2"></i>No NAS devices found
            </td></tr>
            <?php else: foreach ($nas as $n):
                $st = $nasStatuses[$n['id']] ?? ['status' => 'offline', 'label' => 'Unknown', 'latency' => 0];
            ?>
            <tr>
                <td>
                    <?php if ($st['status'] === 'online'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1.5 py-1 px-2" title="Host reachable (<?= $st['latency'] ?>ms)">
                            <span class="spinner-grow spinner-grow-sm" style="width:0.45rem;height:0.45rem" role="status"></span>
                            Online
                        </span>
                        <span class="text-muted ms-1 small" style="font-size:.72rem"><?= $st['latency'] ?>ms</span>
                    <?php elseif ($st['status'] === 'wildcard'): ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1 py-1 px-2" title="Subnet / Wildcard match pattern">
                            <i class="bi bi-diagram-2" style="font-size:.75rem"></i> Subnet
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1 py-1 px-2" title="Unreachable / Timeout">
                            <i class="bi bi-x-circle-fill" style="font-size:.65rem"></i> Offline
                        </span>
                    <?php endif; ?>
                </td>
                <td><strong><?= sanitize($n['shortname']) ?></strong></td>
                <td><code><?= sanitize($n['nasname']) ?></code></td>
                <td><span class="badge bg-light text-dark border"><?= sanitize($n['type'] ?: 'other') ?></span></td>
                <td><?= sanitize($n['ports'] ?: '—') ?></td>
                <td>
                    <span class="text-muted" id="secret_<?= (int)$n['id'] ?>">••••••••</span>
                    <button class="btn btn-link btn-sm p-0 ms-1"
                        onclick="toggleSecret(<?= (int)$n['id'] ?>,'<?= htmlspecialchars(addslashes($n['secret'] ?? ''), ENT_QUOTES) ?>')"
                        title="Show secret">
                        <i class="bi bi-eye text-muted" style="font-size:.8rem"></i>
                    </button>
                </td>
                <td class="text-muted small"><?= sanitize($n['description'] ?: '—') ?></td>
                <td class="text-end pe-3">
                    <div class="btn-group btn-group-sm">
                        <a href="nas-edit.php?id=<?= (int)$n['id'] ?>"
                           class="btn btn-outline-primary py-0 px-2" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button class="btn btn-outline-danger py-0 px-2"
                            onclick="confirmDelete(<?= (int)$n['id'] ?>,'<?= htmlspecialchars(addslashes($n['shortname'] ?? $n['nasname']), ENT_QUOTES) ?>')" title="Delete">
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
</div>

<form id="deleteForm" method="POST" action="nas-delete.php">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<?php
$extra_js = '<script>
function toggleSecret(id, secret) {
    const el = document.getElementById("secret_" + id);
    el.textContent = el.textContent === "••••••••" ? secret : "••••••••";
}
function confirmDelete(id, name) {
    if (confirm("Delete NAS device: " + name + "?\nFreeRADIUS will no longer accept requests from this host.")) {
        document.getElementById("deleteId").value = id;
        document.getElementById("deleteForm").submit();
    }
}
</script>';
include __DIR__ . '/includes/footer.php';

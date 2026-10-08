<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('superadmin');
$page_title = 'NAS Devices';
$db = getDB();

$flash = getFlash();

$nas = $db->query("SELECT id, nasname, shortname, type, ports, secret, description FROM nas ORDER BY shortname, nasname")->fetchAll();

// Fast cached statuses (120 seconds TTL)
$cache = $_SESSION['nas_status_cache'] ?? [];
if (!is_array($cache)) {
    $cache = [];
}

$nasStatuses = [];
$needAsyncProbe = false;

foreach ($nas as $n) {
    $id = (int)$n['id'];
    $host = trim($n['nasname'] ?? '');

    if (str_contains($host, '/') || $host === '0.0.0.0') {
        $nasStatuses[$id] = [
            'type'    => 'wildcard',
            'status'  => 'wildcard',
            'label'   => 'Subnet / Any',
            'latency' => 0
        ];
        continue;
    }

    if (isset($cache[$host]) && isset($cache[$host]['time']) && (time() - $cache[$host]['time'] < 120)) {
        $nasStatuses[$id] = $cache[$host];
    } else {
        $needAsyncProbe = true;
        $nasStatuses[$id] = null;
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-hdd-network me-2 text-primary"></i>NAS Devices</h4>
        <p><?= count($nas) ?> devices registered in FreeRADIUS</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="btnRefreshStatus" class="btn btn-outline-secondary btn-sm" onclick="triggerStatusProbe(true)" title="Re-check status of all NAS devices">
            <i class="bi bi-arrow-clockwise me-1" id="refreshIcon"></i><span id="refreshText">Check Status</span>
        </button>
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
                $nid = (int)$n['id'];
                $st = $nasStatuses[$nid] ?? null;
            ?>
            <tr>
                <td>
                    <?php if ($st && $st['status'] === 'online'): ?>
                        <span id="nas_status_badge_<?= $nid ?>" class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1.5 py-1 px-2" title="Host reachable (<?= $st['latency'] ?>ms)">
                            <span class="spinner-grow spinner-grow-sm" style="width:0.45rem;height:0.45rem" role="status"></span>
                            Online
                        </span>
                        <span id="nas_latency_<?= $nid ?>" class="text-muted ms-1 small" style="font-size:.72rem"><?= $st['latency'] ?>ms</span>
                    <?php elseif ($st && $st['status'] === 'wildcard'): ?>
                        <span id="nas_status_badge_<?= $nid ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1 py-1 px-2" title="Subnet / Wildcard match pattern">
                            <i class="bi bi-diagram-2" style="font-size:.75rem"></i> Subnet
                        </span>
                        <span id="nas_latency_<?= $nid ?>" class="text-muted ms-1 small d-none" style="font-size:.72rem"></span>
                    <?php elseif ($st && $st['status'] === 'offline'): ?>
                        <span id="nas_status_badge_<?= $nid ?>" class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1 py-1 px-2" title="Unreachable / Timeout">
                            <i class="bi bi-x-circle-fill" style="font-size:.65rem"></i> Offline
                        </span>
                        <span id="nas_latency_<?= $nid ?>" class="text-muted ms-1 small d-none" style="font-size:.72rem"></span>
                    <?php else: ?>
                        <span id="nas_status_badge_<?= $nid ?>" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1.5 py-1 px-2">
                            <span class="spinner-border spinner-border-sm" style="width:0.5rem;height:0.5rem" role="status"></span>
                            Checking...
                        </span>
                        <span id="nas_latency_<?= $nid ?>" class="text-muted ms-1 small d-none" style="font-size:.72rem"></span>
                    <?php endif; ?>
                </td>
                <td><strong><?= sanitize($n['shortname']) ?></strong></td>
                <td><code><?= sanitize($n['nasname']) ?></code></td>
                <td>
                    <?php if (strtolower($n['type'] ?? '') === 'ruijie'): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-router me-1"></i>Ruijie</span>
                    <?php elseif (strtolower($n['type'] ?? '') === 'mikrotik'): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">MikroTik</span>
                    <?php elseif (strtolower($n['type'] ?? '') === 'cisco'): ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle">Cisco</span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark border"><?= sanitize(ucfirst($n['type'] ?: 'other')) ?></span>
                    <?php endif; ?>
                </td>
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
    if (confirm("Delete NAS device: " + name + "?\\nFreeRADIUS will no longer accept requests from this host.")) {
        document.getElementById("deleteId").value = id;
        document.getElementById("deleteForm").submit();
    }
}

function renderNasBadge(id, st) {
    const badge = document.getElementById("nas_status_badge_" + id);
    const latencyEl = document.getElementById("nas_latency_" + id);
    if (!badge) return;

    if (st.status === "online") {
        badge.className = "badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1.5 py-1 px-2";
        badge.title = "Host reachable (" + st.latency + "ms)";
        badge.innerHTML = \'<span class="spinner-grow spinner-grow-sm" style="width:0.45rem;height:0.45rem" role="status"></span> Online\';
        if (latencyEl) {
            latencyEl.textContent = st.latency + "ms";
            latencyEl.classList.remove("d-none");
        }
    } else if (st.status === "wildcard") {
        badge.className = "badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1 py-1 px-2";
        badge.title = "Subnet / Wildcard match pattern";
        badge.innerHTML = \'<i class="bi bi-diagram-2" style="font-size:.75rem"></i> Subnet\';
        if (latencyEl) latencyEl.classList.add("d-none");
    } else {
        badge.className = "badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1 py-1 px-2";
        badge.title = "Unreachable / Timeout";
        badge.innerHTML = \'<i class="bi bi-x-circle-fill" style="font-size:.65rem"></i> Offline\';
        if (latencyEl) latencyEl.classList.add("d-none");
    }
}

function triggerStatusProbe(force = false) {
    const btn = document.getElementById("btnRefreshStatus");
    const icon = document.getElementById("refreshIcon");
    const txt = document.getElementById("refreshText");
    if (btn) btn.disabled = true;
    if (icon) icon.classList.add("spin-clockwise");
    if (txt) txt.textContent = "Checking...";

    if (force) {
        document.querySelectorAll(\'[id^="nas_status_badge_"]\').forEach(el => {
            if (!el.textContent.includes("Subnet")) {
                el.className = "badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1.5 py-1 px-2";
                el.innerHTML = \'<span class="spinner-border spinner-border-sm" style="width:0.5rem;height:0.5rem" role="status"></span> Checking...\';
            }
        });
    }

    fetch("api-nas-status.php" + (force ? "?force=1" : ""))
        .then(r => r.json())
        .then(data => {
            if (data.success && data.statuses) {
                Object.keys(data.statuses).forEach(id => {
                    renderNasBadge(id, data.statuses[id]);
                });
            }
        })
        .catch(err => {
            console.error("NAS status probe error:", err);
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove("spin-clockwise");
            if (txt) txt.textContent = "Check Status";
        });
}

document.addEventListener("DOMContentLoaded", function() {
' . ($needAsyncProbe ? '    triggerStatusProbe(false);' : '') . '
});
</script>
<style>
.spin-clockwise {
    display: inline-block;
    animation: spin 1s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>';
include __DIR__ . '/includes/footer.php';

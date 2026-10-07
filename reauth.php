<?php
/**
 * CENDANA — Force Re-Login / Session Disconnect (Admin Only)
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Allows administrators to force all accounts or specific accounts to re-authenticate
 * so that their network/internet connection is re-established.
 */

require_once __DIR__ . '/auth.php';
requireLogin();

// Strict Administrator-Only Access Guard
if (!hasRole('superadmin')) {
    header('Location: welcome.php');
    exit;
}

$page_title = 'Paksa Re-Login (Reset Sesi)';
$db = getDB();

$msg = '';
$msgType = 'info';

// Handle Disconnect Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'disconnect_all') {
        // 1. Force Disconnect ALL Accounts
        $nasList = $db->query("SELECT DISTINCT nasipaddress FROM radacct WHERE acctstoptime IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        $count = terminateAllRadiusSessions('Admin-Force-Reauth');
        
        foreach ($nasList as $nasIp) {
            if ($nasIp) {
                sendRadiusDisconnect($nasIp, 'all');
            }
        }
        
        auditLog('FORCE_DISCONNECT_ALL', 'ALL_ACCOUNTS', "Terminated $count active session(s) to force re-login for all online devices");
        $msg = "<strong>Berhasil!</strong> Sebanyak <strong>" . number_format($count) . " sesi aktif</strong> telah diputus secara serentak. Seluruh pengguna wajib melakukan login ulang pada perangkat mereka untuk memulihkan koneksi internet.";
        $msgType = 'success';

    } elseif ($action === 'disconnect_user') {
        // 2. Force Disconnect Specific User by Username
        $targetUser = trim($_POST['username'] ?? '');
        if ($targetUser === '') {
            $msg = 'Silakan masukkan username akun yang ingin diputus koneksinya.';
            $msgType = 'danger';
        } else {
            $sessStmt = $db->prepare("SELECT acctsessionid, nasipaddress, framedipaddress FROM radacct WHERE username = ? AND acctstoptime IS NULL");
            $sessStmt->execute([$targetUser]);
            $activeList = $sessStmt->fetchAll(PDO::FETCH_ASSOC);
            $count = terminateRadiusSession('', $targetUser, 'Admin-Force-Reauth');

            foreach ($activeList as $row) {
                sendRadiusDisconnect($row['nasipaddress'] ?? '', $targetUser, $row['acctsessionid'] ?? '', $row['framedipaddress'] ?? '');
            }

            auditLog('FORCE_DISCONNECT_USER', $targetUser, "Terminated $count active session(s) for user $targetUser to force re-login");
            if ($count > 0) {
                $msg = "<strong>Berhasil!</strong> Akun <strong>" . htmlspecialchars($targetUser) . "</strong> (" . number_format($count) . " sesi) telah diputus. Pengguna harus melakukan autentikasi ulang untuk menyambung ke internet kembali.";
                $msgType = 'success';
            } else {
                $msg = "Akun <strong>" . htmlspecialchars($targetUser) . "</strong> saat ini tidak memiliki sesi online aktif, namun sinyal reset sesi telah dicatat.";
                $msgType = 'warning';
            }
        }

    } elseif ($action === 'disconnect_single') {
        // 3. Force Disconnect Single Session from table
        $sid   = trim($_POST['session_id'] ?? '');
        $u     = trim($_POST['username'] ?? '');
        $nasIp = trim($_POST['nasip'] ?? '');
        $fIp   = trim($_POST['framedip'] ?? '');

        if ($sid !== '') {
            terminateRadiusSession($sid, '', 'Admin-Force-Reauth');
            sendRadiusDisconnect($nasIp, $u, $sid, $fIp);
            auditLog('FORCE_DISCONNECT_SESSION', $u, "Terminated session $sid on NAS $nasIp ($fIp)");
            $msg = "Sesi online untuk <strong>" . htmlspecialchars($u) . "</strong> (IP: $fIp) telah diputus. Perangkat pengguna harus re-login.";
            $msgType = 'success';
        }

    } elseif ($action === 'disconnect_selected') {
        // 4. Force Disconnect Multi-Selected Sessions
        $selectedSids = $_POST['session_ids'] ?? [];
        if (!empty($selectedSids) && is_array($selectedSids)) {
            $count = 0;
            foreach ($selectedSids as $sid) {
                $sid = trim((string)$sid);
                if ($sid !== '') {
                    $sRow = $db->prepare("SELECT username, nasipaddress, framedipaddress FROM radacct WHERE acctsessionid = ? AND acctstoptime IS NULL LIMIT 1");
                    $sRow->execute([$sid]);
                    $row = $sRow->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        terminateRadiusSession($sid, '', 'Admin-Force-Reauth');
                        sendRadiusDisconnect($row['nasipaddress'] ?? '', $row['username'] ?? '', $sid, $row['framedipaddress'] ?? '');
                        auditLog('FORCE_DISCONNECT_SESSION', $row['username'] ?? '', "Terminated session $sid via batch kick");
                        $count++;
                    }
                }
            }
            $msg = "Berhasil memutuskan <strong>" . number_format($count) . " sesi terpilih</strong>. Perangkat akun terkait harus login ulang.";
            $msgType = 'success';
        } else {
            $msg = "Tidak ada sesi yang dipilih.";
            $msgType = 'warning';
        }
    }
}

// Total online count
$onlineCount = (int)($db->query("SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL")->fetchColumn() ?? 0);

// Search & Filtering for active sessions
$search = trim($_GET['q'] ?? '');
$whereSql = "WHERE acctstoptime IS NULL";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (username LIKE :q1 OR framedipaddress LIKE :q2 OR callingstationid LIKE :q3)";
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM radacct $whereSql");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();

// Pagination
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$pag     = paginate($filteredTotal, $page, $perPage);
$lim     = (int)$pag['per_page'];
$off     = (int)$pag['offset'];

$sessQuery = $db->prepare("
    SELECT radacctid, acctsessionid, username, nasipaddress, framedipaddress,
           callingstationid, acctstarttime, acctinputoctets, acctoutputoctets
    FROM radacct
    $whereSql
    ORDER BY acctstarttime DESC
    LIMIT $lim OFFSET $off
");
$sessQuery->execute($params);
$activeSessions = $sessQuery->fetchAll(PDO::FETCH_ASSOC);

// Recent Audit Trail for Disconnects
$recentLogs = [];
if (dbTableExists('rm_audit_log')) {
    $recentLogs = $db->query("
        SELECT operator, action, target, detail, ip_address, created_at
        FROM rm_audit_log
        WHERE action LIKE 'FORCE_DISCONNECT%'
        ORDER BY id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="welcome.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Paksa Re-Login</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1"><i class="bi bi-broadcast-pin text-danger me-2"></i>Paksa Re-Login &amp; Reset Sesi Internet</h4>
        <p class="text-secondary small mb-0">Fitur khusus administrator untuk memutuskan koneksi akun agar perangkat pengguna melakukan autentikasi ulang (re-login) guna memulihkan sambungan internet.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
            <i class="bi bi-wifi me-1"></i> <?= number_format($onlineCount) ?> Akun Sedang Online
        </span>
        <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Status">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i class="bi <?= $msgType === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?> fs-5"></i>
        <div><?= $msg ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Control Cards Grid -->
<div class="row g-3 mb-4">
    <!-- Card 1: Force Disconnect ALL Accounts -->
    <div class="col-lg-6">
        <div class="card h-100 border-danger border-opacity-25 shadow-sm">
            <div class="card-header bg-danger text-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="bi bi-radioactive me-2"></i>1. Paksa Re-Login SELURUH Akun (Global Reset)</span>
                <span class="badge bg-white text-danger fw-bold"><?= number_format($onlineCount) ?> Online</span>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        <strong>Tindakan Berskala Penuh:</strong> Perintah ini akan memutuskan seluruh sesi internet yang aktif saat ini. Semua pengguna di jaringan (Wi-Fi/Hotspot/PPPoE) akan terputus dan diarahkan ke captive portal / login ulang.
                    </div>
                    <p class="text-secondary small mb-3">
                        Gunakan fitur ini ketika terjadi perubahan kebijakan bandwidth global, pergantian shared secret router, atau rekonfigurasi gateway darurat di mana semua pengguna perlu mendapatkan alamat IP dan kebijakan baru.
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-danger w-100 py-2.5 fw-bold d-flex align-items-center justify-content-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#confirmAllModal" <?= $onlineCount === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-power fs-5"></i>
                        <span>Putus Koneksi Seluruh Akun Online (<?= number_format($onlineCount) ?>)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Force Disconnect Specific User by Username -->
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark"><i class="bi bi-person-slash me-2 text-primary"></i>2. Paksa Re-Login Akun Spesifik (Single User)</span>
                <span class="badge bg-light text-muted border">Target Akun</span>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <p class="text-secondary small mb-3">
                        Ketikkan username akun tertentu yang koneksinya bermasalah atau melanggar aturan. Sesi aktif pengguna tersebut akan diputus seketika dan mereka harus login ulang untuk dapat berselancar kembali.
                    </p>
                    <form method="POST" id="userDisconnectForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="disconnect_user">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Username Pengguna (Subscriber)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Contoh: budi.santoso / voucher_user" required>
                                <button type="submit" class="btn btn-primary px-3 fw-semibold">
                                    <i class="bi bi-power me-1"></i> Putus Sesi User
                                </button>
                            </div>
                            <div class="form-text small text-muted">Sistem akan mencari sesi di radacct dan mengirim paket Disconnect (CoA) ke NAS terkait.</div>
                        </div>
                    </form>
                </div>
                <div class="p-3 bg-light rounded-3 border small text-secondary">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    <strong>Tips:</strong> Anda juga dapat langsung memilih dan memutuskan akun secara interaktif dari tabel sesi aktif di bawah ini.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Online Sessions List with Multi-Select Actions -->
<div class="card border shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-5">
                <h5 class="mb-0 fw-bold fs-6">
                    <i class="bi bi-list-check me-2 text-success"></i>Daftar Sesi Online Aktif (<?= number_format($filteredTotal) ?>)
                </h5>
            </div>
            <div class="col-md-7">
                <form method="GET" class="d-flex gap-2 justify-content-md-end">
                    <div class="input-group input-group-sm" style="max-width: 320px;">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari username, IP, MAC..." value="<?= htmlspecialchars($search) ?>">
                        <?php if ($search !== ''): ?>
                        <a href="reauth.php" class="btn btn-outline-secondary" title="Hapus Filter"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-secondary">Cari</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="batchDisconnectForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="disconnect_selected">

        <div class="card-body p-0">
            <!-- Batch Action Toolbar -->
            <div class="p-2.5 bg-light border-bottom d-flex align-items-center justify-content-between px-3">
                <div class="d-flex align-items-center gap-2">
                    <input type="checkbox" class="form-check-input" id="checkAll" title="Pilih Semua di Halaman Ini">
                    <label for="checkAll" class="form-check-label small text-secondary fw-semibold">Pilih Semua</label>
                </div>
                <div>
                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-3" id="batchBtn" disabled onclick="return confirm('Apakah Anda yakin ingin memutuskan seluruh sesi yang dicentang?')">
                        <i class="bi bi-power me-1"></i> Putus Sesi Terpilih (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Username</th>
                            <th>Alamat IP (Framed IP)</th>
                            <th>MAC Address (Calling Station)</th>
                            <th>NAS Gateway</th>
                            <th>Online Sejak</th>
                            <th>Durasi Sesi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeSessions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                Tidak ada sesi online aktif yang cocok dengan kriteria pencarian.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($activeSessions as $sess): ?>
                        <?php
                            $uptimeSec = time() - strtotime($sess['acctstarttime']);
                            $durationText = formatDuration(max(0, $uptimeSec));
                        ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="session_ids[]" value="<?= htmlspecialchars($sess['acctsessionid']) ?>" class="form-check-input session-checkbox">
                            </td>
                            <td>
                                <a href="users.php?search=<?= urlencode($sess['username']) ?>" class="fw-bold text-decoration-none text-dark">
                                    <?= htmlspecialchars($sess['username']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?= htmlspecialchars($sess['framedipaddress'] ?: '—') ?>
                                </span>
                            </td>
                            <td class="small font-monospace text-muted">
                                <?= htmlspecialchars($sess['callingstationid'] ?: '—') ?>
                            </td>
                            <td class="small">
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= htmlspecialchars($sess['nasipaddress'] ?: '—') ?>
                                </span>
                            </td>
                            <td class="small text-secondary">
                                <?= date('d M H:i', strtotime($sess['acctstarttime'])) ?>
                            </td>
                            <td class="small fw-semibold text-success">
                                <i class="bi bi-stopwatch me-1"></i><?= $durationText ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2.5"
                                        title="Paksa re-login sesi ini"
                                        onclick="kickSingleSession('<?= htmlspecialchars(addslashes($sess['acctsessionid'])) ?>', '<?= htmlspecialchars(addslashes($sess['username'])) ?>', '<?= htmlspecialchars(addslashes($sess['nasipaddress'])) ?>', '<?= htmlspecialchars(addslashes($sess['framedipaddress'])) ?>')">
                                    <i class="bi bi-power me-1"></i>Paksa Re-Login
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
        <span class="small text-muted">Menampilkan <?= count($activeSessions) ?> dari <?= number_format($filteredTotal) ?> sesi</span>
        <?= paginationLinks($pag, "reauth.php?q=" . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Force Disconnect Audit Trail -->
<?php if (!empty($recentLogs)): ?>
<div class="card border shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold fs-6 text-secondary">
            <i class="bi bi-clock-history me-2"></i>Riwayat Eksekusi Paksa Re-Login Terakhir (Audit Log)
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0 small">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Administrator</th>
                    <th>Tindakan</th>
                    <th>Target</th>
                    <th>Detail Keterangan</th>
                    <th>IP Administrator</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td class="text-muted"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($log['operator']) ?></td>
                    <td><span class="badge bg-danger-subtle text-danger border"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($log['target']) ?></td>
                    <td><?= htmlspecialchars($log['detail']) ?></td>
                    <td class="font-monospace text-muted"><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Confirmation Modal: Force All -->
<div class="modal fade" id="confirmAllModal" tabindex="-1" aria-labelledby="confirmAllLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="disconnect_all">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="confirmAllLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Pemutusan Sesi Seluruh Akun
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-danger fw-bold mb-2">PERINGATAN KRUSIAL!</p>
                    <p class="small text-secondary mb-3">
                        Tindakan ini akan <strong>memutuskan <?= number_format($onlineCount) ?> pengguna online</strong> dari jaringan sekaligus. Sinyal disconnect akan dikirimkan ke router/gateway NAS, dan seluruh user harus melakukan login kembali untuk mendapatkan koneksi internet.
                    </p>
                    <div class="p-3 bg-light rounded-2 border small mb-3">
                        Ketikkan kata <strong>RESET</strong> di bawah ini untuk mengonfirmasi tindakan:
                        <input type="text" id="confirmInput" class="form-control form-control-sm mt-2" placeholder="RESET" autocomplete="off" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batalkan</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-3" id="confirmSubmitBtn" disabled>
                        <i class="bi bi-power me-1"></i> Ya, Putus Seluruh Akun
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Single Session Disconnect Form -->
<form method="POST" id="singleKickForm" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="disconnect_single">
    <input type="hidden" name="session_id" id="kickSid">
    <input type="hidden" name="username" id="kickUser">
    <input type="hidden" name="nasip" id="kickNas">
    <input type="hidden" name="framedip" id="kickFip">
</form>

<script>
// Checkbox management for batch disconnect
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.session-checkbox');
    const batchBtn = document.getElementById('batchBtn');
    const selectedCount = document.getElementById('selectedCount');

    function updateSelected() {
        let count = 0;
        checkboxes.forEach(cb => { if (cb.checked) count++; });
        if (selectedCount) selectedCount.textContent = count;
        if (batchBtn) batchBtn.disabled = (count === 0);
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = checkAll.checked; });
            updateSelected();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelected);
    });

    // Confirmation input guard for Reset All
    const confirmInput = document.getElementById('confirmInput');
    const confirmBtn = document.getElementById('confirmSubmitBtn');
    if (confirmInput && confirmBtn) {
        confirmInput.addEventListener('input', function() {
            confirmBtn.disabled = (this.value.trim().toUpperCase() !== 'RESET');
        });
    }
});

function kickSingleSession(sid, user, nasip, fip) {
    if (confirm(`Apakah Anda yakin ingin memutuskan sesi user '${user}' (${fip}) agar mereka login ulang?`)) {
        document.getElementById('kickSid').value = sid;
        document.getElementById('kickUser').value = user;
        document.getElementById('kickNas').value = nasip;
        document.getElementById('kickFip').value = fip;
        document.getElementById('singleKickForm').submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


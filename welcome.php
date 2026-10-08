<?php
/**
 * CENDANA — Welcome & Navigation Hub (Landing Page)
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Directs users here upon login before accessing data-heavy charts.
 */

require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Welcome & Navigation Hub';
$db = getDB();

// Fast lightweight stats (0.5ms)
$totalUsers     = (int)($db->query("SELECT COUNT(DISTINCT username) c FROM radcheck")->fetch()['c'] ?? 0);
$activeSessions = (int)($db->query("SELECT COUNT(*) c FROM radacct WHERE acctstoptime IS NULL")->fetch()['c'] ?? 0);
$totalNas       = (int)($db->query("SELECT COUNT(*) c FROM nas")->fetch()['c'] ?? 0);
$totalVouchers  = dbTableExists('rm_vouchers') ? (int)($db->query("SELECT COUNT(*) c FROM rm_vouchers")->fetch()['c'] ?? 0) : 0;
$totalPlans     = dbTableExists('rm_plans') ? (int)($db->query("SELECT COUNT(*) c FROM rm_plans")->fetch()['c'] ?? 0) : 0;

// Operator details
$adminUser = getAdminUser();
$adminName = getAdminName();
$adminRole = getAdminRole();

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome Hero Header -->
<div class="card mb-4 border-0 shadow-sm overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #064e3b 100%); color: #fff; border-radius: 14px;">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-3" style="background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.35); color: #6ee7b7; font-size: .8rem; font-weight: 600;">
                    <i class="bi bi-shield-check text-success"></i> FreeRADIUS Connected &bull; AAA Service Active
                </div>
                <h2 class="fw-bold mb-2 text-white" style="letter-spacing: -0.02em;">
                    Selamat Datang, <?= htmlspecialchars($adminName) ?>!
                </h2>
                <p class="mb-3 text-slate-300" style="color: #cbd5e1; font-size: 1rem; max-width: 650px; line-height: 1.5;">
                    Anda terautentikasi di <strong><?= APP_NAME ?></strong> (<em><?= APP_FULL_NAME ?></em>). Ini adalah pusat kendali direktori akses dan administrasi FreeRADIUS.
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2 pt-1 text-muted small">
                    <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1">
                        <i class="bi bi-person-badge me-1"></i> Role: <?= ucfirst(htmlspecialchars($adminRole)) ?>
                    </span>
                    <span class="badge bg-dark-subtle text-light border border-secondary px-2.5 py-1">
                        <i class="bi bi-database me-1"></i> DB: <?= DB_NAME ?> @ <?= DB_HOST ?>
                    </span>
                    <span class="badge bg-dark-subtle text-light border border-secondary px-2.5 py-1">
                        <i class="bi bi-clock me-1"></i> <?= date('d M Y, H:i') ?>
                    </span>
                </div>
            </div>

            <!-- Primary Call to Action Button to Access Data Charts -->
            <div class="col-lg-4 mt-4 mt-lg-0 text-lg-end">
                <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(8px);">
                    <div class="text-uppercase small fw-bold mb-2 text-light" style="font-size: .75rem; letter-spacing: .08em;">
                        <i class="bi bi-bar-chart-line text-warning me-1"></i> Data Analytics &amp; Metrics
                    </div>
                    <a href="dashboard.php" class="btn btn-primary btn-lg w-100 py-2.5 mb-2 d-inline-flex align-items-center justify-content-center gap-2 shadow" style="border-radius: 9px; font-weight: 700;">
                        <i class="bi bi-speedometer2 fs-5"></i>
                        <span>Buka Dashboard Grafik</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                    <div class="d-grid gap-1">
                        <div class="row g-1">
                            <div class="col-6">
                                <a href="dashboard.php?view=today" class="btn btn-sm btn-outline-light w-100 py-1.5" style="font-size:.75rem; border-color: rgba(255,255,255,0.25);" title="Lihat grafik sesi dan lalu lintas data hari ini">
                                    <i class="bi bi-calendar-event text-success me-1"></i> Grafik Hari Ini
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="dashboard.php?view=general" class="btn btn-sm btn-outline-light w-100 py-1.5" style="font-size:.75rem; border-color: rgba(255,255,255,0.25);" title="Lihat grafik analitik umum dan historis 30 hari">
                                    <i class="bi bi-calendar-range text-info me-1"></i> Grafik Umum
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick KPI Snapshot Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="users.php" class="stat-card" title="Buka Direktori Pengguna">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-people text-primary"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Total Subscribers <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="sessions.php" class="stat-card" title="Buka Pemantau Sesi Aktif">
            <div class="stat-icon" style="background:#f0fdf4">
                <i class="bi bi-activity text-success"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($activeSessions) ?></div>
                <div class="stat-label">Sesi Online Aktif <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="nas.php" class="stat-card" title="Buka Daftar NAS Gateway">
            <div class="stat-icon" style="background:#fefce8">
                <i class="bi bi-hdd-network text-warning"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalNas) ?></div>
                <div class="stat-label">NAS Access Points <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="vouchers.php" class="stat-card" title="Buka Manajemen Voucher Hotspot">
            <div class="stat-icon" style="background:#fdf4ff">
                <i class="bi bi-ticket-perforated" style="color:#9333ea"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalVouchers) ?></div>
                <div class="stat-label">Voucher Hotspot <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </a>
    </div>
</div>

<!-- Operational Modules Hub -->
<div class="card mb-4 border">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-bold fs-6">
            <i class="bi bi-grid me-2 text-primary"></i>Pusat Navigasi &amp; Akses Fitur Utama
        </h5>
        <span class="badge bg-light text-muted border">Pilih modul kerja</span>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <!-- Module 1: Subscriber Directory -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #2563eb !important;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-primary-subtle text-primary">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Direktori Pengguna</h6>
                    </div>
                    <p class="text-secondary small mb-3">
                        Kelola akun subscriber FreeRADIUS, batas kecepatan bandwidth, masa aktif, dan impor CSV massal.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="users.php" class="btn btn-sm btn-outline-primary py-1 px-2.5">Daftar User</a>
                        <a href="user-add.php" class="btn btn-sm btn-primary py-1 px-2.5"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
                    </div>
                </div>
            </div>

            <!-- Module 2: Active Sessions & Re-Auth -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #10b981 !important;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-success-subtle text-success">
                            <i class="bi bi-broadcast fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Pemantauan Sesi Aktif</h6>
                    </div>
                    <p class="text-secondary small mb-3">
                        Pantau pengguna yang sedang terhubung ke Wi-Fi / AP, alamat IP teralokasi, dan durasi online saat ini.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="sessions.php" class="btn btn-sm btn-outline-success py-1 px-2.5">Lihat Sesi Aktif</a>
                        <?php if (hasRole('superadmin')): ?>
                        <a href="reauth.php" class="btn btn-sm btn-outline-danger py-1 px-2.5" title="Paksa logout akun tertentu atau seluruh pengguna">
                            <i class="bi bi-power me-1"></i>Paksa Re-Login
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Module 3: Hotspot & Vouchers -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #9333ea !important;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded" style="background:#f3e8ff; color:#9333ea">
                            <i class="bi bi-ticket-perforated-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Hotspot &amp; Voucher</h6>
                    </div>
                    <p class="text-secondary small mb-3">
                        Generate kredensial voucher batch untuk tamu/event, dan cetak kartu voucher siap pakai layout A4.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="vouchers.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5">Inventori</a>
                        <a href="voucher-generate.php" class="btn btn-sm btn-purple text-white py-1 px-2.5" style="background:#9333ea"><i class="bi bi-magic me-1"></i>Generate</a>
                    </div>
                </div>
            </div>

            <!-- Module 4: NAS & Wireless Hardware -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #f59e0b !important;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-warning-subtle text-warning">
                            <i class="bi bi-router-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Gateway &amp; NAS AP</h6>
                    </div>
                    <p class="text-secondary small mb-3">
                        Registrasi router MikroTik, Ruijie AP Controller, shared secret RADIUS, dan tes probe konektivitas.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="nas.php" class="btn btn-sm btn-outline-warning text-dark py-1 px-2.5">Daftar NAS</a>
                        <a href="nas-add.php" class="btn btn-sm btn-warning text-dark py-1 px-2.5"><i class="bi bi-plus-lg me-1"></i>Tambah NAS</a>
                    </div>
                </div>
            </div>

            <!-- Module 5: Accounting & Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #06b6d4 !important;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-info-subtle text-info">
                            <i class="bi bi-file-earmark-bar-graph-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Accounting &amp; Laporan</h6>
                    </div>
                    <p class="text-secondary small mb-3">
                        Riwayat penggunaan data internet, rekapitulasi sesi bulanan, log autentikasi, serta ekspor CSV/PDF.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="accounting.php" class="btn btn-sm btn-outline-info text-dark py-1 px-2.5">Accounting</a>
                        <a href="reports.php" class="btn btn-sm btn-info text-white py-1 px-2.5">Laporan Eksekutif</a>
                    </div>
                </div>
            </div>

            <!-- Module 6: System Configuration & Admin Security -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #64748b !important;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 rounded bg-secondary-subtle text-secondary">
                            <i class="bi bi-sliders2-vertical fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Pengaturan Sistem &amp; RBAC</h6>
                    </div>
                    <p class="text-secondary small mb-3">
                        Konfigurasi parameter AAA daloRADIUS parity, manajemen operator, hak akses RBAC, dan audit trail.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="settings.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5">Konfigurasi</a>
                        <?php if (hasRole('superadmin')): ?>
                        <a href="operators.php" class="btn btn-sm btn-secondary py-1 px-2.5">Operator &amp; RBAC</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Fast Tool Banner (Only for Superadmin) -->
<?php if (hasRole('superadmin')): ?>
<div class="card border-warning border-opacity-50 mb-4 bg-warning-subtle shadow-sm">
    <div class="card-body p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 rounded bg-warning text-dark fs-4">
                <i class="bi bi-broadcast-pin"></i>
            </div>
            <div>
                <div class="fw-bold text-dark">Fitur Administrator: Paksa Re-Login (Force Disconnect)</div>
                <div class="text-muted small">Putus sesi akun tertentu atau seluruh pengguna online sekaligus agar koneksi internet mereka diperbarui via re-autentikasi.</div>
            </div>
        </div>
        <div>
            <a href="reauth.php" class="btn btn-danger btn-sm px-3 py-2 fw-semibold text-white d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-power"></i> Kelola Paksa Re-Login
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-shadow { transition: transform .15s ease, box-shadow .15s ease; }
.hover-shadow:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.06); }
</style>

<?php
$extra_js = '<script>
(function() {
    // Instant responsive feedback when clicking dashboard CTA buttons
    document.querySelectorAll(\'a[href*="dashboard.php"]\').forEach(function(link) {
        link.addEventListener("click", function(e) {
            var icon = this.querySelector("i");
            if (icon && !this.classList.contains("disabled")) {
                icon.className = "spinner-border spinner-border-sm me-1";
                icon.style.display = "inline-block";
            }
        });
    });
})();
</script>';
include __DIR__ . '/includes/footer.php';
?>



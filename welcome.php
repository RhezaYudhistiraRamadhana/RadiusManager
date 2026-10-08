<?php
/**
 * CENDANA — Welcome & Navigation Hub (Landing Page / Beranda)
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Directs users here upon login before accessing data-heavy charts.
 */

require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Beranda & Pusat Navigasi';
$db = getDB();

// Fast lightweight stats (< 1ms)
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

<!-- Clean & Simple Welcome Banner -->
<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #064e3b 100%); color: #fff; border-radius: 14px;">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 px-2.5 py-1 rounded-pill mb-2" style="background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.35); color: #6ee7b7; font-size: .78rem; font-weight: 600;">
                    <i class="bi bi-shield-check text-success"></i> FreeRADIUS Connected &bull; AAA Service Active
                </div>
                <h3 class="fw-bold mb-1 text-white" style="letter-spacing: -0.02em;">
                    Selamat Datang, <?= htmlspecialchars($adminName) ?>!
                </h3>
                <p class="text-secondary small mb-0" style="color: #cbd5e1 !important; font-size: .88rem;">
                    Pusat kendali dan administrasi direktori akses jaringan <strong><?= APP_NAME ?></strong> (<em><?= APP_FULL_NAME ?></em>).
                </p>
            </div>

            <!-- Sleek Dashboard CTA Action Buttons -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="dashboard.php?view=today" class="btn btn-outline-light btn-sm px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" style="border-radius: 8px; border-color: rgba(255,255,255,0.25);" title="Lihat profil sesi dan lalu lintas data hari ini">
                    <i class="bi bi-clock-history text-success"></i>
                    <span>Grafik Hari Ini</span>
                </a>
                <a href="dashboard.php?view=general" class="btn btn-outline-light btn-sm px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" style="border-radius: 8px; border-color: rgba(255,255,255,0.25);" title="Lihat tren historis multi-hari dan volume data">
                    <i class="bi bi-graph-up text-info"></i>
                    <span>Grafik Umum</span>
                </a>
                <a href="dashboard.php" class="btn btn-primary btn-sm px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" style="border-radius: 8px; font-weight: 600;">
                    <i class="bi bi-speedometer2"></i>
                    <span>Buka Dashboard</span>
                </a>
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

<!-- Pusat Navigasi & Akses Fitur Utama (Interactive Feature Hub) -->
<div class="card mb-4 border shadow-sm" style="border-radius: 12px;">
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
                    <a href="users.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Direktori Pengguna">
                        <div class="p-2 rounded bg-primary-subtle text-primary">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Direktori Pengguna</h6>
                    </a>
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
                    <a href="sessions.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Pemantauan Sesi Aktif">
                        <div class="p-2 rounded bg-success-subtle text-success">
                            <i class="bi bi-broadcast fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Pemantauan Sesi Aktif</h6>
                    </a>
                    <p class="text-secondary small mb-3">
                        Pantau pengguna yang sedang terhubung ke Wi-Fi / AP, alamat IP teralokasi, dan durasi online saat ini.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="sessions.php" class="btn btn-sm btn-outline-success py-1 px-2.5">Lihat Sesi Aktif</a>
                        <a href="reauth.php" class="btn btn-sm btn-outline-danger py-1 px-2.5 <?= hasRole('superadmin') ? '' : 'disabled' ?>" title="Paksa logout akun tertentu atau seluruh pengguna agar re-koneksi">
                            <i class="bi bi-power me-1"></i>Paksa Re-Login
                        </a>
                    </div>
                </div>
            </div>

            <!-- Module 3: Hotspot & Vouchers -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #9333ea !important;">
                    <a href="vouchers.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Hotspot & Voucher">
                        <div class="p-2 rounded" style="background:#f3e8ff; color:#9333ea">
                            <i class="bi bi-ticket-perforated-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Hotspot &amp; Voucher</h6>
                    </a>
                    <p class="text-secondary small mb-3">
                        Generate kredensial voucher batch untuk tamu/event, dan cetak kartu voucher siap pakai layout A4.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="vouchers.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5">Inventori</a>
                        <a href="voucher-generate.php" class="btn btn-sm text-white py-1 px-2.5" style="background:#9333ea"><i class="bi bi-magic me-1"></i>Generate</a>
                    </div>
                </div>
            </div>

            <!-- Module 4: NAS & Wireless Hardware -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #f59e0b !important;">
                    <a href="nas.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Gateway & NAS AP">
                        <div class="p-2 rounded bg-warning-subtle text-warning">
                            <i class="bi bi-router-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Gateway &amp; NAS AP</h6>
                    </a>
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
                    <a href="accounting.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Accounting & Laporan">
                        <div class="p-2 rounded bg-info-subtle text-info">
                            <i class="bi bi-file-earmark-bar-graph-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Accounting &amp; Laporan</h6>
                    </a>
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
                    <a href="settings.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Pengaturan Sistem & RBAC">
                        <div class="p-2 rounded bg-secondary-subtle text-secondary">
                            <i class="bi bi-sliders2-vertical fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Pengaturan Sistem &amp; RBAC</h6>
                    </a>
                    <p class="text-secondary small mb-3">
                        Konfigurasi parameter AAA daloRADIUS parity, manajemen operator, hak akses RBAC, dan audit trail.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="settings.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5">Konfigurasi</a>
                        <a href="operators.php" class="btn btn-sm btn-secondary py-1 px-2.5 <?= hasRole('superadmin') ? '' : 'disabled' ?>">Operator &amp; RBAC</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-shadow { transition: transform .15s ease, box-shadow .15s ease; }
.hover-shadow:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.06); }
</style>

<?php
$extra_js = '<script>
(function() {
    // Instant responsive feedback when clicking CTA buttons or navigation links
    document.querySelectorAll(\'a[href*="dashboard.php"], .card a.btn\').forEach(function(link) {
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

<?php
/**
 * CENDANA — Welcome & Navigation Hub (Landing Page / Home)
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Directs users here upon login before accessing data-heavy charts.
 * Design synchronized with Polman Bandung JATI Design System.
 */

require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Beranda';
$db = getDB();

// Cache welcome stats in session for 120 seconds to eliminate repeated queries
if (isset($_SESSION['welcome_stats_time']) && (time() - $_SESSION['welcome_stats_time'] < 120) && !empty($_SESSION['welcome_stats'])) {
    $stats          = $_SESSION['welcome_stats'];
    $totalUsers     = $stats['totalUsers'];
    $onlineAccounts = $stats['onlineAccounts'] ?? $stats['activeSessions'] ?? 0;
    $onlineDevices  = $stats['onlineDevices'] ?? $onlineAccounts;
    $totalNas       = $stats['totalNas'];
    $totalVouchers  = $stats['totalVouchers'];
    $totalPlans     = $stats['totalPlans'];
    $expiringCount  = $stats['expiringCount'] ?? 0;
    $rulesCount     = $stats['rulesCount'] ?? 0;
} else {
    $totalUsers     = (int)($db->query("SELECT COUNT(DISTINCT username) c FROM radcheck")->fetch()['c'] ?? 0);
    $activeInfo     = getTrulyActiveOnlineAccounts($db);
    $onlineAccounts = $activeInfo['total_accounts'];
    $onlineDevices  = $activeInfo['total_devices'];
    $totalNas       = (int)($db->query("SELECT COUNT(*) c FROM nas")->fetch()['c'] ?? 0);
    $totalVouchers  = dbTableExists('rm_vouchers') ? (int)($db->query("SELECT COUNT(*) c FROM rm_vouchers")->fetch()['c'] ?? 0) : 0;
    $totalPlans     = dbTableExists('rm_plans') ? (int)($db->query("SELECT COUNT(*) c FROM rm_plans")->fetch()['c'] ?? 0) : 0;
    $rulesCount     = (int)($db->query("SELECT COUNT(*) c FROM radcheck WHERE attribute = 'Simultaneous-Use'")->fetch()['c'] ?? 0);
    
    // Quick count of users with expiration date
    $expiringCount  = (int)($db->query("SELECT COUNT(*) c FROM radcheck WHERE attribute = 'Expiration'")->fetch()['c'] ?? 0);

    $_SESSION['welcome_stats'] = [
        'totalUsers'     => $totalUsers,
        'activeSessions' => $onlineAccounts,
        'onlineAccounts' => $onlineAccounts,
        'onlineDevices'  => $onlineDevices,
        'totalNas'       => $totalNas,
        'totalVouchers'  => $totalVouchers,
        'totalPlans'     => $totalPlans,
        'expiringCount'  => $expiringCount,
        'rulesCount'     => $rulesCount,
    ];
    $_SESSION['welcome_stats_time'] = time();
}

// Operator details
$adminUser = getAdminUser();
$adminName = getAdminName();
$adminRole = getAdminRole();

// Indonesian Date Formatter for JATI Top Header
$daysId   = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$monthsId = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

$dayName   = $daysId[date('l')] ?? date('l');
$monthName = $monthsId[(int)date('n')] ?? date('F');
$todayDateFormatted = $dayName . ', ' . date('j') . ' ' . $monthName . ' ' . date('Y');

// Release session lock immediately to prevent blocking concurrent requests or asset loading
session_write_close();

include __DIR__ . '/includes/header.php';
?>

<!-- JATI Header: Date & Breadcrumbs -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1" style="color: #1e293b; letter-spacing: -0.01em;">Beranda</h4>
        <div class="text-secondary fw-semibold small d-flex align-items-center gap-1.5" style="font-size: .88rem; color: #475569 !important;">
            <i class="bi bi-calendar3 text-primary"></i> <?= $todayDateFormatted ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small d-none d-md-inline" style="font-size: .8rem;">Dashboard &rsaquo; Beranda &rsaquo; <span class="text-dark fw-semibold">Index</span></span>
        <a href="dashboard.php" class="btn btn-primary btn-sm px-3 py-1.5 d-inline-flex align-items-center gap-1.5 shadow-sm" style="border-radius: 8px; font-weight: 600;">
            <i class="bi bi-speedometer2"></i> <span>Analytics Dashboard</span>
        </a>
    </div>
</div>

<!-- JATI Style Operator Greeting & System Notice Row -->
<div class="row g-3 mb-4">
    <!-- Operator Status Card (Left) -->
    <div class="col-lg-8">
        <div class="card h-100 p-3 p-md-4">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <!-- Profile Avatar Capsule -->
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm flex-shrink-0" 
                         style="width: 58px; height: 58px; background: linear-gradient(135deg, #0b4ea2 0%, #1e40af 100%); font-size: 1.35rem; border: 2px solid #e0edff;">
                        <?= strtoupper(substr($adminName, 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.01em; font-size: 1.15rem;">
                            <?= htmlspecialchars($adminName) ?>
                        </h5>
                        <div class="text-muted small fw-medium mb-1">
                            UPA Teknologi Informasi dan Komunikasi &bull; Polman Bandung
                        </div>
                        <div class="text-secondary small" style="font-size: .75rem;">
                            Akun: <span class="fw-bold text-dark"><?= htmlspecialchars($adminUser) ?></span> &bull; Hak Akses: <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0.5"><?= ucfirst($adminRole) ?></span>
                        </div>
                    </div>
                </div>

                <!-- JATI Status Pills -->
                <div class="d-flex flex-sm-column align-items-sm-end gap-1.5">
                    <span class="badge bg-success py-1.5 px-3 rounded-pill text-white shadow-xs d-inline-flex align-items-center gap-1.5" style="font-size: .78rem; font-weight: 600; background-color: #16a34a !important;">
                        <i class="bi bi-check-circle-fill"></i> Hadir / Aktif
                    </span>
                    <span class="badge py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size: .72rem; font-weight: 500;">
                        <i class="bi bi-shield-check"></i> FreeRADIUS AAA Online
                    </span>
                    <span class="text-muted d-none d-sm-block mt-1" style="font-size: .74rem;">
                        Terima kasih, sistem beroperasi normal.
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Alert Box (Right) -->
    <div class="col-lg-4">
        <div class="card h-100 p-3" style="background: #eff6ff; border: 1px solid #bfdbfe;">
            <div class="d-flex align-items-start gap-2.5">
                <div class="text-primary fs-3 flex-shrink-0" style="color: #0b4ea2 !important;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-primary" style="color: #0b4ea2 !important; font-size: .92rem;">
                        Informasi Layanan Network
                    </h6>
                    <p class="text-dark small mb-0" style="font-size: .8rem; line-height: 1.45;">
                        Pastikan seluruh gateway NAS (Ruijie AP / MikroTik) mengirimkan paket accounting (UDP 1813) agar kalkulasi durasi dan limit device berjalan real-time.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JATI 2-Column Operational Grid: Clock/Health (Left) & Soft Pastel Metric Grid (Right) -->
<div class="row g-3 mb-4">
    <!-- Jam Operasional & Status Server (Left) -->
    <div class="col-lg-5">
        <div class="card h-100 p-4 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">
                        <i class="bi bi-clock-history me-1.5 text-primary"></i> Waktu Server &amp; Status AAA
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill" style="font-size: .7rem;">
                        <i class="bi bi-circle-fill text-success" style="font-size:.38rem;"></i> Sinkron
                    </span>
                </div>

                <div class="row g-2 text-center my-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background: #f0fdf4; border: 1px solid #dcfce7;">
                            <div class="text-muted small fw-medium mb-1">Jam Server (WIB)</div>
                            <div class="fw-bold" style="font-size: 1.85rem; color: #16a34a; font-family: monospace;" id="digitalClockLive">
                                <?= date('H:i:s') ?>
                            </div>
                            <div class="text-secondary small mt-1" style="font-size: .72rem;">Asia/Jakarta</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background: #eff6ff; border: 1px solid #dbeafe;">
                            <div class="text-muted small fw-medium mb-1">Batas Sesi Normal</div>
                            <div class="fw-bold" style="font-size: 1.85rem; color: #0b4ea2; font-family: monospace;">
                                24 Jam
                            </div>
                            <div class="text-secondary small mt-1" style="font-size: .72rem;">RFC 2865 Session</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Jam Kerja / Sesi Hari Ini -->
            <div class="p-2.5 rounded-3 text-center" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                <span class="text-muted small fw-medium">
                    <i class="bi bi-broadcast text-primary me-1"></i> Total Koneksi Aktif Saat Ini:
                    <strong class="text-dark"><?= number_format($onlineAccounts) ?> Akun</strong> 
                    (<span class="text-success fw-bold"><?= number_format($onlineDevices) ?> Perangkat</span>)
                </span>
            </div>
        </div>
    </div>

    <!-- Ringkasan Jaringan (JATI 3x3 Soft Pastel Metric Grid) -->
    <div class="col-lg-7">
        <div class="card h-100 p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <span class="fw-bold text-dark small text-uppercase tracking-wider">
                    <i class="bi bi-grid-3x3-gap-fill me-1.5 text-primary"></i> Ringkasan Jaringan &amp; Subscriber
                </span>
                <span class="badge bg-light text-secondary border px-2.5 py-1" style="border-radius: 6px; font-weight: 500; font-size: .75rem;">
                    <?= $monthName . ' ' . date('Y') ?> <i class="bi bi-calendar3 ms-1 text-primary"></i>
                </span>
            </div>

            <!-- 3x3 Soft Pastel Tiles (Direct JATI Parity) -->
            <div class="row g-2.5">
                <!-- 1. Hadir / Online (Green) -->
                <div class="col-4">
                    <a href="sessions.php" class="tile-pastel tile-green text-center" title="Buka Monitor Sesi Aktif">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> Online
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #166534;">
                            <?= number_format($onlineAccounts) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            <?= number_format($onlineDevices) ?> Device
                        </div>
                    </a>
                </div>

                <!-- 2. Terlambat / Expiry (Amber) -->
                <div class="col-4">
                    <a href="expiry-check.php" class="tile-pastel tile-amber text-center" title="Buka Peringatan Masa Berlaku">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-hourglass-split text-warning me-1"></i> Expiry
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #b45309;">
                            <?= number_format($expiringCount) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Subscriber
                        </div>
                    </a>
                </div>

                <!-- 3. Tidak Hadir / Rules Limit (Red) -->
                <div class="col-4">
                    <a href="rules.php" class="tile-pastel tile-red text-center" title="Buka Aturan Akses Device">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-shield-x text-danger me-1"></i> Rules
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #b91c1c;">
                            <?= number_format($rulesCount) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Limit Sesi
                        </div>
                    </a>
                </div>

                <!-- 4. Gateways / NAS (Teal) -->
                <div class="col-4">
                    <a href="nas.php" class="tile-pastel tile-teal text-center" title="Buka Daftar NAS Access Point">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-hdd-network text-info me-1"></i> NAS AP
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #0f766e;">
                            <?= number_format($totalNas) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Gateways
                        </div>
                    </a>
                </div>

                <!-- 5. Vouchers (Purple/Rose) -->
                <div class="col-4">
                    <a href="vouchers.php" class="tile-pastel tile-purple text-center" title="Buka Manajemen Voucher">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-ticket-perforated me-1" style="color:#9333ea;"></i> Voucher
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #6b21a8;">
                            <?= number_format($totalVouchers) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Inventory
                        </div>
                    </a>
                </div>

                <!-- 6. Total Users (Blue) -->
                <div class="col-4">
                    <a href="users.php" class="tile-pastel tile-blue text-center" title="Buka Direktori Pengguna">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-people-fill text-primary me-1"></i> Users
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #1d4ed8;">
                            <?= number_format($totalUsers) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Terdaftar
                        </div>
                    </a>
                </div>

                <!-- 7. Plans (Slate) -->
                <div class="col-4">
                    <a href="plans.php" class="tile-pastel tile-slate text-center" title="Buka Paket Bandwidth">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-speedometer me-1 text-secondary"></i> Plans
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #334155;">
                            <?= number_format($totalPlans) ?>
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Bandwidth
                        </div>
                    </a>
                </div>

                <!-- 8. Dynamic Pools (Violet) -->
                <div class="col-4">
                    <a href="ippool.php" class="tile-pastel tile-violet text-center" title="Buka IP Pools">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-diagram-3 me-1" style="color:#7c3aed;"></i> IP Pool
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #6d28d9;">
                            Active
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Subnet DHCP
                        </div>
                    </a>
                </div>

                <!-- 9. Re-Login / PoD (Orange) -->
                <div class="col-4">
                    <a href="reauth.php" class="tile-pastel tile-orange text-center" title="Buka Force Re-Login">
                        <div class="small fw-bold mb-1" style="font-size: .75rem;">
                            <i class="bi bi-power me-1" style="color:#ea580c;"></i> Reauth
                        </div>
                        <div class="fw-bold" style="font-size: 1.6rem; line-height: 1.1; color: #c2410c;">
                            RFC 3576
                        </div>
                        <div class="small mt-1" style="font-size: .72rem; opacity: .85;">
                            Reset Sesi
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Center & Core Modules Hub (Clean White JATI Cards) -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-bold fs-6" style="color: #0b4ea2;">
            <i class="bi bi-grid-fill me-2 text-primary"></i>Modul Operasional &amp; Manajemen Jaringan
        </h5>
        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: .74rem; font-weight: 500;">
            Akses Cepat Modul CENDANA
        </span>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <!-- Module 1: Subscriber Directory -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #0b4ea2 !important;">
                    <a href="users.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Direktori Subscriber">
                        <div class="p-2 rounded" style="background:#e0edff; color:#0b4ea2;">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Direktori Subscriber</h6>
                    </a>
                    <p class="text-secondary small mb-3" style="font-size: .83rem;">
                        Manajemen kredensial pengguna, profil departemen, tanggal kedaluwarsa, dan impor massal CSV.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="users.php" class="btn btn-sm btn-outline-primary py-1 px-2.5">Daftar Akun</a>
                        <a href="rules.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5" title="Aturan Limit Device"><i class="bi bi-shield-lock me-1"></i>Rules</a>
                        <a href="user-add.php" class="btn btn-sm btn-primary py-1 px-2.5"><i class="bi bi-plus-lg me-1"></i>+ Akun</a>
                    </div>
                </div>
            </div>

            <!-- Module 2: Active Sessions & Force Re-Login -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #16a34a !important;">
                    <a href="sessions.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Monitor Sesi Aktif">
                        <div class="p-2 rounded bg-success-subtle text-success">
                            <i class="bi bi-broadcast fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Monitor Sesi Aktif</h6>
                    </a>
                    <p class="text-secondary small mb-3" style="font-size: .83rem;">
                        Pantau perangkat terhubung real-time, alamat IP, durasi koneksi, serta eksekusi disconnect CoA.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="sessions.php" class="btn btn-sm btn-outline-success py-1 px-2.5">Lihat Sesi</a>
                        <a href="reauth.php" class="btn btn-sm btn-outline-danger py-1 px-2.5 <?= hasRole('superadmin') ? '' : 'disabled' ?>" title="Force disconnect via RFC 3576">
                            <i class="bi bi-power me-1"></i>Force Re-Login
                        </a>
                    </div>
                </div>
            </div>

            <!-- Module 3: Hotspot & Vouchers -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #9333ea !important;">
                    <a href="vouchers.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Hotspot & Vouchers">
                        <div class="p-2 rounded" style="background:#f3e8ff; color:#9333ea">
                            <i class="bi bi-ticket-perforated-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Hotspot &amp; Voucher</h6>
                    </a>
                    <p class="text-secondary small mb-3" style="font-size: .83rem;">
                        Pembuatan voucher massal tamu/event dan cetak kartu voucher siap pakai format A4.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="vouchers.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5">Inventory</a>
                        <a href="voucher-generate.php" class="btn btn-sm text-white py-1 px-2.5" style="background:#9333ea"><i class="bi bi-magic me-1"></i>Generate</a>
                    </div>
                </div>
            </div>

            <!-- Module 4: Gateways & Wireless Hardware -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #d97706 !important;">
                    <a href="nas.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Gateways & NAS">
                        <div class="p-2 rounded" style="background:#fef3c7; color:#d97706">
                            <i class="bi bi-router-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Gateways &amp; NAS AP</h6>
                    </a>
                    <p class="text-secondary small mb-3" style="font-size: .83rem;">
                        Registrasi Ruijie AP Controller, MikroTik router, shared secret RADIUS, dan tes reachability.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="nas.php" class="btn btn-sm btn-outline-warning text-dark py-1 px-2.5">Daftar NAS</a>
                        <a href="nas-add.php" class="btn btn-sm btn-warning text-dark py-1 px-2.5"><i class="bi bi-plus-lg me-1"></i>+ NAS</a>
                    </div>
                </div>
            </div>

            <!-- Module 5: Accounting & Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #0f766e !important;">
                    <a href="accounting.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Akuntansi & Laporan">
                        <div class="p-2 rounded" style="background:#ccfbf1; color:#0f766e">
                            <i class="bi bi-file-earmark-bar-graph-fill fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Akuntansi &amp; Laporan</h6>
                    </a>
                    <p class="text-secondary small mb-3" style="font-size: .83rem;">
                        Riwayat konsumsi kuota bandwidth, log post-auth, laporan eksekutif PDF, dan ekspor CSV.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="accounting.php" class="btn btn-sm btn-outline-info text-dark py-1 px-2.5">Akuntansi</a>
                        <a href="reports.php" class="btn btn-sm btn-info text-white py-1 px-2.5">Laporan</a>
                    </div>
                </div>
            </div>

            <!-- Module 6: System Configuration & Security -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 h-100 bg-white hover-shadow transition" style="border-left: 4px solid #475569 !important;">
                    <a href="settings.php" class="text-decoration-none text-dark d-flex align-items-center gap-2 mb-2" title="Buka Konfigurasi Sistem">
                        <div class="p-2 rounded bg-secondary-subtle text-secondary">
                            <i class="bi bi-sliders2-vertical fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0">Konfigurasi &amp; RBAC</h6>
                    </a>
                    <p class="text-secondary small mb-3" style="font-size: .83rem;">
                        Parameter FreeRADIUS, daloRADIUS parity, manajemen operator, role RBAC, dan audit trail.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="settings.php" class="btn btn-sm btn-outline-secondary py-1 px-2.5">Konfigurasi</a>
                        <a href="operators.php" class="btn btn-sm btn-secondary py-1 px-2.5 <?= hasRole('superadmin') ? '' : 'disabled' ?>">Operator</a>
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
    // Live ticking digital clock for JATI operational card
    var clockEl = document.getElementById("digitalClockLive");
    if (clockEl) {
        setInterval(function() {
            var now = new Date();
            var h = String(now.getHours()).padStart(2, "0");
            var m = String(now.getMinutes()).padStart(2, "0");
            var s = String(now.getSeconds()).padStart(2, "0");
            clockEl.textContent = h + ":" + m + ":" + s;
        }, 1000);
    }

    // Instant feedback on action buttons
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
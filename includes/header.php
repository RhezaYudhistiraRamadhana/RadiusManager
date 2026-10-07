<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> <?= isset($page_title) ? '- ' . $page_title : '' ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg?v=cendana_tree_<?= APP_VERSION ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script>
        (function() {
            try {
                if (localStorage.getItem('cendana_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed-preload');
                }
            } catch (e) {}
        })();
    </script>
    <style>
        :root {
            --sidebar-w: 240px;
            --sidebar-collapsed-w: 68px;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --sidebar-active: #2563eb;
            --topbar-h: 56px;
        }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        html { scroll-behavior: smooth; }

        /* Modern Custom Scrollbar (Global) */
        * {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-button {
            display: none;
            width: 0;
            height: 0;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 9999px;
            border: 2px solid transparent;
            background-clip: content-box;
            transition: background-color .2s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8;
        }
        ::-webkit-scrollbar-thumb:active {
            background-color: #64748b;
        }
        ::-webkit-scrollbar-corner {
            background: transparent;
        }

        /* Sidebar */
        #sidebar {
            width: var(--sidebar-w); height: 100vh; position: fixed;
            top: 0; left: 0; background: var(--sidebar-bg);
            overflow-y: auto; overflow-x: hidden; z-index: 1000;
            transition: width .2s cubic-bezier(0.4, 0, 0.2, 1), transform .3s ease, box-shadow .2s ease;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }
        #sidebar::-webkit-scrollbar {
            width: 6px;
        }
        #sidebar::-webkit-scrollbar-button {
            display: none;
        }
        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        #sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.18);
            border-radius: 9999px;
            border: 1px solid transparent;
            background-clip: content-box;
        }
        #sidebar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.35);
        }
        .sidebar-header {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 62px;
            box-sizing: border-box;
            gap: 0.5rem;
        }
        .sidebar-brand {
            color: #fff;
            display: flex;
            align-items: center;
            gap: .75rem;
            text-decoration: none;
            transition: opacity .15s ease;
            min-width: 0;
            flex: 1;
        }
        .sidebar-brand:hover { color: #fff; opacity: 0.9; }
        .sidebar-collapse-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .sidebar-collapse-btn:hover {
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.35);
        }
        .sidebar-collapse-btn i,
        .collapse-footer-icon {
            transition: transform 0.2s ease;
        }
        .sidebar-brand .brand-icon-wrap {
            width: 32px; height: 32px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .sidebar-brand:hover .brand-icon-wrap {
            transform: scale(1.06);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.5);
        }
        .sidebar-brand .brand-icon-wrap img,
        .sidebar-brand .brand-icon-wrap svg {
            width: 100%; height: 100%; display: block;
        }
        .sidebar-brand .brand-info {
            display: flex; flex-direction: column; line-height: 1.15; min-width: 0;
        }
        .sidebar-brand .brand-title {
            letter-spacing: -0.01em; white-space: nowrap; font-weight: 700;
            font-size: 1.05rem; color: #fff;
        }
        .sidebar-brand .brand-ver {
            font-size: .68rem; color: #94a3b8; font-weight: 500; margin-top: 2px;
            letter-spacing: 0.02em;
        }
        .sidebar-section {
            padding: .8rem 1.2rem .3rem;
            font-size: .65rem; text-transform: uppercase;
            letter-spacing: .08em; color: #64748b; font-weight: 600;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: .75rem;
            padding: .6rem 1.5rem; color: #94a3b8;
            text-decoration: none; font-size: .875rem;
            transition: .15s; border-left: 3px solid transparent;
        }
        .sidebar-link:hover { background: var(--sidebar-hover); color: #e2e8f0; }
        .sidebar-link.active {
            background: var(--sidebar-hover); color: #fff;
            border-left-color: var(--primary);
        }
        .sidebar-link i { font-size: 1rem; width: 1.2rem; }

        /* Main content */
        #main {
            margin-left: var(--sidebar-w); min-height: 100vh;
            transition: margin-left .2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #topbar {
            height: var(--topbar-h); background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center;
            padding: 0 1.5rem; position: sticky; top: 0; z-index: 999;
        }
        .content { padding: 1.5rem; }

        .stat-card {
            background: #fff; border-radius: 10px;
            padding: 1.25rem 1.5rem; border: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 1rem;
            text-decoration: none; color: inherit;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,.06);
            border-color: #cbd5e1;
            color: inherit;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #1e293b; line-height: 1; }
        .stat-label { font-size: .8rem; color: #64748b; margin-top: .2rem; }

        /* Table */
        .card { border: 1px solid #e2e8f0; border-radius: 10px; }
        .table th { font-size: .78rem; text-transform: uppercase;
                    letter-spacing: .05em; color: #64748b; font-weight: 600;
                    border-bottom: 2px solid #e2e8f0; padding: .75rem 1rem; }
        .table td { padding: .7rem 1rem; vertical-align: middle; font-size: .875rem; }
        .table tbody tr:hover { background: #f8fafc; }

        /* Badges */
        .badge-online { background: #dcfce7; color: #166534; }
        .badge-offline { background: #fee2e2; color: #991b1b; }

        /* Buttons */
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }

        /* Alerts */
        .alert { border-radius: 8px; font-size: .875rem; }

        /* Modals */
        .modal-content {
            background-color: #ffffff !important;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
        }
        .modal-backdrop.show {
            opacity: 0.5;
        }

        /* Page header */
        .page-header { margin-bottom: 1.5rem; }
        .page-header h4 { font-weight: 700; color: #1e293b; margin: 0; }
        .page-header p { color: #64748b; font-size: .875rem; margin: .2rem 0 0; }

        /* Collapsed Sidebar (Minimized state) */
        html.sidebar-collapsed-preload #sidebar,
        body.sidebar-collapsed #sidebar {
            width: var(--sidebar-collapsed-w);
        }
        html.sidebar-collapsed-preload #main,
        body.sidebar-collapsed #main {
            margin-left: var(--sidebar-collapsed-w);
        }
        html.sidebar-collapsed-preload .sidebar-header,
        body.sidebar-collapsed .sidebar-header {
            padding: 0.75rem 0;
            justify-content: center;
            flex-direction: column;
            gap: 0.4rem;
        }
        html.sidebar-collapsed-preload .sidebar-brand,
        body.sidebar-collapsed .sidebar-brand {
            padding: 0;
            justify-content: center;
            flex: initial;
        }
        html.sidebar-collapsed-preload .sidebar-brand .brand-info,
        body.sidebar-collapsed .sidebar-brand .brand-info {
            display: none !important;
        }
        html.sidebar-collapsed-preload .sidebar-brand .brand-icon-wrap,
        body.sidebar-collapsed .sidebar-brand .brand-icon-wrap {
            width: 36px;
            height: 36px;
            margin: 0 auto;
        }
        body.sidebar-collapsed .sidebar-collapse-btn i,
        body.sidebar-collapsed .collapse-footer-icon {
            transform: rotate(180deg);
        }
        html.sidebar-collapsed-preload .sidebar-section,
        body.sidebar-collapsed .sidebar-section {
            padding: 0;
            margin: 0.6rem 0.8rem;
            height: 1px;
            background: rgba(255, 255, 255, 0.08);
            font-size: 0;
            color: transparent;
            overflow: hidden;
            line-height: 0;
        }
        html.sidebar-collapsed-preload .sidebar-link,
        body.sidebar-collapsed .sidebar-link {
            justify-content: center;
            padding: 0.75rem 0;
            gap: 0;
            border-left-width: 3px;
        }
        html.sidebar-collapsed-preload .sidebar-link .link-text,
        body.sidebar-collapsed .sidebar-link .link-text {
            display: none !important;
        }
        html.sidebar-collapsed-preload .sidebar-link i,
        body.sidebar-collapsed .sidebar-link i {
            font-size: 1.25rem;
            width: auto;
            margin: 0 auto;
            text-align: center;
        }
        body.sidebar-collapsed #sidebarCollapseBtn i {
            transform: rotate(180deg);
        }

        /* Tooltip styling for collapsed sidebar */
        .sidebar-tooltip .tooltip-inner {
            background-color: #0f172a;
            color: #f8fafc;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .sidebar-tooltip .tooltip-arrow::before {
            border-right-color: #0f172a !important;
        }
        body:not(.sidebar-collapsed) .sidebar-tooltip,
        body.sidebar-collapsed .tooltip {
            display: none !important;
        }

        @keyframes fadeInText {
            from { opacity: 0; transform: translateX(-4px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @media (min-width: 769px) {
            /* Hover-Expand Flyout on Minimized Sidebar:
               Automatically opens on mouse hover and closes on mouse leave */
            body.sidebar-collapsed #sidebar:hover {
                width: var(--sidebar-w) !important;
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.45);
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-header {
                padding: 0.85rem 1rem !important;
                justify-content: space-between !important;
                flex-direction: row !important;
                gap: 0.5rem !important;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-brand {
                justify-content: flex-start !important;
                padding: 0 !important;
                flex: 1 !important;
            }
            body.sidebar-collapsed #sidebar:hover .brand-info {
                display: flex !important;
                animation: fadeInText 0.18s ease-in-out forwards;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-brand .brand-icon-wrap {
                width: 32px !important;
                height: 32px !important;
                margin: 0 !important;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-collapse-btn i,
            body.sidebar-collapsed #sidebar:hover .collapse-footer-icon {
                transform: rotate(0deg) !important;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-section {
                padding: .8rem 1.2rem .3rem !important;
                margin: 0 !important;
                height: auto !important;
                background: transparent !important;
                font-size: .65rem !important;
                color: #64748b !important;
                line-height: normal !important;
                overflow: visible !important;
                display: block !important;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-link {
                justify-content: flex-start !important;
                padding: .6rem 1.5rem !important;
                gap: .75rem !important;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-link .link-text {
                display: inline !important;
                animation: fadeInText 0.18s ease-in-out forwards;
            }
            body.sidebar-collapsed #sidebar:hover .sidebar-link i {
                font-size: 1rem !important;
                width: 1.2rem !important;
                margin: 0 !important;
                text-align: left !important;
            }
        }

        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); width: var(--sidebar-w) !important; }
            #sidebar.show { transform: translateX(0); }
            #main { margin-left: 0 !important; }
            body.sidebar-collapsed #sidebar,
            html.sidebar-collapsed-preload #sidebar { width: var(--sidebar-w) !important; }
            body.sidebar-collapsed #main,
            html.sidebar-collapsed-preload #main { margin-left: 0 !important; }
            body.sidebar-collapsed .sidebar-brand .brand-info,
            body.sidebar-collapsed .sidebar-link .link-text { display: inline !important; }
            body.sidebar-collapsed .sidebar-section {
                height: auto !important;
                font-size: .65rem !important;
                color: #64748b !important;
                padding: .8rem 1.2rem .3rem !important;
                margin: 0 !important;
                background: transparent !important;
            }
            body.sidebar-collapsed .sidebar-brand {
                justify-content: flex-start !important;
                padding: 1rem 1.25rem !important;
                gap: .75rem !important;
            }
            body.sidebar-collapsed .sidebar-link {
                justify-content: flex-start !important;
                padding: .6rem 1.5rem !important;
                gap: .75rem !important;
            }
            body.sidebar-collapsed .sidebar-link i {
                margin: 0 !important;
                width: 1.2rem !important;
                font-size: 1rem !important;
            }
        }
    </style>
</head>
<body>
<script>
    if (document.documentElement.classList.contains('sidebar-collapsed-preload')) {
        document.body.classList.add('sidebar-collapsed');
        document.documentElement.classList.remove('sidebar-collapsed-preload');
    }
</script>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header">
        <a href="welcome.php" class="sidebar-brand" title="<?= defined('APP_FULL_NAME') ? APP_FULL_NAME : 'Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi' ?>">
            <div class="brand-icon-wrap" aria-label="<?= APP_NAME ?> Logo">
                <?php
                $headerLogo = __DIR__ . '/../assets/img/logo.svg';
                if (file_exists($headerLogo)) {
                    readfile($headerLogo);
                } else {
                    echo '<img src="assets/img/logo.svg?v=tree_' . APP_VERSION . '" alt="' . APP_NAME . ' Logo" width="32" height="32">';
                }
                ?>
            </div>
            <div class="brand-info">
                <span class="brand-title"><?= APP_NAME ?></span>
                <span class="brand-ver">v<?= APP_VERSION ?></span>
            </div>
        </a>
        <button type="button" class="sidebar-collapse-btn sidebar-collapse-trigger d-none d-md-flex" title="Minimize Sidebar" aria-label="Minimize Sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="sidebar-section">Main</div>
    <a href="welcome.php" class="sidebar-link <?= $current_page==='welcome'?'active':'' ?>" title="Beranda" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Beranda">
        <i class="bi bi-house-door"></i> <span class="link-text">Beranda</span>
    </a>
    <a href="dashboard.php" class="sidebar-link <?= $current_page==='dashboard'?'active':'' ?>" title="Dashboard Grafik" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard Grafik">
        <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard Grafik</span>
    </a>

    <div class="sidebar-section">RADIUS</div>
    <a href="users.php" class="sidebar-link <?= in_array($current_page, ['users','user-add','user-edit','user-import','user-batch'])?'active':'' ?>" title="Users" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Users">
        <i class="bi bi-people"></i> <span class="link-text">Users</span>
    </a>
    <a href="groups.php" class="sidebar-link <?= $current_page==='groups'?'active':'' ?>" title="Groups" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Groups">
        <i class="bi bi-collection"></i> <span class="link-text">Groups</span>
    </a>
    <a href="plans.php" class="sidebar-link <?= in_array($current_page, ['plans','plan-add','plan-edit'])?'active':'' ?>" title="Rate Plans" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Rate Plans">
        <i class="bi bi-speedometer"></i> <span class="link-text">Rate Plans</span>
    </a>
    <a href="nas.php" class="sidebar-link <?= in_array($current_page, ['nas','nas-add','nas-edit'])?'active':'' ?>" title="NAS Devices" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="NAS Devices">
        <i class="bi bi-hdd-network"></i> <span class="link-text">NAS Devices</span>
    </a>
    <?php if (dbTableExists('radippool')): ?>
    <a href="ippool.php" class="sidebar-link <?= $current_page==='ippool'?'active':'' ?>" title="IP Pools" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="IP Pools">
        <i class="bi bi-diagram-3"></i> <span class="link-text">IP Pools</span>
    </a>
    <?php endif; ?>
    <a href="expiry-check.php" class="sidebar-link <?= $current_page==='expiry-check'?'active':'' ?>" title="Expiry Warnings" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Expiry Warnings">
        <i class="bi bi-hourglass-split"></i> <span class="link-text">Expiry Warnings</span>
    </a>
    <a href="vouchers.php" class="sidebar-link <?= in_array($current_page, ['vouchers','voucher-generate'])?'active':'' ?>" title="Vouchers & Hotspot" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Vouchers & Hotspot">
        <i class="bi bi-ticket-perforated"></i> <span class="link-text">Vouchers & Hotspot</span>
    </a>

    <div class="sidebar-section">Reporting</div>
    <a href="reports.php" class="sidebar-link <?= $current_page==='reports'?'active':'' ?>" title="Reports" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Reports">
        <i class="bi bi-file-earmark-bar-graph"></i> <span class="link-text">Reports</span>
    </a>
    <a href="accounting.php" class="sidebar-link <?= $current_page==='accounting'?'active':'' ?>" title="Accounting" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Accounting">
        <i class="bi bi-clock-history"></i> <span class="link-text">Accounting</span>
    </a>
    <a href="sessions.php" class="sidebar-link <?= $current_page==='sessions'?'active':'' ?>" title="Active Sessions" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Active Sessions">
        <i class="bi bi-activity"></i> <span class="link-text">Active Sessions</span>
    </a>
    <a href="postauth.php" class="sidebar-link <?= $current_page==='postauth'?'active':'' ?>" title="Auth Log" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Auth Log">
        <i class="bi bi-shield-check"></i> <span class="link-text">Auth Log</span>
    </a>

    <div class="sidebar-section">System</div>
    <a href="settings.php" class="sidebar-link <?= $current_page==='settings'?'active':'' ?>" title="Configuration" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Configuration">
        <i class="bi bi-sliders"></i> <span class="link-text">Configuration</span>
    </a>
    <?php if (hasRole('superadmin')): ?>
    <a href="reauth.php" class="sidebar-link <?= $current_page==='reauth'?'active':'' ?>" title="Paksa Re-Login (Reset)" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Paksa Re-Login (Reset)">
        <i class="bi bi-arrow-repeat text-warning"></i> <span class="link-text">Paksa Re-Login</span>
    </a>
    <a href="operators.php" class="sidebar-link <?= $current_page==='operators'?'active':'' ?>" title="Operators & RBAC" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Operators & RBAC">
        <i class="bi bi-person-badge"></i> <span class="link-text">Operators & RBAC</span>
    </a>
    <a href="audit.php" class="sidebar-link <?= $current_page==='audit'?'active':'' ?>" title="Audit Log" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Audit Log">
        <i class="bi bi-journal-text"></i> <span class="link-text">Audit Log</span>
    </a>
    <?php endif; ?>
    <div class="sidebar-section">Documentation</div>
    <a href="docs/RadiusManager_User_Guide.pdf" target="_blank" class="sidebar-link" title="User Manual (PDF)" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="User Manual (PDF)">
        <i class="bi bi-file-earmark-pdf text-danger"></i> <span class="link-text">User Manual (PDF)</span>
    </a>
    <a href="logout.php" class="sidebar-link text-danger" title="Logout" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Logout">
        <i class="bi bi-box-arrow-left"></i> <span class="link-text">Logout</span>
    </a>

    <div class="sidebar-collapse-footer d-none d-md-block border-top border-secondary border-opacity-25 mt-2 py-1">
        <button type="button" class="sidebar-link sidebar-collapse-trigger w-100 border-0 bg-transparent text-secondary text-start py-2" title="Minimize / Expand Sidebar" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Minimize / Expand Sidebar">
            <i class="bi bi-chevron-bar-left collapse-footer-icon"></i> <span class="link-text">Minimize Sidebar</span>
        </button>
    </div>
</nav>

<!-- Main -->
<div id="main">
    <!-- Topbar -->
    <div id="topbar">
        <!-- Desktop Sidebar Collapse Toggle Button -->
        <button id="sidebarCollapseBtn" class="sidebar-collapse-trigger btn btn-sm btn-light border me-2 d-none d-md-inline-flex align-items-center justify-content-center shadow-none" 
                title="Minimize / Expand Sidebar" aria-label="Toggle Sidebar"
                style="width: 34px; height: 34px; border-radius: 8px; color: #475569; transition: all .15s ease;">
            <i class="bi bi-layout-sidebar-inset fs-6" style="transition: transform .2s ease;"></i>
        </button>
        <!-- Mobile Sidebar Toggle -->
        <button class="btn btn-sm btn-light border d-md-none me-2" onclick="document.getElementById('sidebar').classList.toggle('show')" aria-label="Toggle Mobile Menu" style="width: 34px; height: 34px; border-radius: 8px;">
            <i class="bi bi-list fs-5"></i>
        </button>
        <span class="text-muted small"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem"></i>Connected to FreeRADIUS</span>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="docs/RadiusManager_User_Guide.pdf" target="_blank" class="btn btn-outline-secondary btn-sm py-1 px-2.5 d-none d-md-inline-flex align-items-center gap-1.5" title="Download & View User Manual (PDF)">
                <i class="bi bi-file-earmark-pdf text-danger"></i> <span style="font-size:.78rem; font-weight:600;">User Manual (PDF)</span>
            </a>
            <a href="settings.php?tab=account" class="text-decoration-none small text-secondary d-flex align-items-center gap-2" title="Settings & Account">
                <i class="bi bi-person-circle fs-6"></i>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_user'] ?? 'admin') ?></span>
                <span class="badge <?= getAdminRole() === 'superadmin' ? 'bg-danger-subtle text-danger border border-danger-subtle' : (getAdminRole() === 'readonly' ? 'bg-secondary-subtle text-secondary border' : 'bg-primary-subtle text-primary border border-primary-subtle') ?> px-2 py-0.5" style="font-size:.65rem">
                    <?= ucfirst(htmlspecialchars(getAdminRole())) ?>
                </span>
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

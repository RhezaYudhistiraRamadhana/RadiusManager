<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> <?= isset($page_title) ? '- ' . $page_title : '' ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-w: 230px;
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
            overflow-y: auto; z-index: 1000; transition: .3s;
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
        .sidebar-brand {
            padding: 1.1rem 1.25rem; border-bottom: 1px solid #334155;
            color: #fff; font-size: 1.05rem; font-weight: 700;
            display: flex; align-items: center; gap: .65rem;
            text-decoration: none;
            transition: background .15s ease;
        }
        .sidebar-brand:hover { color: #fff; background: rgba(255,255,255,.03); }
        .sidebar-brand .brand-icon-wrap {
            width: 32px; height: 32px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .sidebar-brand:hover .brand-icon-wrap {
            transform: scale(1.06);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.5);
        }
        .sidebar-brand .brand-icon-wrap img,
        .sidebar-brand .brand-icon-wrap svg {
            width: 100%; height: 100%; display: block;
        }
        .sidebar-brand .brand-title {
            letter-spacing: -0.01em; white-space: nowrap; font-weight: 700;
        }
        .sidebar-brand .badge-ver {
            font-size: .6rem; background: var(--primary);
            padding: .15rem .45rem; border-radius: 4px;
            font-weight: 600; margin-left: auto;
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
        #main { margin-left: var(--sidebar-w); min-height: 100vh; }
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

        /* Page header */
        .page-header { margin-bottom: 1.5rem; }
        .page-header h4 { font-weight: 700; color: #1e293b; margin: 0; }
        .page-header p { color: #64748b; font-size: .875rem; margin: .2rem 0 0; }

        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #main { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-icon-wrap">
            <img src="assets/img/logo.svg" alt="<?= APP_NAME ?> Logo" width="32" height="32">
        </div>
        <span class="brand-title"><?= APP_NAME ?></span>
        <span class="badge-ver">v<?= APP_VERSION ?></span>
    </a>

    <div class="sidebar-section">Main</div>
    <a href="dashboard.php" class="sidebar-link <?= $current_page==='dashboard'?'active':'' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="sidebar-section">RADIUS</div>
    <a href="users.php" class="sidebar-link <?= $current_page==='users'||$current_page==='user-add'||$current_page==='user-edit'||$current_page==='user-import'?'active':'' ?>">
        <i class="bi bi-people"></i> Users
    </a>
    <a href="groups.php" class="sidebar-link <?= $current_page==='groups'?'active':'' ?>">
        <i class="bi bi-collection"></i> Groups
    </a>
    <a href="plans.php" class="sidebar-link <?= $current_page==='plans'?'active':'' ?>">
        <i class="bi bi-speedometer"></i> Rate Plans
    </a>
    <a href="nas.php" class="sidebar-link <?= $current_page==='nas'||$current_page==='nas-add'||$current_page==='nas-edit'?'active':'' ?>">
        <i class="bi bi-hdd-network"></i> NAS Devices
    </a>
    <?php if (dbTableExists('radippool')): ?>
    <a href="ippool.php" class="sidebar-link <?= $current_page==='ippool'?'active':'' ?>">
        <i class="bi bi-diagram-3"></i> IP Pools
    </a>
    <?php endif; ?>

    <div class="sidebar-section">Reporting</div>
    <a href="accounting.php" class="sidebar-link <?= $current_page==='accounting'?'active':'' ?>">
        <i class="bi bi-clock-history"></i> Accounting
    </a>
    <a href="sessions.php" class="sidebar-link <?= $current_page==='sessions'?'active':'' ?>">
        <i class="bi bi-activity"></i> Active Sessions
    </a>
    <a href="postauth.php" class="sidebar-link <?= $current_page==='postauth'?'active':'' ?>">
        <i class="bi bi-shield-check"></i> Auth Log
    </a>

    <div class="sidebar-section">System</div>
    <a href="audit.php" class="sidebar-link <?= $current_page==='audit'?'active':'' ?>">
        <i class="bi bi-journal-text"></i> Audit Log
    </a>
    <a href="settings.php" class="sidebar-link <?= $current_page==='settings'?'active':'' ?>">
        <i class="bi bi-gear"></i> Settings
    </a>
    <a href="logout.php" class="sidebar-link text-danger">
        <i class="bi bi-box-arrow-left"></i> Logout
    </a>
</nav>

<!-- Main -->
<div id="main">
    <!-- Topbar -->
    <div id="topbar">
        <button class="btn btn-sm btn-light d-md-none me-3" onclick="document.getElementById('sidebar').classList.toggle('show')">
            <i class="bi bi-list fs-5"></i>
        </button>
        <span class="text-muted small"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem"></i>Connected to FreeRADIUS</span>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="settings.php" class="text-decoration-none small text-secondary d-flex align-items-center gap-2" title="Settings & Account">
                <i class="bi bi-person-circle fs-6"></i>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_user'] ?? 'admin') ?></span>
                <?php if (!empty($_SESSION['admin_source'])): ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-1.5 py-0.5" style="font-size:.65rem">
                    <?= $_SESSION['admin_source'] === 'operators' ? 'Operator' : 'Admin' ?>
                </span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

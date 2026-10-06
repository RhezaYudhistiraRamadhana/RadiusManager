<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Configuration & Settings';
$db = getDB();
ensureAdminTables();
ensureSettingsTable();

$currentUser = getAdminUser();
$currentName = getAdminName();
$currentSource = getAdminSource();
$flash = getFlash();

// Active tab and sub-tab selection
$activeTab = $_GET['tab'] ?? 'general';
$activeSub = $_GET['sub'] ?? 'user';

// Fetch current user details from DB if available
$operatorData = null;
$rmAdminData  = null;

if ($currentSource === 'operators' && dbTableExists('operators')) {
    $operatorData = dbFetch("SELECT * FROM operators WHERE username = ?", [$currentUser]);
} elseif ($currentSource === 'rm_admins' && dbTableExists('rm_admins')) {
    $rmAdminData = dbFetch("SELECT * FROM rm_admins WHERE username = ?", [$currentUser]);
}

// ── Handle POST Requests ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');
    $tabRedirect = trim($_POST['tab_redirect'] ?? 'general');
    $subRedirect = trim($_POST['sub_redirect'] ?? 'user');

    // ── 1. Change Password ─────────────────────────────────────────────────────
    if ($action === 'change_password') {
        $currPass = trim($_POST['current_password'] ?? '');
        $newPass  = trim($_POST['new_password'] ?? '');
        $confPass = trim($_POST['confirm_password'] ?? '');

        if ($currPass === '' || $newPass === '' || $confPass === '') {
            flash('danger', 'Please fill in all password fields.');
            header("Location: settings.php?tab=account");
            exit;
        }

        $minLen = (int)getSetting('user_pass_min_len', 6);
        if (strlen($newPass) < $minLen) {
            flash('danger', "New password must be at least $minLen characters long.");
            header("Location: settings.php?tab=account");
            exit;
        }

        if ($newPass !== $confPass) {
            flash('danger', 'New password and confirmation do not match.');
            header("Location: settings.php?tab=account");
            exit;
        }

        // Verify current password according to auth source
        $verified = false;
        if ($currentSource === 'operators' && $operatorData) {
            $dbPass = $operatorData['password'];
            if (password_verify($currPass, $dbPass) || $currPass === $dbPass || md5($currPass) === $dbPass) {
                $verified = true;
            }
        } elseif ($currentSource === 'rm_admins' && $rmAdminData) {
            $dbPass = $rmAdminData['password'];
            if (password_verify($currPass, $dbPass) || $currPass === $dbPass) {
                $verified = true;
            }
        } elseif ($currentSource === 'config') {
            if (password_verify($currPass, APP_PASS) || $currPass === 'admin123') {
                $verified = true;
            }
        } else {
            if ($operatorData && (password_verify($currPass, $operatorData['password']) || $currPass === $operatorData['password'])) {
                $verified = true;
                $currentSource = 'operators';
            } elseif ($rmAdminData && (password_verify($currPass, $rmAdminData['password']) || $currPass === $rmAdminData['password'])) {
                $verified = true;
                $currentSource = 'rm_admins';
            }
        }

        if (!$verified) {
            flash('danger', 'Current password verification failed. Please check your password and try again.');
            header("Location: settings.php?tab=account");
            exit;
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);

        try {
            if ($currentSource === 'operators') {
                $db->exec("SET SESSION sql_mode = ''");
                $db->exec("ALTER TABLE `operators` MODIFY `password` VARCHAR(255) NOT NULL");
                $stmt = $db->prepare("UPDATE operators SET password = ?, updatedate = NOW() WHERE username = ?");
                $stmt->execute([$newHash, $currentUser]);
                flash('success', 'Password successfully updated for operator account ' . htmlspecialchars($currentUser) . '.');
            } else {
                $stmt = $db->prepare("INSERT INTO rm_admins (username, password, name)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE password = VALUES(password), updated_at = NOW()");
                $stmt->execute([$currentUser, $newHash, $currentName]);
                $_SESSION['admin_source'] = 'rm_admins';
                flash('success', 'Password successfully updated and securely stored in database.');
            }
            auditLog('security.change_password', $currentUser, "Administrator changed their password");
        } catch (Exception $e) {
            flash('danger', 'Database error updating password: ' . $e->getMessage());
        }

        header("Location: settings.php?tab=account");
        exit;
    }

    // ── 2. Update Profile Details ──────────────────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($currentSource === 'operators') {
            $fname = trim($_POST['firstname'] ?? '');
            $lname = trim($_POST['lastname'] ?? '');
            $dept  = trim($_POST['department'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            try {
                $stmt = $db->prepare("UPDATE operators SET firstname = ?, lastname = ?, email1 = ?, department = ?, phone1 = ?, updatedate = NOW() WHERE username = ?");
                $stmt->execute([$fname, $lname, $email, $dept, $phone, $currentUser]);
                $fullName = trim("$fname $lname") ?: $currentUser;
                $_SESSION['admin_name'] = $fullName;
                flash('success', 'Operator profile details updated successfully.');
            } catch (Exception $e) {
                flash('danger', 'Error updating profile: ' . $e->getMessage());
            }
        } elseif ($currentSource === 'rm_admins') {
            try {
                $stmt = $db->prepare("UPDATE rm_admins SET name = ?, email = ? WHERE username = ?");
                $stmt->execute([$name, $email, $currentUser]);
                if ($name) $_SESSION['admin_name'] = $name;
                flash('success', 'Admin profile updated successfully.');
            } catch (Exception $e) {
                flash('danger', 'Error updating profile: ' . $e->getMessage());
            }
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO rm_admins (username, password, name, email)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), updated_at = NOW()");
                $stmt->execute([$currentUser, APP_PASS, $name, $email]);
                $_SESSION['admin_source'] = 'rm_admins';
                if ($name) $_SESSION['admin_name'] = $name;
                flash('success', 'Profile created and stored in database.');
            } catch (Exception $e) {
                flash('danger', 'Error saving profile: ' . $e->getMessage());
            }
        }

        header("Location: settings.php?tab=account");
        exit;
    }

    // ── 3. Clear IP Lockouts (Superadmin only) ──────────────────────────────────
    if ($action === 'clear_lockout') {
        requireRole('superadmin');
        $targetIp = trim($_POST['ip_address'] ?? '');
        if ($targetIp === 'all') {
            if (dbTableExists('rm_login_attempts')) {
                dbQuery("TRUNCATE TABLE rm_login_attempts");
            }
            auditLog('security.unlock_all', 'all_ips', 'Superadmin cleared all IP login lockouts and attempt history');
            flash('success', 'All temporary IP lockouts and failed login records have been cleared.');
        } elseif ($targetIp !== '') {
            clearLoginAttempts($targetIp);
            auditLog('security.unlock_ip', $targetIp, "Superadmin unlocked IP address $targetIp");
            flash('success', "IP lockout for $targetIp has been successfully cleared.");
        }
        header("Location: settings.php?tab=maintenance");
        exit;
    }

    // ── 4. Global Settings: User Settings ──────────────────────────────────────
    if ($action === 'save_user_settings') {
        requireRole('superadmin');
        $allowCleartext = in_array($_POST['user_allow_cleartext'] ?? '', ['yes', 'no']) ? $_POST['user_allow_cleartext'] : 'yes';
        $randomChars    = trim($_POST['user_random_chars'] ?? 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $passMinLen     = max(4, min(32, (int)($_POST['user_pass_min_len'] ?? 8)));
        $passMaxLen     = max($passMinLen, min(64, (int)($_POST['user_pass_max_len'] ?? 14)));
        $defaultGroup   = trim($_POST['default_user_group'] ?? 'Default');
        $defaultExpiry  = max(0, (int)($_POST['default_expiry_days'] ?? 30));

        setSetting('user_allow_cleartext', $allowCleartext);
        setSetting('user_random_chars', $randomChars);
        setSetting('user_pass_min_len', (string)$passMinLen);
        setSetting('user_pass_max_len', (string)$passMaxLen);
        setSetting('default_user_group', $defaultGroup);
        setSetting('default_expiry_days', (string)$defaultExpiry);

        auditLog('config.user_settings', 'rm_settings', 'Updated User Settings (cleartext=' . $allowCleartext . ', min=' . $passMinLen . ', max=' . $passMaxLen . ')');
        flash('success', 'User Settings have been successfully saved.');
        header("Location: settings.php?tab=general&sub=user");
        exit;
    }

    // ── 5. Global Settings: Database Settings ──────────────────────────────────
    if ($action === 'save_db_settings') {
        requireRole('superadmin');
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? 'radius');
        $dbUser = trim($_POST['db_user'] ?? 'radius');
        $authPort = (int)($_POST['radius_auth_port'] ?? 1812);
        $acctPort = (int)($_POST['radius_acct_port'] ?? 1813);

        setSetting('db_host', $dbHost);
        setSetting('db_port', (string)$dbPort);
        setSetting('db_name', $dbName);
        setSetting('db_user', $dbUser);
        setSetting('radius_auth_port', (string)$authPort);
        setSetting('radius_acct_port', (string)$acctPort);

        auditLog('config.db_settings', 'rm_settings', "Updated Database Settings ($dbHost:$dbPort, FreeRADIUS ports $authPort/$acctPort)");
        flash('success', 'Database & FreeRADIUS port settings successfully updated.');
        header("Location: settings.php?tab=general&sub=database");
        exit;
    }

    // ── 6. Test FreeRADIUS Port ────────────────────────────────────────────────
    if ($action === 'test_radius_port') {
        $host = trim($_POST['test_host'] ?? getSetting('db_host', '127.0.0.1'));
        if ($host === 'localhost' || empty($host)) $host = '127.0.0.1';
        $port = (int)($_POST['test_port'] ?? 1812);

        $fp = @fsockopen("udp://$host", $port, $errno, $errstr, 2);
        if ($fp) {
            fclose($fp);
            flash('success', "FreeRADIUS UDP port test: Socket bound successfully to udp://$host:$port.");
        } else {
            flash('warning', "FreeRADIUS UDP port test: Could not establish socket to udp://$host:$port ($errstr)");
        }
        header("Location: settings.php?tab=general&sub=database");
        exit;
    }

    // ── 7. Global Settings: Language Settings ──────────────────────────────────
    if ($action === 'save_lang_settings') {
        requireRole('superadmin');
        $defaultLang = in_array($_POST['default_lang'] ?? '', ['en', 'id']) ? $_POST['default_lang'] : 'en';
        $charset     = trim($_POST['charset'] ?? 'UTF-8');
        $timezone    = trim($_POST['timezone'] ?? 'Asia/Jakarta');

        setSetting('default_lang', $defaultLang);
        setSetting('charset', $charset);
        setSetting('timezone', $timezone);

        auditLog('config.lang_settings', 'rm_settings', "Updated Language Settings (lang=$defaultLang, tz=$timezone)");
        flash('success', 'Language and localization settings saved.');
        header("Location: settings.php?tab=general&sub=language");
        exit;
    }

    // ── 8. Global Settings: Logging Settings ───────────────────────────────────
    if ($action === 'save_logging_settings') {
        requireRole('superadmin');
        $appEnv        = in_array($_POST['app_env'] ?? '', ['production', 'development']) ? $_POST['app_env'] : 'production';
        $logErrors     = ($_POST['log_errors'] ?? '1') === '1' ? '1' : '0';
        $radiusLogPath = trim($_POST['radius_log_path'] ?? '/var/log/freeradius/radius.log');

        setSetting('app_env', $appEnv);
        setSetting('log_errors', $logErrors);
        setSetting('radius_log_path', $radiusLogPath);

        auditLog('config.logging_settings', 'rm_settings', "Updated Logging Settings (env=$appEnv, log_errors=$logErrors)");
        flash('success', 'Logging settings saved successfully.');
        header("Location: settings.php?tab=general&sub=logging");
        exit;
    }

    // ── 9. Global Settings: Interface Settings ─────────────────────────────────
    if ($action === 'save_interface_settings') {
        requireRole('superadmin');
        $appName     = trim($_POST['app_name'] ?? 'RadiusManager');
        $rowsPerPage = max(5, min(200, (int)($_POST['rows_per_page'] ?? 20)));
        $dateFormat  = trim($_POST['date_format'] ?? 'Y-m-d H:i');
        $theme       = trim($_POST['default_theme'] ?? 'light');

        setSetting('app_name', $appName);
        setSetting('rows_per_page', (string)$rowsPerPage);
        setSetting('date_format', $dateFormat);
        setSetting('default_theme', $theme);

        auditLog('config.interface_settings', 'rm_settings', "Updated Interface Settings (app=$appName, rows=$rowsPerPage)");
        flash('success', 'Interface settings saved successfully.');
        header("Location: settings.php?tab=general&sub=interface");
        exit;
    }

    // ── 10. Global Settings: Message Settings ──────────────────────────────────
    if ($action === 'save_message_settings') {
        requireRole('superadmin');
        $welcomeMsg = trim($_POST['welcome_msg_template'] ?? '');
        $otpSubject = trim($_POST['otp_msg_subject'] ?? '');
        $portalHelp = trim($_POST['portal_help_notice'] ?? '');

        setSetting('welcome_msg_template', $welcomeMsg);
        setSetting('otp_msg_subject', $otpSubject);
        setSetting('portal_help_notice', $portalHelp);

        auditLog('config.message_settings', 'rm_settings', 'Updated notification and message templates');
        flash('success', 'Message templates saved successfully.');
        header("Location: settings.php?tab=general&sub=message");
        exit;
    }

    // ── 11. Global Settings: Recurring Tasks Settings ──────────────────────────
    if ($action === 'save_recurring_settings') {
        requireRole('superadmin');
        $staleDays = max(1, min(365, (int)($_POST['clean_stale_sessions_days'] ?? 30)));
        $autoOtpHours = max(1, min(72, (int)($_POST['auto_clean_otp_hours'] ?? 24)));

        setSetting('clean_stale_sessions_days', (string)$staleDays);
        setSetting('auto_clean_otp_hours', (string)$autoOtpHours);

        auditLog('config.recurring_settings', 'rm_settings', "Updated Recurring Task Settings (stale_days=$staleDays)");
        flash('success', 'Recurring task settings saved successfully.');
        header("Location: settings.php?tab=general&sub=recurring");
        exit;
    }

    // ── 12. Stale Sessions Cleanup Action ──────────────────────────────────────
    if ($action === 'clean_stale_sessions') {
        requireRole('superadmin');
        $days = (int)($_POST['stale_days'] ?? getSetting('clean_stale_sessions_days', 30));
        if ($days < 1) $days = 1;

        try {
            $stmt = $db->prepare("UPDATE radacct SET acctstoptime = NOW(), acctterminatecause = 'Admin-Reset' WHERE acctstoptime IS NULL AND acctstarttime < NOW() - INTERVAL ? DAY");
            $stmt->execute([$days]);
            $count = $stmt->rowCount();
            auditLog('maintenance.clean_stale_sessions', 'radacct', "Terminated $count stale accounting sessions older than $days days");
            flash('success', "Cleaned and terminated $count stale accounting session(s) older than $days days.");
        } catch (Exception $e) {
            flash('danger', 'Error cleaning stale sessions: ' . $e->getMessage());
        }

        header("Location: settings.php?tab=" . urlencode($tabRedirect) . "&sub=" . urlencode($subRedirect));
        exit;
    }

    // ── 13. Mail & SMTP Settings ───────────────────────────────────────────────
    if ($action === 'save_mail_settings') {
        requireRole('superadmin');
        $mailTransport = trim($_POST['mail_transport'] ?? 'smtp');
        $smtpHost      = trim($_POST['smtp_host'] ?? '');
        $smtpPort      = (int)($_POST['smtp_port'] ?? 587);
        $smtpSecure    = trim($_POST['smtp_secure'] ?? 'tls');
        $smtpUser      = trim($_POST['smtp_user'] ?? '');
        $smtpPass      = trim($_POST['smtp_pass'] ?? '');
        $mailFrom      = trim($_POST['mail_from'] ?? '');
        $mailFromName  = trim($_POST['mail_from_name'] ?? '');

        setSetting('mail_transport', $mailTransport);
        setSetting('smtp_host', $smtpHost);
        setSetting('smtp_port', (string)$smtpPort);
        setSetting('smtp_secure', $smtpSecure);
        setSetting('smtp_user', $smtpUser);
        if ($smtpPass !== '') {
            setSetting('smtp_pass', $smtpPass);
        }
        if ($mailFrom !== '') {
            setSetting('mail_from', $mailFrom);
        }
        if ($mailFromName !== '') {
            setSetting('mail_from_name', $mailFromName);
        }

        auditLog('config.mail_settings', 'rm_settings', "Updated Mail & SMTP Settings (host=$smtpHost, port=$smtpPort, from=$mailFrom)");
        flash('success', 'Mail and SMTP configuration saved successfully.');
        header("Location: settings.php?tab=mail");
        exit;
    }

    // ── 14. Send Test Email ────────────────────────────────────────────────────
    if ($action === 'test_mail') {
        requireRole('superadmin');
        $to = trim($_POST['test_recipient'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Please provide a valid destination email address for testing.');
        } else {
            $subject = '[' . APP_NAME . '] SMTP Configuration Verification';
            $html = '<div style="font-family:sans-serif;max-width:520px;margin:20px auto;padding:24px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;">'
                  . '<h2 style="color:#2563eb;margin-top:0;"><i style="font-style:normal;">&#x2705;</i> ' . htmlspecialchars(APP_NAME) . ' SMTP Test</h2>'
                  . '<p style="color:#334155;font-size:14px;line-height:1.6;">Congratulations! Your mail transport configuration is functioning properly. RadiusManager can successfully dispatch system notifications and password reset OTPs.</p>'
                  . '<div style="background:#f8fafc;padding:12px;border-radius:6px;font-size:12px;color:#64748b;margin-top:16px;">'
                  . '<strong>Sent at:</strong> ' . date('Y-m-d H:i:s') . '<br>'
                  . '<strong>Operator:</strong> ' . htmlspecialchars($currentUser) . '<br>'
                  . '<strong>Transport:</strong> ' . htmlspecialchars(getSetting('mail_transport', 'smtp')) . ' (' . htmlspecialchars(getSetting('smtp_host', 'localhost')) . ':' . htmlspecialchars(getSetting('smtp_port', 587)) . ')'
                  . '</div>'
                  . '</div>';
            $res = sendMailMessage($to, $subject, $html);
            if (!empty($res['success'])) {
                flash('success', "Test email sent successfully to $to!");
            } else {
                flash('danger', "Failed to send test email: " . ($res['error'] ?? 'Unknown mail transport error'));
            }
        }
        header("Location: settings.php?tab=mail");
        exit;
    }
}

// ── Read Live Settings & Diagnostics ───────────────────────────────────────────
$cfg = getAllSettings();

// System Information
$mysqlVersion = 'Unknown';
try {
    $mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn();
} catch (Exception $e) {}

// Scale Metrics (metadata query)
$countsMap = [];
try {
    $scaleRows = dbFetchAll("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('radpostauth', 'radacct', 'radcheck', 'nas')");
    foreach ($scaleRows as $r) {
        $countsMap[strtolower($r['TABLE_NAME'])] = (int)$r['TABLE_ROWS'];
    }
} catch (Exception $e) {}

$totalAuthLogs = $countsMap['radpostauth'] ?? 0;
$totalAcct     = $countsMap['radacct'] ?? 0;
$totalUsers    = $countsMap['radcheck'] ?? 0;
$totalNas      = $countsMap['nas'] ?? 0;

// Active Sessions Count (sessions with no acctstoptime)
$openSessionsCount = 0;
try {
    $openSessionsCount = (int)dbFetch("SELECT COUNT(*) as c FROM radacct WHERE acctstoptime IS NULL")['c'];
} catch (Exception $e) {}

// Active Lockouts & Login Security Data
$activeLockouts = [];
$recentAttempts = [];
if (dbTableExists('rm_login_attempts')) {
    $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $activeLockouts = dbFetchAll("
        SELECT ip_address, COUNT(*) as failed_count, MAX(attempted_at) as last_attempt, MIN(attempted_at) as first_attempt
        FROM rm_login_attempts
        WHERE attempted_at > ?
        GROUP BY ip_address
        HAVING failed_count >= 5
        ORDER BY last_attempt DESC
    ", [$window]);

    $recentAttempts = dbFetchAll("
        SELECT ip_address, username, attempted_at
        FROM rm_login_attempts
        ORDER BY id DESC
        LIMIT 10
    ");
}

// Operators / Admins List
$operatorsList = [];
if (dbTableExists('operators')) {
    $operatorsList = dbFetchAll("SELECT id, username, firstname, lastname, email1, department, lastlogin, creationdate FROM operators ORDER BY id ASC");
}

$rmAdminsList = [];
if (dbTableExists('rm_admins')) {
    $rmAdminsList = dbFetchAll("SELECT id, username, name, email, created_at, updated_at FROM rm_admins ORDER BY id ASC");
}

// Recent Mail Log Snippet
$mailLogFile = __DIR__ . '/storage/logs/mail.log';
$recentMailLogs = [];
if (file_exists($mailLogFile) && is_readable($mailLogFile)) {
    $lines = file($mailLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        $recentMailLogs = array_slice(array_reverse($lines), 0, 10);
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-sliders me-2 text-primary"></i>Configuration &amp; System Administration</h4>
        <p class="text-muted mb-0">Manage global FreeRADIUS policies, daloRADIUS compatibility settings, mail services, maintenance tools, and operator profiles</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
            <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?>
        </span>
        <a href="export.php?type=config" class="btn btn-outline-secondary btn-sm" title="Export Configuration Backup">
            <i class="bi bi-download me-1"></i>Export Config
        </a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm mb-4">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill fs-5' : 'exclamation-triangle-fill fs-5' ?>"></i>
        <span><?= htmlspecialchars($flash['msg']) ?></span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════════════
     TOP NAVIGATION TABS (daloRADIUS Full Configuration Parity)
     ══════════════════════════════════════════════════════════════════════════════ -->
<ul class="nav nav-pills nav-fill bg-white p-2 rounded-3 shadow-sm border mb-4" id="configMainTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold <?= $activeTab === 'general' ? 'active' : '' ?>" id="tab-general-btn" data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab">
            <i class="bi bi-gear-wide-connected me-1.5"></i>General
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold <?= $activeTab === 'mail' ? 'active' : '' ?>" id="tab-mail-btn" data-bs-toggle="pill" data-bs-target="#tab-mail" type="button" role="tab">
            <i class="bi bi-envelope-at me-1.5"></i>Mail
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold <?= $activeTab === 'maintenance' ? 'active' : '' ?>" id="tab-maintenance-btn" data-bs-toggle="pill" data-bs-target="#tab-maintenance" type="button" role="tab">
            <i class="bi bi-tools me-1.5"></i>Maintenance
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold <?= $activeTab === 'operators' ? 'active' : '' ?>" id="tab-operators-btn" data-bs-toggle="pill" data-bs-target="#tab-operators" type="button" role="tab">
            <i class="bi bi-people me-1.5"></i>Operators
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold <?= $activeTab === 'backup' ? 'active' : '' ?>" id="tab-backup-btn" data-bs-toggle="pill" data-bs-target="#tab-backup" type="button" role="tab">
            <i class="bi bi-database-down me-1.5"></i>Backup
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold <?= $activeTab === 'account' ? 'active' : '' ?>" id="tab-account-btn" data-bs-toggle="pill" data-bs-target="#tab-account" type="button" role="tab">
            <i class="bi bi-person-circle me-1.5"></i>My Account
        </button>
    </li>
</ul>

<!-- ══════════════════════════════════════════════════════════════════════════════
     TAB CONTENTS
     ══════════════════════════════════════════════════════════════════════════════ -->
<div class="tab-content" id="configMainTabContent">

    <!-- ──────────────────────────────────────────────────────────────────────────
         TAB 1: GENERAL (GLOBAL SETTINGS SUB-MENU)
         ────────────────────────────────────────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab === 'general' ? 'show active' : '' ?>" id="tab-general" role="tabpanel">
        <div class="row g-4">
            <!-- Left Sub-Navigation Sidebar (Matches daloRADIUS layout) -->
            <div class="col-lg-3 col-md-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-header bg-primary text-white py-2.5 px-3">
                        <span class="fw-bold text-uppercase" style="font-size: .78rem; letter-spacing: 0.5px;">
                            <i class="bi bi-globe me-1"></i>Global Settings
                        </span>
                    </div>
                    <div class="list-group list-group-flush" id="globalSettingsList" role="tablist">
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'user' ? 'active' : '' ?>" id="sub-user-link" data-bs-toggle="pill" href="#sub-user" role="tab">
                            <span><i class="bi bi-person-gear me-2"></i>User Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'database' ? 'active' : '' ?>" id="sub-db-link" data-bs-toggle="pill" href="#sub-database" role="tab">
                            <span><i class="bi bi-database me-2"></i>Database Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'language' ? 'active' : '' ?>" id="sub-lang-link" data-bs-toggle="pill" href="#sub-language" role="tab">
                            <span><i class="bi bi-translate me-2"></i>Language Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'logging' ? 'active' : '' ?>" id="sub-log-link" data-bs-toggle="pill" href="#sub-logging" role="tab">
                            <span><i class="bi bi-journal-text me-2"></i>Logging Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'interface' ? 'active' : '' ?>" id="sub-ui-link" data-bs-toggle="pill" href="#sub-interface" role="tab">
                            <span><i class="bi bi-display me-2"></i>Interface Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'message' ? 'active' : '' ?>" id="sub-msg-link" data-bs-toggle="pill" href="#sub-message" role="tab">
                            <span><i class="bi bi-chat-left-dots me-2"></i>Message Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                        <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 <?= $activeSub === 'recurring' ? 'active' : '' ?>" id="sub-rec-link" data-bs-toggle="pill" href="#sub-recurring" role="tab">
                            <span><i class="bi bi-arrow-repeat me-2"></i>Recurring Tasks Settings</span>
                            <i class="bi bi-chevron-right small opacity-75"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Content Area for Global Settings -->
            <div class="col-lg-9 col-md-8">
                <div class="tab-content" id="globalSettingsContent">

                    <!-- 1. USER SETTINGS (daloRADIUS User Settings Parity) -->
                    <div class="tab-pane fade <?= $activeSub === 'user' ? 'show active' : '' ?>" id="sub-user" role="tabpanel">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-person-gear text-primary me-2"></i>User Settings</h5>
                                <span class="badge bg-light text-muted border">General &gt; User Settings</span>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted small mb-4">Configure password security constraints, generation policies, and default profile attributes for RADIUS user management.</p>

                                <form method="POST" autocomplete="off">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_user_settings">

                                    <!-- Allow cleartext password in db -->
                                    <div class="row align-items-center mb-3.5 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Allow cleartext password in db</label>
                                            <div class="text-muted" style="font-size: .78rem;">Allow storing unhashed cleartext credentials in <code>radcheck</code> table (Cleartext-Password attribute)</div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="d-flex gap-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="user_allow_cleartext" id="cleartext_no" value="no" <?= ($cfg['user_allow_cleartext'] ?? 'yes') === 'no' ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold" for="cleartext_no">No (Secure Hashing)</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="user_allow_cleartext" id="cleartext_yes" value="yes" <?= ($cfg['user_allow_cleartext'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold" for="cleartext_yes">Yes (PAP Cleartext)</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Allowed Random Characters -->
                                    <div class="row align-items-center mb-3.5 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0" for="user_random_chars">Allowed Random Characters</label>
                                            <div class="text-muted" style="font-size: .78rem;">Characters used by password and voucher code generator</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="text" name="user_random_chars" id="user_random_chars" class="form-control font-monospace" style="font-size: .85rem;" value="<?= htmlspecialchars($cfg['user_random_chars'] ?? 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789') ?>" required>
                                            <div class="form-text text-muted" style="font-size:.72rem;">Default omits ambiguous characters like <code>0</code>, <code>O</code>, <code>1</code>, <code>l</code>, <code>I</code>.</div>
                                        </div>
                                    </div>

                                    <!-- Password min length -->
                                    <div class="row align-items-center mb-3.5 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0" for="user_pass_min_len">Password min length</label>
                                            <div class="text-muted" style="font-size: .78rem;">Minimum allowed length for subscriber passwords</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="number" name="user_pass_min_len" id="user_pass_min_len" class="form-control" style="max-width: 140px;" min="4" max="32" value="<?= (int)($cfg['user_pass_min_len'] ?? 8) ?>" required>
                                        </div>
                                    </div>

                                    <!-- Password max length -->
                                    <div class="row align-items-center mb-3.5 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0" for="user_pass_max_len">Password max length</label>
                                            <div class="text-muted" style="font-size: .78rem;">Maximum allowed length for generated or subscriber passwords</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="number" name="user_pass_max_len" id="user_pass_max_len" class="form-control" style="max-width: 140px;" min="8" max="64" value="<?= (int)($cfg['user_pass_max_len'] ?? 14) ?>" required>
                                        </div>
                                    </div>

                                    <!-- Default User Group -->
                                    <div class="row align-items-center mb-3.5 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0" for="default_user_group">Default User Group</label>
                                            <div class="text-muted" style="font-size: .78rem;">Default RADIUS profile applied to new accounts</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="text" name="default_user_group" id="default_user_group" class="form-control" style="max-width: 260px;" value="<?= htmlspecialchars($cfg['default_user_group'] ?? 'Default') ?>">
                                        </div>
                                    </div>

                                    <!-- Default Expiration (Days) -->
                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0" for="default_expiry_days">Default Expiry Period</label>
                                            <div class="text-muted" style="font-size: .78rem;">Days before newly created subscriber accounts expire (0 for unlimited)</div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="input-group" style="max-width: 180px;">
                                                <input type="number" name="default_expiry_days" id="default_expiry_days" class="form-control" min="0" value="<?= (int)($cfg['default_expiry_days'] ?? 30) ?>">
                                                <span class="input-group-text">days</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Apply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 2. DATABASE SETTINGS -->
                    <div class="tab-pane fade <?= $activeSub === 'database' ? 'show active' : '' ?>" id="sub-database" role="tabpanel">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-database text-primary me-2"></i>Database &amp; FreeRADIUS Port Settings</h5>
                                <span class="badge bg-light text-muted border">General &gt; Database Settings</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_db_settings">

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Database Host</label>
                                            <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($cfg['db_host'] ?? DB_HOST) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Database Port</label>
                                            <input type="number" name="db_port" class="form-control" value="<?= (int)($cfg['db_port'] ?? DB_PORT) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Database Name</label>
                                            <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($cfg['db_name'] ?? DB_NAME) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Database User</label>
                                            <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($cfg['db_user'] ?? DB_USER) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">FreeRADIUS Authentication Port</label>
                                            <input type="number" name="radius_auth_port" class="form-control" value="<?= (int)($cfg['radius_auth_port'] ?? 1812) ?>">
                                            <div class="form-text text-muted" style="font-size: .72rem;">Standard RFC 2865 authentication port is UDP 1812.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">FreeRADIUS Accounting Port</label>
                                            <input type="number" name="radius_acct_port" class="form-control" value="<?= (int)($cfg['radius_acct_port'] ?? 1813) ?>">
                                            <div class="form-text text-muted" style="font-size: .72rem;">Standard RFC 2866 accounting port is UDP 1813.</div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Save Database Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- FreeRADIUS Connectivity Check -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-activity text-info me-2"></i>FreeRADIUS Socket Diagnostic</h6>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted small">Verify that the FreeRADIUS UDP listening port is reachable on the server.</p>
                                <form method="POST" class="row g-3 align-items-end">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="test_radius_port">
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold">Target Server Host / IP</label>
                                        <input type="text" name="test_host" class="form-control form-control-sm" value="<?= htmlspecialchars($cfg['db_host'] ?? '127.0.0.1') ?>" placeholder="127.0.0.1">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">UDP Port</label>
                                        <input type="number" name="test_port" class="form-control form-control-sm" value="<?= (int)($cfg['radius_auth_port'] ?? 1812) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-outline-info btn-sm w-100">
                                            <i class="bi bi-broadcast-pin me-1"></i>Test UDP Socket
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 3. LANGUAGE SETTINGS -->
                    <div class="tab-pane fade <?= $activeSub === 'language' ? 'show active' : '' ?>" id="sub-language" role="tabpanel">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-translate text-primary me-2"></i>Language &amp; Localization Settings</h5>
                                <span class="badge bg-light text-muted border">General &gt; Language Settings</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_lang_settings">

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Default Interface Language</label>
                                            <div class="text-muted" style="font-size: .78rem;">Primary system language for admin panel and portal</div>
                                        </div>
                                        <div class="col-md-7">
                                            <select name="default_lang" class="form-select" style="max-width: 260px;">
                                                <option value="en" <?= ($cfg['default_lang'] ?? 'en') === 'en' ? 'selected' : '' ?>>English (en_US)</option>
                                                <option value="id" <?= ($cfg['default_lang'] ?? 'en') === 'id' ? 'selected' : '' ?>>Bahasa Indonesia (id_ID)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Character Encoding</label>
                                            <div class="text-muted" style="font-size: .78rem;">Standard document charset</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="text" name="charset" class="form-control" style="max-width: 260px;" value="<?= htmlspecialchars($cfg['charset'] ?? 'UTF-8') ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Server Timezone</label>
                                            <div class="text-muted" style="font-size: .78rem;">Timezone used for RADIUS accounting and audit timestamps</div>
                                        </div>
                                        <div class="col-md-7">
                                            <select name="timezone" class="form-select" style="max-width: 260px;">
                                                <?php
                                                $tzList = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC', 'America/New_York', 'Europe/London'];
                                                $curTz = $cfg['timezone'] ?? 'Asia/Jakarta';
                                                foreach ($tzList as $tz):
                                                ?>
                                                <option value="<?= $tz ?>" <?= $curTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Save Language Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 4. LOGGING SETTINGS -->
                    <div class="tab-pane fade <?= $activeSub === 'logging' ? 'show active' : '' ?>" id="sub-logging" role="tabpanel">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-journal-text text-primary me-2"></i>Logging Settings</h5>
                                <span class="badge bg-light text-muted border">General &gt; Logging Settings</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_logging_settings">

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Application Environment</label>
                                            <div class="text-muted" style="font-size: .78rem;">Production suppresses error display; Development displays detailed trace</div>
                                        </div>
                                        <div class="col-md-7">
                                            <select name="app_env" class="form-select" style="max-width: 260px;">
                                                <option value="production" <?= ($cfg['app_env'] ?? APP_ENV) === 'production' ? 'selected' : '' ?>>Production (Secure)</option>
                                                <option value="development" <?= ($cfg['app_env'] ?? APP_ENV) === 'development' ? 'selected' : '' ?>>Development (Debug Mode)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Log Errors to File</label>
                                            <div class="text-muted" style="font-size: .78rem;">Direct runtime PHP errors into <code>storage/logs/app.log</code></div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="log_errors" id="log_errors" value="1" <?= ($cfg['log_errors'] ?? '1') === '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-semibold" for="log_errors">Enable Error Logging</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">FreeRADIUS Server Log Path</label>
                                            <div class="text-muted" style="font-size: .78rem;">Path to FreeRADIUS main server log file</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="text" name="radius_log_path" class="form-control" value="<?= htmlspecialchars($cfg['radius_log_path'] ?? '/var/log/freeradius/radius.log') ?>">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Save Logging Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Application Log File Status -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text text-secondary me-2"></i>Application Log Status</h6>
                                <span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars(LOG_FILE) ?></span>
                            </div>
                            <div class="card-body p-3">
                                <?php if (file_exists(LOG_FILE)): ?>
                                    <div class="small text-muted mb-2">
                                        File size: <strong><?= number_format(filesize(LOG_FILE) / 1024, 2) ?> KB</strong> | Last modified: <?= date('Y-m-d H:i:s', filemtime(LOG_FILE)) ?>
                                    </div>
                                    <div class="bg-dark text-light p-3 rounded font-monospace small" style="max-height: 180px; overflow-y: auto; font-size: .75rem;">
                                        <?php
                                        $logSnippet = array_slice(file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -8);
                                        if (!empty($logSnippet)) {
                                            foreach ($logSnippet as $line) {
                                                echo htmlspecialchars($line) . "<br>";
                                            }
                                        } else {
                                            echo "<span class='text-secondary'>Log file is currently empty.</span>";
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light border small text-muted mb-0">
                                        Log file has not been created yet or directory is clean.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 5. INTERFACE SETTINGS -->
                    <div class="tab-pane fade <?= $activeSub === 'interface' ? 'show active' : '' ?>" id="sub-interface" role="tabpanel">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-display text-primary me-2"></i>Interface Settings</h5>
                                <span class="badge bg-light text-muted border">General &gt; Interface Settings</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_interface_settings">

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Application Title / Name</label>
                                            <div class="text-muted" style="font-size: .78rem;">Displayed in navbar, page titles, and exported reports</div>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="text" name="app_name" class="form-control" style="max-width: 280px;" value="<?= htmlspecialchars($cfg['app_name'] ?? APP_NAME) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Table Pagination (Rows Per Page)</label>
                                            <div class="text-muted" style="font-size: .78rem;">Default number of items displayed in list tables</div>
                                        </div>
                                        <div class="col-md-7">
                                            <select name="rows_per_page" class="form-select" style="max-width: 140px;">
                                                <?php foreach ([10, 20, 25, 50, 100] as $rCount): ?>
                                                <option value="<?= $rCount ?>" <?= ((int)($cfg['rows_per_page'] ?? ROWS_PER_PAGE) === $rCount) ? 'selected' : '' ?>><?= $rCount ?> rows</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Date &amp; Time Format</label>
                                            <div class="text-muted" style="font-size: .78rem;">PHP date formatting string for lists and logs</div>
                                        </div>
                                        <div class="col-md-7">
                                            <select name="date_format" class="form-select" style="max-width: 240px;">
                                                <option value="Y-m-d H:i" <?= ($cfg['date_format'] ?? 'Y-m-d H:i') === 'Y-m-d H:i' ? 'selected' : '' ?>>YYYY-MM-DD HH:MM (ISO)</option>
                                                <option value="d/m/Y H:i" <?= ($cfg['date_format'] ?? 'Y-m-d H:i') === 'd/m/Y H:i' ? 'selected' : '' ?>>DD/MM/YYYY HH:MM</option>
                                                <option value="m/d/Y h:i A" <?= ($cfg['date_format'] ?? 'Y-m-d H:i') === 'm/d/Y h:i A' ? 'selected' : '' ?>>MM/DD/YYYY 12-Hour</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold mb-0">Interface Color Scheme</label>
                                            <div class="text-muted" style="font-size: .78rem;">Modern UI accent color palette</div>
                                        </div>
                                        <div class="col-md-7">
                                            <select name="default_theme" class="form-select" style="max-width: 240px;">
                                                <option value="light" <?= ($cfg['default_theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Radius Blue (Default)</option>
                                                <option value="slate" <?= ($cfg['default_theme'] ?? 'light') === 'slate' ? 'selected' : '' ?>>Slate Dark Contrast</option>
                                                <option value="indigo" <?= ($cfg['default_theme'] ?? 'light') === 'indigo' ? 'selected' : '' ?>>Indigo Corporate</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Save Interface Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 6. MESSAGE SETTINGS -->
                    <div class="tab-pane fade <?= $activeSub === 'message' ? 'show active' : '' ?>" id="sub-message" role="tabpanel">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-chat-left-dots text-primary me-2"></i>Notification &amp; Message Settings</h5>
                                <span class="badge bg-light text-muted border">General &gt; Message Settings</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_message_settings">

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">New User Welcome Message Template</label>
                                        <textarea name="welcome_msg_template" class="form-control" rows="3"><?= htmlspecialchars($cfg['welcome_msg_template'] ?? 'Welcome to campus Wi-Fi! Your username is {username} and your initial password is {password}.') ?></textarea>
                                        <div class="form-text text-muted" style="font-size: .75rem;">Available placeholders: <code>{username}</code>, <code>{password}</code>, <code>{app_name}</code>, <code>{expiry}</code>.</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Self-Service OTP Email Subject</label>
                                        <input type="text" name="otp_msg_subject" class="form-control" value="<?= htmlspecialchars($cfg['otp_msg_subject'] ?? 'Campus Wi-Fi: Password Reset Verification Code') ?>">
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Self-Service Portal Assistance Notice</label>
                                        <textarea name="portal_help_notice" class="form-control" rows="2"><?= htmlspecialchars($cfg['portal_help_notice'] ?? 'To update your account password, please verify an OTP sent to your registered email or contact IT Support.') ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-end pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Save Message Templates
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 7. RECURRING TASKS SETTINGS -->
                    <div class="tab-pane fade <?= $activeSub === 'recurring' ? 'show active' : '' ?>" id="sub-recurring" role="tabpanel">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-arrow-repeat text-primary me-2"></i>Recurring Tasks &amp; Maintenance Automation</h5>
                                <span class="badge bg-light text-muted border">General &gt; Recurring Tasks</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="save_recurring_settings">

                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold mb-0">Stale Session Retention Threshold</label>
                                            <div class="text-muted" style="font-size: .78rem;">Accounting sessions with no Stop packet older than this limit are marked as stale</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group" style="max-width: 180px;">
                                                <input type="number" name="clean_stale_sessions_days" class="form-control" min="1" max="365" value="<?= (int)($cfg['clean_stale_sessions_days'] ?? 30) ?>">
                                                <span class="input-group-text">days</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold mb-0">Expired OTP Verification Codes Purge Window</label>
                                            <div class="text-muted" style="font-size: .78rem;">Purge temporary OTP attempts older than specified hours</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group" style="max-width: 180px;">
                                                <input type="number" name="auto_clean_otp_hours" class="form-control" min="1" max="72" value="<?= (int)($cfg['auto_clean_otp_hours'] ?? 24) ?>">
                                                <span class="input-group-text">hours</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end pt-3 border-top">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-1"></i>Save Recurring Policies
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Manual Trigger Card -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-play-circle text-warning me-2"></i>Run Stale Accounting Sessions Cleaner</h6>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                    <?= number_format($openSessionsCount) ?> Active/Lingering Sessions
                                </span>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted small">Execute an immediate cleanup job that stamps an <code>acctstoptime</code> and marks terminate cause as <code>Admin-Reset</code> on unclosed sessions older than the specified days.</p>
                                <form method="POST" class="d-flex align-items-center gap-3">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="clean_stale_sessions">
                                    <input type="hidden" name="tab_redirect" value="general">
                                    <input type="hidden" name="sub_redirect" value="recurring">
                                    <div class="input-group" style="max-width: 220px;">
                                        <span class="input-group-text small">Older than</span>
                                        <input type="number" name="stale_days" class="form-control" min="1" max="365" value="<?= (int)($cfg['clean_stale_sessions_days'] ?? 30) ?>">
                                        <span class="input-group-text small">days</span>
                                    </div>
                                    <button type="submit" class="btn btn-warning px-3" onclick="return confirm('Clean all stale sessions older than selected days?');">
                                        <i class="bi bi-broom me-1"></i>Clean Stale Sessions Now
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────────────────────────
         TAB 2: MAIL SETTINGS
         ────────────────────────────────────────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab === 'mail' ? 'show active' : '' ?>" id="tab-mail" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-envelope-at text-primary me-2"></i>Mail &amp; SMTP Configuration</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Mail Transport</span>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" autocomplete="off">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_mail_settings">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Mail Transport Engine</label>
                                    <select name="mail_transport" class="form-select">
                                        <option value="smtp" <?= ($cfg['mail_transport'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>Socket SMTP (Recommended)</option>
                                        <option value="mail" <?= ($cfg['mail_transport'] ?? 'smtp') === 'mail' ? 'selected' : '' ?>>PHP Native mail()</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">SMTP Host / Server</label>
                                    <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com / smtp.office365.com" value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">SMTP Port</label>
                                    <input type="number" name="smtp_port" class="form-control" placeholder="587" value="<?= (int)($cfg['smtp_port'] ?? 587) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Encryption Security</label>
                                    <select name="smtp_secure" class="form-select">
                                        <option value="tls" <?= strtolower($cfg['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (Port 587)</option>
                                        <option value="ssl" <?= strtolower($cfg['smtp_secure'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL / TLS (Port 465)</option>
                                        <option value="none" <?= strtolower($cfg['smtp_secure'] ?? 'tls') === 'none' ? 'selected' : '' ?>>None (Plaintext Port 25)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">SMTP Username</label>
                                    <input type="text" name="smtp_user" class="form-control" placeholder="user@domain.com" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">SMTP Password</label>
                                <input type="password" name="smtp_pass" class="form-control" placeholder="Enter new password or leave blank to keep unchanged">
                                <div class="form-text text-muted" style="font-size: .72rem;">Stored encrypted in database. Leave empty to preserve current credential.</div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">From Email Address</label>
                                    <input type="email" name="mail_from" class="form-control" placeholder="noreply@your-domain.edu" value="<?= htmlspecialchars($cfg['mail_from'] ?? MAIL_FROM) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">From Sender Name</label>
                                    <input type="text" name="mail_from_name" class="form-control" placeholder="RadiusManager Security" value="<?= htmlspecialchars($cfg['mail_from_name'] ?? MAIL_FROM_NAME) ?>">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-check-lg me-1"></i>Save Mail Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Live Mail Testing & Logs -->
            <div class="col-lg-5">
                <!-- Test Mail Sender -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-send-check text-success me-2"></i>Send Test Verification Email</h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small">Send a live test email through the active transport configuration to verify delivery.</p>
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="test_mail">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Recipient Email Address</label>
                                <input type="email" name="test_recipient" class="form-control" placeholder="admin@your-domain.edu" required>
                            </div>
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-send me-1"></i>Dispatch Test Email
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Mail Logs -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-secondary me-2"></i>Recent Mail Activity</h6>
                        <span class="badge bg-light text-muted border">storage/logs/mail.log</span>
                    </div>
                    <div class="card-body p-3">
                        <?php if (!empty($recentMailLogs)): ?>
                            <div class="bg-dark text-light p-2.5 rounded font-monospace small" style="max-height: 220px; overflow-y: auto; font-size: .72rem;">
                                <?php foreach ($recentMailLogs as $mLog): ?>
                                    <div><?= htmlspecialchars($mLog) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border small text-muted mb-0">
                                No email transactions logged yet. Dispatch a test message above to generate log records.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────────────────────────
         TAB 3: MAINTENANCE
         ────────────────────────────────────────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab === 'maintenance' ? 'show active' : '' ?>" id="tab-maintenance" role="tabpanel">
        <div class="row g-4">
            <!-- Left: Diagnostics & Scale -->
            <div class="col-lg-6">
                <!-- System Diagnostics -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <span class="fw-bold"><i class="bi bi-cpu-fill text-info me-2"></i>System Diagnostics &amp; Runtime Environment</span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-layers me-2 text-primary"></i>Application</span>
                                <span class="fw-bold"><?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-filetype-php me-2 text-primary"></i>PHP Runtime</span>
                                <span class="fw-bold">v<?= phpversion() ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-database me-2 text-primary"></i>Database Server</span>
                                <span class="fw-bold"><?= htmlspecialchars($mysqlVersion) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-hdd-network me-2 text-primary"></i>Database Name</span>
                                <span class="fw-bold"><code><?= htmlspecialchars(DB_NAME) ?></code> @ <?= htmlspecialchars(DB_HOST) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-clock-history me-2 text-primary"></i>Session Timeout</span>
                                <span class="fw-bold"><?= (int)getSetting('session_lifetime', SESSION_LIFETIME) ?>s (<?= round((int)getSetting('session_lifetime', SESSION_LIFETIME) / 60) ?> mins)</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-memory me-2 text-primary"></i>PHP Memory Limit</span>
                                <span class="fw-bold"><?= ini_get('memory_limit') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3">
                                <span class="text-muted"><i class="bi bi-hdd me-2 text-primary"></i>Web Server</span>
                                <span class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?>">
                                    <?= htmlspecialchars(explode(' ', $_SERVER['SERVER_SOFTWARE'] ?? 'Apache')[0]) ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Database Scale Status -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <span class="fw-bold"><i class="bi bi-bar-chart-fill text-success me-2"></i>FreeRADIUS Database Scale Metrics</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="p-2.5 bg-light rounded-3 border">
                                    <div class="text-muted" style="font-size:.72rem">Auth Log Records (radpostauth)</div>
                                    <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalAuthLogs) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2.5 bg-light rounded-3 border">
                                    <div class="text-muted" style="font-size:.72rem">Accounting Sessions (radacct)</div>
                                    <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalAcct) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2.5 bg-light rounded-3 border">
                                    <div class="text-muted" style="font-size:.72rem">Subscribers (radcheck)</div>
                                    <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalUsers) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2.5 bg-light rounded-3 border">
                                    <div class="text-muted" style="font-size:.72rem">NAS Hardware Devices</div>
                                    <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($totalNas) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Maintenance Actions & Lockout Security -->
            <div class="col-lg-6">
                <!-- Stale Session Cleaning Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold"><i class="bi bi-eraser-fill text-warning me-2"></i>Accounting Sessions Maintenance</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= number_format($openSessionsCount) ?> Open Sessions</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small">Close orphaned accounting sessions that never received a Radius-Acct-Stop packet from the NAS.</p>
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="clean_stale_sessions">
                            <input type="hidden" name="tab_redirect" value="maintenance">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-auto">
                                    <label class="form-label small fw-semibold mb-0">Threshold:</label>
                                </div>
                                <div class="col-auto">
                                    <div class="input-group input-group-sm" style="max-width: 140px;">
                                        <input type="number" name="stale_days" class="form-control" min="1" max="365" value="<?= (int)($cfg['clean_stale_sessions_days'] ?? 30) ?>">
                                        <span class="input-group-text">days</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-warning btn-sm px-3" onclick="return confirm('Clean all stale sessions older than threshold?');">
                                        <i class="bi bi-broom me-1"></i>Run Stale Cleanup
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Login Security & Brute Force Lockouts -->
                <?php if (hasRole('superadmin')): ?>
                <div class="card shadow-sm border-0 mb-4" id="security">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fw-bold"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Login Security &amp; IP Lockouts</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-2">Active</span>
                        </div>
                        <?php if (!empty($activeLockouts) || !empty($recentAttempts)): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Clear all failed login records and active IP lockouts?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="clear_lockout">
                            <input type="hidden" name="ip_address" value="all">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash3 me-1"></i>Clear All Lockouts
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-3">
                        <div class="small text-muted mb-3">
                            <i class="bi bi-info-circle me-1 text-primary"></i>Policy: Maximum <strong><?= (int)($cfg['bruteforce_max_attempts'] ?? 5) ?> failed login attempts</strong> within <strong><?= (int)($cfg['bruteforce_window_mins'] ?? 15) ?> minutes</strong> automatically locks the client IP.
                        </div>

                        <h6 class="fw-bold text-uppercase text-secondary mb-2" style="font-size:.75rem">Active IP Lockouts</h6>
                        <?php if (empty($activeLockouts)): ?>
                            <div class="p-2.5 bg-success-subtle text-success-emphasis rounded-3 border border-success-subtle d-flex align-items-center gap-2 small mb-3">
                                <i class="bi bi-check-circle-fill fs-6 text-success"></i>
                                <span>No active IP lockouts. All client connections are currently clear.</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Failed Attempts</th>
                                            <th>Last Attempt</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeLockouts as $l): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($l['ip_address']) ?></code></td>
                                            <td><span class="badge bg-danger"><?= (int)$l['failed_count'] ?> failures</span></td>
                                            <td class="text-muted"><?= date('H:i:s', strtotime($l['last_attempt'])) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="clear_lockout">
                                                    <input type="hidden" name="ip_address" value="<?= htmlspecialchars($l['ip_address']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:.75rem">
                                                        <i class="bi bi-unlock me-1"></i>Unlock
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($recentAttempts)): ?>
                        <details class="small text-muted">
                            <summary class="cursor-pointer fw-semibold text-secondary py-1" style="cursor:pointer">
                                <i class="bi bi-clock-history me-1"></i>View Recent Failed Attempts (Last 10)
                            </summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-striped align-middle mb-0" style="font-size:.78rem">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>IP Address</th>
                                            <th>Target Username</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAttempts as $a): ?>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars($a['attempted_at']) ?></td>
                                            <td><code><?= htmlspecialchars($a['ip_address']) ?></code></td>
                                            <td><strong><?= htmlspecialchars($a['username']) ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────────────────────────
         TAB 4: OPERATORS
         ────────────────────────────────────────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab === 'operators' ? 'show active' : '' ?>" id="tab-operators" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Operators &amp; Administrators Directory</h5>
                    <span class="text-muted small">Listing system operators from both <code>operators</code> and <code>rm_admins</code> tables</span>
                </div>
                <a href="operators.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Manage Operators
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Name / Profile</th>
                                <th>Auth Source</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Last Login</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operatorsList as $op): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-person-fill text-success me-1"></i>
                                    <strong><?= htmlspecialchars($op['username']) ?></strong>
                                    <?php if ($op['username'] === $currentUser): ?>
                                    <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.65rem">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(trim(($op['firstname'] ?? '') . ' ' . ($op['lastname'] ?? '')) ?: '—') ?></td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle">operators table</span></td>
                                <td><?= htmlspecialchars($op['department'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($op['email1'] ?: '—') ?></td>
                                <td class="small text-muted"><?= $op['lastlogin'] && $op['lastlogin'] !== '0000-00-00 00:00:00' ? htmlspecialchars($op['lastlogin']) : 'Never' ?></td>
                                <td class="small text-muted"><?= $op['creationdate'] && $op['creationdate'] !== '0000-00-00 00:00:00' ? htmlspecialchars($op['creationdate']) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php foreach ($rmAdminsList as $adm): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                                    <strong><?= htmlspecialchars($adm['username']) ?></strong>
                                    <?php if ($adm['username'] === $currentUser): ?>
                                    <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.65rem">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($adm['name'] ?: 'Administrator') ?></td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">rm_admins table</span></td>
                                <td>—</td>
                                <td><?= htmlspecialchars($adm['email'] ?: '—') ?></td>
                                <td class="small text-muted">—</td>
                                <td class="small text-muted"><?= htmlspecialchars($adm['created_at'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────────────────────────
         TAB 5: BACKUP
         ────────────────────────────────────────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab === 'backup' ? 'show active' : '' ?>" id="tab-backup" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-database-down text-primary me-2"></i>Database Backups &amp; Snapshots</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">Export schema DDL or system configuration snapshots for disaster recovery, migrations, and audit compliance.</p>

                        <div class="d-flex flex-column gap-3">
                            <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-filetype-sql text-primary me-2"></i>Database Schema DDL (.sql)</h6>
                                    <div class="text-muted" style="font-size: .78rem;">Full table structures for FreeRADIUS &amp; RadiusManager tables</div>
                                </div>
                                <a href="export.php?type=schema" class="btn btn-outline-primary btn-sm px-3">
                                    <i class="bi bi-download me-1"></i>Download SQL
                                </a>
                            </div>

                            <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-filetype-json text-info me-2"></i>Configuration Snapshot (.json)</h6>
                                    <div class="text-muted" style="font-size: .78rem;">JSON export of all persistent <code>rm_settings</code> key-values</div>
                                </div>
                                <a href="export.php?type=config" class="btn btn-outline-info btn-sm px-3">
                                    <i class="bi bi-download me-1"></i>Download JSON
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-table text-success me-2"></i>Entity Data Exports (CSV)</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">Download tabular CSV archives of core RADIUS and network entities directly from the database.</p>

                        <div class="d-flex flex-column gap-2.5">
                            <a href="export.php?type=users" class="btn btn-outline-secondary d-flex align-items-center justify-content-between py-2.5 px-3">
                                <span><i class="bi bi-people me-2 text-primary"></i>RADIUS Subscribers &amp; Profiles (radcheck, userinfo)</span>
                                <i class="bi bi-download"></i>
                            </a>
                            <a href="export.php?type=nas" class="btn btn-outline-secondary d-flex align-items-center justify-content-between py-2.5 px-3">
                                <span><i class="bi bi-router me-2 text-success"></i>NAS Network Devices &amp; Shared Secrets (nas)</span>
                                <i class="bi bi-download"></i>
                            </a>
                            <a href="export.php?type=accounting" class="btn btn-outline-secondary d-flex align-items-center justify-content-between py-2.5 px-3">
                                <span><i class="bi bi-receipt me-2 text-warning"></i>Accounting Sessions (radacct)</span>
                                <i class="bi bi-download"></i>
                            </a>
                            <a href="export.php?type=audit" class="btn btn-outline-secondary d-flex align-items-center justify-content-between py-2.5 px-3">
                                <span><i class="bi bi-shield-check me-2 text-danger"></i>Administrator Audit Log (rm_audit_log)</span>
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────────────────────────
         TAB 6: MY ACCOUNT (PROFILE & PASSWORD)
         ────────────────────────────────────────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab === 'account' ? 'show active' : '' ?>" id="tab-account" role="tabpanel">
        <div class="row g-4">
            <!-- Left: Change Password -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold"><i class="bi bi-key-fill text-warning me-2"></i>Change My Password</span>
                        <span class="badge bg-light text-muted border">User: <strong><?= htmlspecialchars($currentUser) ?></strong></span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-4 border">
                            <div class="stat-icon bg-white text-primary shadow-sm" style="width:42px;height:42px">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div>
                                <div class="small text-muted">Currently Signed In As:</div>
                                <div class="fw-bold text-dark fs-6">
                                    <?= htmlspecialchars($currentName) ?> (<code><?= htmlspecialchars($currentUser) ?></code>)
                                </div>
                            </div>
                            <div class="ms-auto text-end">
                                <span class="badge <?= $currentSource === 'operators' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle' ?> px-2.5 py-1">
                                    <?= $currentSource === 'operators' ? 'Database Operator' : ($currentSource === 'rm_admins' ? 'Database Admin' : 'Config Admin') ?>
                                </span>
                            </div>
                        </div>

                        <form method="POST" autocomplete="off">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Current Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="current_password" id="curr_pass" class="form-control" required placeholder="Enter current password">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePass('curr_pass', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-lock"></i></span>
                                        <input type="password" name="new_password" id="new_pass" class="form-control" required minlength="6" placeholder="At least 6 characters">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass('new_pass', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-check"></i></span>
                                        <input type="password" name="confirm_password" id="conf_pass" class="form-control" required minlength="6" placeholder="Re-type new password">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass('conf_pass', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <span class="small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Takes effect on next sign-in.
                                </span>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-check2-circle me-1"></i>Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Update Profile -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <span class="fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i>Personal Profile Details</span>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_profile">

                            <?php if ($currentSource === 'operators' && $operatorData): ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary">First Name</label>
                                        <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($operatorData['firstname'] ?? '') ?>" placeholder="First name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary">Last Name</label>
                                        <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($operatorData['lastname'] ?? '') ?>" placeholder="Last name">
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($operatorData['email1'] ?? '') ?>" placeholder="admin@example.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary">Department</label>
                                        <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($operatorData['department'] ?? '') ?>" placeholder="IT / Network Ops">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($operatorData['phone1'] ?? '') ?>" placeholder="+62 ...">
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary">Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentName) ?>" placeholder="Administrator Name" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($rmAdminData['email'] ?? '') ?>" placeholder="admin@your-domain.edu">
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-end pt-2 border-top">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-save me-1"></i>Save Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Preserve active tab & subtab across reload and history navigation
document.addEventListener('DOMContentLoaded', () => {
    // Check URL parameters first
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const subParam = urlParams.get('sub');

    if (tabParam) {
        const tabTrigger = document.querySelector(`#configMainTabs button[data-bs-target="#tab-${tabParam}"]`);
        if (tabTrigger) {
            new bootstrap.Tab(tabTrigger).show();
        }
    }

    if (subParam) {
        const subTrigger = document.querySelector(`#globalSettingsList a[href="#sub-${subParam}"]`);
        if (subTrigger) {
            new bootstrap.Tab(subTrigger).show();
        }
    }

    // Update URL hash / query on tab click without full page reload
    document.querySelectorAll('#configMainTabs button[data-bs-toggle="pill"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', (e) => {
            const targetId = e.target.getAttribute('data-bs-target').replace('#tab-', '');
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', targetId);
            window.history.replaceState({}, '', newUrl);
        });
    });

    document.querySelectorAll('#globalSettingsList a[data-bs-toggle="pill"]').forEach(link => {
        link.addEventListener('shown.bs.tab', (e) => {
            const targetId = e.target.getAttribute('href').replace('#sub-', '');
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('sub', targetId);
            window.history.replaceState({}, '', newUrl);
        });
    });
});
</script>

<?php
include __DIR__ . '/includes/footer.php';

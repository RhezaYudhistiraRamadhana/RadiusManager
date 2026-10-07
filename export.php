<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$db = getDB();
$type = $_GET['type'] ?? 'users';

// Set execution time limit for streaming large datasets
@set_time_limit(300);

// Disable output buffering to stream directly to client
while (ob_get_level() > 0) {
    ob_end_clean();
}

$nowStr = date('Ymd_His');

switch ($type) {
    // ══════════════════════════════════════════════════════════════════════
    // 1. EXPORT USERS
    // ══════════════════════════════════════════════════════════════════════
    case 'users':
        $search         = trim($_GET['q'] ?? '');
        $groupFilter    = trim($_GET['group'] ?? '');
        $statusFilter   = trim($_GET['status'] ?? ''); // '', 'active', 'disabled', 'online'
        $delimiter      = ($_GET['delimiter'] ?? ',') === ';' ? ';' : ',';
        $includeHeaders = !isset($_GET['headers']) || $_GET['headers'] === '1';
        $hasUserinfo    = dbTableExists('userinfo');
        $userInfoJoin   = $hasUserinfo ? "LEFT JOIN userinfo ui ON ui.username=rc.username" : "";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_users_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $colLabels = [
            'username'   => 'Username',
            'password'   => 'Password',
            'group'      => 'Groups / Plan',
            'status'     => 'Account Status',
            'online'     => 'Online Status',
            'firstname'  => 'First Name',
            'lastname'   => 'Last Name',
            'department' => 'Department',
            'email'      => 'Email',
            'static_ip'  => 'Static IP'
        ];

        // Process requested columns
        $requestedCols = $_GET['cols'] ?? [];
        if (!is_array($requestedCols) || empty($requestedCols)) {
            $selectedCols = ['username', 'password', 'group', 'status', 'online', 'firstname', 'lastname', 'department', 'email'];
        } else {
            $validKeys = array_keys($colLabels);
            $selectedCols = [];
            foreach ($requestedCols as $rc) {
                if (in_array($rc, $validKeys, true) && !in_array($rc, $selectedCols, true)) {
                    $selectedCols[] = $rc;
                }
            }
            if (!in_array('username', $selectedCols, true)) {
                array_unshift($selectedCols, 'username');
            }
        }

        // CSV Header row
        if ($includeHeaders) {
            $headerRow = [];
            foreach ($selectedCols as $ck) {
                $headerRow[] = $colLabels[$ck];
            }
            fputcsv($out, $headerRow, $delimiter);
        }

        // Step 1: Fetch matching usernames
        $whereClauses = [];
        $params = [];

        if ($groupFilter !== '') {
            $whereClauses[] = "rug.groupname = ?";
            $params[] = $groupFilter;
        }

        if ($search !== '') {
            $q = "%$search%";
            if ($hasUserinfo) {
                $whereClauses[] = "(rc.username LIKE ? OR ui.firstname LIKE ? OR ui.lastname LIKE ? OR ui.department LIKE ? OR ui.email LIKE ? OR rug.groupname LIKE ?)";
                $params = array_merge($params, [$q, $q, $q, $q, $q, $q]);
            } else {
                $whereClauses[] = "(rc.username LIKE ? OR rug.groupname LIKE ?)";
                $params = array_merge($params, [$q, $q]);
            }
        }

        if ($statusFilter === 'disabled') {
            $whereClauses[] = "EXISTS (SELECT 1 FROM radcheck rc_dis WHERE rc_dis.username = rc.username AND rc_dis.attribute = 'Auth-Type' AND rc_dis.value = 'Reject')";
        } elseif ($statusFilter === 'active') {
            $whereClauses[] = "NOT EXISTS (SELECT 1 FROM radcheck rc_act WHERE rc_act.username = rc.username AND rc_act.attribute = 'Auth-Type' AND rc_act.value = 'Reject')";
        } elseif ($statusFilter === 'online') {
            $whereClauses[] = "EXISTS (SELECT 1 FROM radacct ra_on WHERE ra_on.username = rc.username AND ra_on.acctstoptime IS NULL)";
        }

        if (!empty($whereClauses)) {
            $whereSql = "WHERE " . implode(' AND ', $whereClauses);
            $uStmt = $db->prepare("SELECT DISTINCT rc.username FROM radcheck rc $userInfoJoin LEFT JOIN radusergroup rug ON rug.username=rc.username $whereSql ORDER BY rc.username");
            $uStmt->execute($params);
            $usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $uStmt = $db->query("SELECT DISTINCT username FROM radcheck ORDER BY username");
            $usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!empty($usernames)) {
            $uiCols = $hasUserinfo ? "MAX(ui.firstname) AS firstname, MAX(ui.lastname) AS lastname, MAX(ui.department) AS department, MAX(ui.email) AS email," : "";

            // Chunk usernames in batches of 250 for streaming
            $chunks = array_chunk($usernames, 250);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                // Fast active session batch check
                $onlineStmt = $db->prepare("SELECT DISTINCT username FROM radacct WHERE username IN ($placeholders) AND acctstoptime IS NULL");
                $onlineStmt->execute($chunk);
                $onlineSet = array_flip($onlineStmt->fetchAll(PDO::FETCH_COLUMN));

                // Fetch details for chunk
                $stmt = $db->prepare("SELECT rc.username,
                    COALESCE(
                        MAX(CASE WHEN rc.attribute='Cleartext-Password' THEN rc.value END),
                        MAX(CASE WHEN rc.attribute='User-Password' THEN rc.value END)
                    ) AS password,
                    MAX(CASE WHEN rc.attribute='Auth-Type' AND rc.value='Reject' THEN 1 ELSE 0 END) AS is_disabled,
                    MAX(CASE WHEN rr.attribute='Framed-IP-Address' THEN rr.value END) AS static_ip,
                    GROUP_CONCAT(DISTINCT rug.groupname ORDER BY rug.priority SEPARATOR ', ') AS groupname,
                    $uiCols
                    rc.username AS dummy
                  FROM radcheck rc
                  $userInfoJoin
                  LEFT JOIN radusergroup rug ON rug.username=rc.username
                  LEFT JOIN radreply rr ON rr.username=rc.username
                  WHERE rc.username IN ($placeholders)
                  GROUP BY rc.username
                  ORDER BY rc.username");
                $stmt->execute($chunk);
                $rows = $stmt->fetchAll();

                foreach ($rows as $r) {
                    $isOnline = isset($onlineSet[$r['username']]) ? 'Online' : 'Offline';
                    $row = [];
                    foreach ($selectedCols as $ck) {
                        switch ($ck) {
                            case 'username':   $row[] = $r['username']; break;
                            case 'password':   $row[] = $r['password'] ?? ''; break;
                            case 'group':      $row[] = $r['groupname'] ?? ''; break;
                            case 'status':     $row[] = !empty($r['is_disabled']) ? 'Disabled' : 'Active'; break;
                            case 'online':     $row[] = $isOnline; break;
                            case 'firstname':  $row[] = $r['firstname'] ?? ''; break;
                            case 'lastname':   $row[] = $r['lastname'] ?? ''; break;
                            case 'department': $row[] = $r['department'] ?? ''; break;
                            case 'email':      $row[] = $r['email'] ?? ''; break;
                            case 'static_ip':  $row[] = $r['static_ip'] ?? ''; break;
                        }
                    }
                    fputcsv($out, $row, $delimiter);
                }
                fflush($out);
            }
        }

        fclose($out);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 2. EXPORT ACCOUNTING HISTORY
    // ══════════════════════════════════════════════════════════════════════
    case 'accounting':
        $search   = trim($_GET['q'] ?? '');
        $ipSearch = trim($_GET['ip'] ?? '');
        $dateFrom = trim($_GET['from'] ?? '');
        $dateTo   = trim($_GET['to'] ?? '');

        // Smart date fallback if not explicitly provided
        if (!$dateFrom) {
            $currMonthStart = date('Y-m-01');
            $hasCurrent = $db->query("SELECT 1 FROM radacct WHERE acctstarttime >= '$currMonthStart 00:00:00' LIMIT 1")->fetch();
            if ($hasCurrent) {
                $dateFrom = $currMonthStart;
                $dateTo   = date('Y-m-d');
            } else {
                $latest = $db->query("SELECT DATE(acctstarttime) d FROM radacct ORDER BY acctstarttime DESC LIMIT 1")->fetch();
                if ($latest && !empty($latest['d'])) {
                    $dateFrom = substr($latest['d'], 0, 7) . '-01';
                    $dateTo   = $latest['d'];
                } else {
                    $dateFrom = date('Y-m-01');
                    $dateTo   = date('Y-m-d');
                }
            }
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_accounting_' . $dateFrom . '_to_' . $dateTo . '_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            'Username',
            'NAS IP Address',
            'Framed IP Address',
            'Session Start Time',
            'Session Stop Time',
            'Session Duration (s)',
            'Duration Formatted',
            'Upload (Bytes)',
            'Upload Formatted',
            'Download (Bytes)',
            'Download Formatted',
            'Total (Bytes)',
            'Total Formatted',
            'Termination Cause'
        ]);

        $where = "WHERE acctstarttime >= :from_dt AND acctstarttime <= :to_dt";
        $params = [
            ':from_dt' => "$dateFrom 00:00:00",
            ':to_dt'   => "$dateTo 23:59:59"
        ];

        if ($search) {
            $where .= " AND username LIKE :q";
            $params[':q'] = "%$search%";
        }

        if ($ipSearch) {
            $where .= " AND framedipaddress LIKE :ip";
            $params[':ip'] = "%$ipSearch%";
        }

        // Safety cap of 50,000 rows to ensure fast streaming without browser timeout
        $maxRows = 50000;
        $stmt = $db->prepare("SELECT username, nasipaddress, framedipaddress,
            acctstarttime, acctstoptime, acctsessiontime,
            acctinputoctets, acctoutputoctets, acctterminatecause
          FROM radacct $where
          ORDER BY acctstarttime DESC LIMIT $maxRows");
        $stmt->execute($params);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inBytes  = (int)($r['acctinputoctets'] ?? 0);
            $outBytes = (int)($r['acctoutputoctets'] ?? 0);
            $totBytes = $inBytes + $outBytes;
            $sec      = (int)($r['acctsessiontime'] ?? 0);

            fputcsv($out, [
                $r['username'],
                $r['nasipaddress'] ?? '',
                $r['framedipaddress'] ?? '',
                $r['acctstarttime'] ?? '',
                $r['acctstoptime'] ?? 'Active',
                $sec,
                formatDuration($sec),
                $inBytes,
                formatBytes($inBytes),
                $outBytes,
                formatBytes($outBytes),
                $totBytes,
                formatBytes($totBytes),
                $r['acctterminatecause'] ?? ''
            ]);
        }

        fclose($out);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 3. EXPORT AUTH LOG (radpostauth)
    // ══════════════════════════════════════════════════════════════════════
    case 'postauth':
        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $dateFrom = trim($_GET['from'] ?? '');
        $dateTo   = trim($_GET['to'] ?? '');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_authlog_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            'Log ID',
            'Username',
            'Password / Token',
            'Reply Status',
            'Authentication Date'
        ]);

        $where = [];
        $params = [];

        if ($status === 'Access-Accept' || $status === 'Access-Reject') {
            $where[] = "reply = :status";
            $params[':status'] = $status;
        }

        if ($search) {
            $where[] = "username LIKE :q";
            $params[':q'] = "%$search%";
        }

        if ($dateFrom) {
            $where[] = "authdate >= :from_dt";
            $params[':from_dt'] = "$dateFrom 00:00:00";
        }

        if ($dateTo) {
            $where[] = "authdate <= :to_dt";
            $params[':to_dt'] = "$dateTo 23:59:59";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        // On 24M-row radpostauth table, streaming uses index scan capped at 30,000 records
        $maxLogs = 30000;
        $stmt = $db->prepare("SELECT id, username, pass, reply, authdate 
            FROM radpostauth $whereSql 
            ORDER BY id DESC LIMIT $maxLogs");
        $stmt->execute($params);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'],
                $r['username'],
                $r['pass'] ?? '',
                $r['reply'],
                $r['authdate']
            ]);
        }

        fclose($out);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 4. EXPORT HOTSPOT VOUCHERS
    // ══════════════════════════════════════════════════════════════════════
    case 'vouchers':
        $search      = trim($_GET['q'] ?? '');
        $batchFilter = trim($_GET['batch'] ?? '');
        $statusFilter= trim($_GET['status'] ?? '');
        $delimiter   = ($_GET['delimiter'] ?? ',') === ';' ? ';' : ',';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_vouchers_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            'ID',
            'Batch Name',
            'Username',
            'Password',
            'Plan / Profile',
            'Status',
            'Created By',
            'Created At',
            'Used At'
        ], $delimiter);

        if (dbTableExists('rm_vouchers')) {
            $where = ["1=1"];
            $params = [];

            if ($search !== '') {
                $where[] = "(v.username LIKE ? OR v.batch_name LIKE ? OR v.created_by LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($batchFilter !== '') {
                $where[] = "v.batch_name = ?";
                $params[] = $batchFilter;
            }

            if (in_array($statusFilter, ['unused', 'active', 'expired'], true)) {
                $where[] = "v.status = ?";
                $params[] = $statusFilter;
            }

            $whereSql = "WHERE " . implode(' AND ', $where);
            $planJoin = dbTableExists('rm_plans') ? "LEFT JOIN rm_plans p ON p.id = v.plan_id" : "";
            $planCol  = dbTableExists('rm_plans') ? "COALESCE(p.name, '') AS plan_name" : "'' AS plan_name";

            $stmt = $db->prepare("SELECT v.id, v.batch_name, v.username, v.password, $planCol, v.status, v.created_by, v.created_at, v.used_at
                                  FROM rm_vouchers v
                                  $planJoin
                                  $whereSql
                                  ORDER BY v.id DESC LIMIT 50000");
            $stmt->execute($params);

            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, [
                    $r['id'],
                    $r['batch_name'],
                    $r['username'],
                    $r['password'],
                    $r['plan_name'],
                    ucfirst($r['status']),
                    $r['created_by'] ?? '',
                    $r['created_at'] ?? '',
                    $r['used_at'] ?? ''
                ], $delimiter);
            }
        }

        fclose($out);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 5. EXPORT AUDIT LOG
    // ══════════════════════════════════════════════════════════════════════
    case 'audit':
        $operator = trim($_GET['operator'] ?? '');
        $action   = trim($_GET['action'] ?? '');
        $search   = trim($_GET['q'] ?? '');
        $dateFrom = trim($_GET['from'] ?? '');
        $dateTo   = trim($_GET['to'] ?? '');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_audit_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            'ID',
            'Timestamp',
            'Operator',
            'Action',
            'Target',
            'Detail',
            'IP Address'
        ]);

        $where = [];
        $params = [];

        if ($operator) {
            $where[] = "operator = :op";
            $params[':op'] = $operator;
        }
        if ($action) {
            $where[] = "action = :action";
            $params[':action'] = $action;
        }
        if ($search) {
            $where[] = "(target LIKE :search OR detail LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($dateFrom) {
            $where[] = "created_at >= :from_dt";
            $params[':from_dt'] = "$dateFrom 00:00:00";
        }
        if ($dateTo) {
            $where[] = "created_at <= :to_dt";
            $params[':to_dt'] = "$dateTo 23:59:59";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        $stmt = $db->prepare("SELECT id, created_at, operator, action, target, detail, ip_address 
            FROM rm_audit_log $whereSql 
            ORDER BY id DESC LIMIT 50000");
        $stmt->execute($params);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'],
                $r['created_at'],
                $r['operator'],
                $r['action'],
                $r['target'],
                $r['detail'],
                $r['ip_address']
            ]);
        }

        fclose($out);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 5. EXPORT NAS DEVICES
    // ══════════════════════════════════════════════════════════════════════
    case 'nas':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_nas_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'NAS IP / Name', 'Shortname', 'Type', 'Ports', 'Secret', 'Server', 'Community', 'Description']);

        $stmt = $db->query("SELECT id, nasname, shortname, type, ports, secret, server, community, description FROM nas ORDER BY id ASC");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'],
                $r['nasname'],
                $r['shortname'],
                $r['type'],
                $r['ports'],
                $r['secret'],
                $r['server'],
                $r['community'],
                $r['description']
            ]);
        }
        fclose($out);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 6. EXPORT CONFIGURATION SNAPSHOT (JSON)
    // ══════════════════════════════════════════════════════════════════════
    case 'config':
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="cendana_config_' . $nowStr . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $settings = getAllSettings();
        if (isset($settings['smtp_pass']) && $settings['smtp_pass'] !== '') {
            $settings['smtp_pass'] = '********';
        }
        $snapshot = [
            'app' => APP_NAME,
            'version' => APP_VERSION,
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => getAdminUser(),
            'settings' => $settings
        ];
        echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;

    // ══════════════════════════════════════════════════════════════════════
    // 7. EXPORT DATABASE SCHEMA DDL (.sql)
    // ══════════════════════════════════════════════════════════════════════
    case 'schema':
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_schema_' . $nowStr . '.sql"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "-- ============================================================\n";
        echo "-- " . APP_NAME . " / FreeRADIUS Database Schema Backup\n";
        echo "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Database: " . DB_NAME . " @ " . DB_HOST . "\n";
        echo "-- ============================================================\n\n";

        $tables = ['radcheck', 'radreply', 'radgroupcheck', 'radgroupreply', 'usergroup', 'radacct', 'radpostauth', 'nas', 'userinfo', 'operators', 'rm_admins', 'rm_settings', 'rm_plans', 'rm_vouchers', 'rm_login_attempts', 'rm_audit_log'];
        foreach ($tables as $tbl) {
            if (dbTableExists($tbl)) {
                $createRow = dbFetch("SHOW CREATE TABLE `$tbl`");
                if (!empty($createRow['Create Table'])) {
                    echo "-- ── Table structure for `$tbl` ──\n";
                    echo "DROP TABLE IF EXISTS `$tbl`;\n";
                    echo $createRow['Create Table'] . ";\n\n";
                }
            }
        }
        exit;

    default:
        flash('Invalid export type requested.', 'danger');
        header('Location: dashboard.php');
        exit;
}


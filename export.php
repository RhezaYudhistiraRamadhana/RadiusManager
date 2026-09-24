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
        $search = trim($_GET['q'] ?? '');
        $hasUserinfo = dbTableExists('userinfo');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radius_users_' . $nowStr . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // CSV Header
        fputcsv($out, [
            'Username',
            'Password',
            'Groups',
            'First Name',
            'Last Name',
            'Department',
            'Email',
            'Online Status'
        ]);

        // Step 1: Fetch matching usernames
        if ($search) {
            $q = "%$search%";
            if ($hasUserinfo) {
                $uStmt = $db->prepare("SELECT DISTINCT rc.username FROM radcheck rc
                    LEFT JOIN userinfo ui ON ui.username=rc.username
                    WHERE rc.username LIKE ? OR ui.firstname LIKE ? OR ui.lastname LIKE ? OR ui.department LIKE ? OR ui.email LIKE ?
                    ORDER BY rc.username");
                $uStmt->execute([$q, $q, $q, $q, $q]);
            } else {
                $uStmt = $db->prepare("SELECT DISTINCT username FROM radcheck WHERE username LIKE ? ORDER BY username");
                $uStmt->execute([$q]);
            }
            $usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $uStmt = $db->query("SELECT DISTINCT username FROM radcheck ORDER BY username");
            $usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!empty($usernames)) {
            $uiCols = $hasUserinfo ? "MAX(ui.firstname) AS firstname, MAX(ui.lastname) AS lastname, MAX(ui.department) AS department, MAX(ui.email) AS email," : "";
            $userInfoJoin = $hasUserinfo ? "LEFT JOIN userinfo ui ON ui.username=rc.username" : "";

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
                    GROUP_CONCAT(DISTINCT rug.groupname ORDER BY rug.priority SEPARATOR ', ') AS groupname,
                    $uiCols
                    rc.username AS dummy
                  FROM radcheck rc
                  $userInfoJoin
                  LEFT JOIN radusergroup rug ON rug.username=rc.username
                  WHERE rc.username IN ($placeholders)
                  GROUP BY rc.username
                  ORDER BY rc.username");
                $stmt->execute($chunk);
                $rows = $stmt->fetchAll();

                foreach ($rows as $r) {
                    $isOnline = isset($onlineSet[$r['username']]) ? 'Online' : 'Offline';
                    fputcsv($out, [
                        $r['username'],
                        $r['password'] ?? '',
                        $r['groupname'] ?? '',
                        $r['firstname'] ?? '',
                        $r['lastname'] ?? '',
                        $r['department'] ?? '',
                        $r['email'] ?? '',
                        $isOnline
                    ]);
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

    default:
        flash('Invalid export type requested.', 'danger');
        header('Location: dashboard.php');
        exit;
}


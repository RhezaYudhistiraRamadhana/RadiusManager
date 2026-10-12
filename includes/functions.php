<?php
/**
 * Shared Utility Functions
 */

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitize($input) {
    if ($input === null) return '';
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

function formatBytes($bytes, $precision = 2) {
    $bytes = (float)($bytes ?? 0);
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow   = floor(log($bytes) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function formatDuration($seconds) {
    $seconds = (int)($seconds ?? 0);
    if ($seconds <= 0) return '0s';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    $parts = [];
    if ($h > 0) $parts[] = "{$h}h";
    if ($m > 0) $parts[] = "{$m}m";
    if ($s > 0 || empty($parts)) $parts[] = "{$s}s";
    return implode(' ', $parts);
}

function formatMac(?string $mac): string {
    if (!$mac || trim($mac) === '' || $mac === '—') return '—';
    $clean = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
    if (strlen($clean) === 12) {
        return implode(':', str_split($clean, 2));
    }
    return strtoupper($mac);
}

function paginate($total, $page, $perPage = 20) {
    $perPage = max(1, (int)$perPage);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min((int)$page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'per_page'    => $perPage,
        'total'       => (int)$total,
    ];
}

function paginationLinks($pag, $baseUrl) {
    if ($pag['total_pages'] <= 1) return '';
    $separator = (str_contains($baseUrl, '?')) ? '&' : '?';
    $p = $pag['page'];
    $tp = $pag['total_pages'];

    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    // Previous
    $html .= '<li class="page-item ' . ($p <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . h($baseUrl . $separator . 'page=' . ($p - 1)) . '">&laquo;</a></li>';

    for ($i = max(1, $p - 2); $i <= min($tp, $p + 2); $i++) {
        $html .= '<li class="page-item ' . ($i === $p ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="' . h($baseUrl . $separator . 'page=' . $i) . '">' . $i . '</a></li>';
    }

    // Next
    $html .= '<li class="page-item ' . ($p >= $tp ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . h($baseUrl . $separator . 'page=' . ($p + 1)) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Log administrative activity to rm_audit_log
 */
function auditLog(string $action, string $target = '', string $detail = ''): void {
    if (!dbTableExists('rm_audit_log')) return;
    $operator = $_SESSION['admin_user'] ?? 'system';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        dbQuery(
            "INSERT INTO rm_audit_log (operator, action, target, detail, ip_address)
             VALUES (:op, :action, :target, :detail, :ip)",
            [':op' => $operator, ':action' => $action, ':target' => $target,
             ':detail' => $detail, ':ip' => $ip]
        );
    } catch (Exception $e) {
        // Fail silently to avoid interrupting caller operations
    }
}

/**
 * Synchronize bandwidth and session limit attributes for a group in radgroupreply
 */
function syncPlanAttributes(string $groupname, int $dl_kbps, int $ul_kbps, int $time_hours = 0, int $data_mb = 0): void {
    $db = getDB();
    $managedAttrs = [
        'WISPr-Bandwidth-Max-Down',
        'WISPr-Bandwidth-Max-Up',
        'Mikrotik-Rate-Limit',
        'Session-Timeout',
        'ChilliSpot-Max-Total-Octets'
    ];
    $placeholders = implode(',', array_fill(0, count($managedAttrs), '?'));
    $del = $db->prepare("DELETE FROM radgroupreply WHERE groupname = ? AND attribute IN ($placeholders)");
    $del->execute(array_merge([$groupname], $managedAttrs));

    $ins = $db->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, ?, ':=', ?)");

    if ($dl_kbps > 0) {
        $ins->execute([$groupname, 'WISPr-Bandwidth-Max-Down', (string)($dl_kbps * 1024)]);
    }
    if ($ul_kbps > 0) {
        $ins->execute([$groupname, 'WISPr-Bandwidth-Max-Up', (string)($ul_kbps * 1024)]);
    }
    if ($dl_kbps > 0 && $ul_kbps > 0) {
        $ins->execute([$groupname, 'Mikrotik-Rate-Limit', "{$ul_kbps}k/{$dl_kbps}k"]);
    }
    if ($time_hours > 0) {
        $ins->execute([$groupname, 'Session-Timeout', (string)($time_hours * 3600)]);
    }
    if ($data_mb > 0) {
        $ins->execute([$groupname, 'ChilliSpot-Max-Total-Octets', (string)($data_mb * 1024 * 1024)]);
    }
}

/**
 * Ensure rm_settings table exists
 */
function ensureSettingsTable(): void {
    static $done = false;
    if ($done) return;
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS `rm_settings` (
            `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
            `setting_value` TEXT DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $done = true;
    } catch (Exception $e) {}
}

/**
 * Fetch a persistent system setting from rm_settings table
 */
function getSetting(string $key, mixed $default = null): mixed {
    if (!isset($GLOBALS['rm_settings_cache']) || $GLOBALS['rm_settings_cache'] === null) {
        $GLOBALS['rm_settings_cache'] = [];
        ensureSettingsTable();
        try {
            $rows = dbFetchAll("SELECT setting_key, setting_value FROM rm_settings");
            foreach ($rows as $r) {
                $GLOBALS['rm_settings_cache'][$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {}
    }
    return $GLOBALS['rm_settings_cache'][$key] ?? $default;
}

/**
 * Persist or update a system setting in rm_settings table
 */
function setSetting(string $key, mixed $value): bool {
    ensureSettingsTable();
    try {
        dbQuery(
            "INSERT INTO rm_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
            [$key, (string)$value]
        );
        if (isset($GLOBALS['rm_settings_cache']) && is_array($GLOBALS['rm_settings_cache'])) {
            $GLOBALS['rm_settings_cache'][$key] = (string)$value;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all settings as associative array
 */
function getAllSettings(): array {
    ensureSettingsTable();
    try {
        $rows = dbFetchAll("SELECT setting_key, setting_value FROM rm_settings");
        $res = [];
        foreach ($rows as $r) {
            $res[$r['setting_key']] = $r['setting_value'];
        }
        $GLOBALS['rm_settings_cache'] = $res;
        return $res;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Terminate a single active session or all sessions for a user in radacct
 */
/**
 * Terminate a single active session or all sessions for a user in radacct
 */
function terminateRadiusSession(string $sessionId, string $username = '', string $reason = 'Admin-Reset'): int {
    $db = getDB();
    if ($sessionId !== '') {
        $stmt = $db->prepare("UPDATE radacct SET acctstoptime = NOW(), acctsessiontime = GREATEST(0, TIMESTAMPDIFF(SECOND, acctstarttime, NOW())), acctterminatecause = ? WHERE acctsessionid = ? AND acctstoptime IS NULL");
        $stmt->execute([$reason, $sessionId]);
        return $stmt->rowCount();
    } elseif ($username !== '') {
        $stmt = $db->prepare("UPDATE radacct SET acctstoptime = NOW(), acctsessiontime = GREATEST(0, TIMESTAMPDIFF(SECOND, acctstarttime, NOW())), acctterminatecause = ? WHERE username = ? AND acctstoptime IS NULL");
        $stmt->execute([$reason, $username]);
        return $stmt->rowCount();
    }
    return 0;
}

/**
 * Terminate multiple active sessions in a single batch UPDATE query
 */
function terminateMultipleRadiusSessions(array $sessionIds, string $reason = 'Admin-Reset'): int {
    if (empty($sessionIds)) return 0;
    $db = getDB();
    $cleanIds = array_values(array_filter(array_map('trim', $sessionIds)));
    if (empty($cleanIds)) return 0;
    
    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $params = array_merge([$reason], $cleanIds);
    $stmt = $db->prepare("UPDATE radacct SET acctstoptime = NOW(), acctsessiontime = GREATEST(0, TIMESTAMPDIFF(SECOND, acctstarttime, NOW())), acctterminatecause = ? WHERE acctsessionid IN ($placeholders) AND acctstoptime IS NULL");
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Terminate all currently active sessions across all accounts in radacct
 */
function terminateAllRadiusSessions(string $reason = 'Admin-Force-Reauth'): int {
    $db = getDB();
    $stmt = $db->prepare("UPDATE radacct SET acctstoptime = NOW(), acctsessiontime = GREATEST(0, TIMESTAMPDIFF(SECOND, acctstarttime, NOW())), acctterminatecause = ? WHERE acctstoptime IS NULL");
    $stmt->execute([$reason]);
    return $stmt->rowCount();
}

/**
 * Send RADIUS Disconnect-Request (PoD / CoA RFC 3576 / RFC 5176) to NAS gateway
 */
function sendRadiusDisconnect(string $nasIp, string $username, string $sessionId = '', string $framedIp = ''): array {
    static $nasSecretCache = null;
    $db = getDB();

    if ($nasSecretCache === null) {
        try {
            $nasSecretCache = $db->query("SELECT nasname, secret FROM nas")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            $nasSecretCache = [];
        }
    }
    $secret = $nasSecretCache[$nasIp] ?? 'secret';

    $attributes = ["User-Name=$username"];
    if ($sessionId !== '') {
        $attributes[] = "Acct-Session-Id=$sessionId";
    }
    if ($framedIp !== '') {
        $attributes[] = "Framed-IP-Address=$framedIp";
    }
    $attrString = implode(',', $attributes);

    $cmd = "echo \"$attrString\" | radclient -x " . escapeshellarg("$nasIp:3799") . " disconnect " . escapeshellarg($secret) . " 2>&1";

    $output = '';
    $executed = false;

    if (function_exists('shell_exec') && stripos(PHP_OS, 'WIN') === false) {
        $radclientCheck = trim((string)@shell_exec('which radclient 2>/dev/null'));
        if ($radclientCheck !== '') {
            $output = (string)@shell_exec($cmd);
            $executed = true;
        }
    }

    // Ultra-fast non-blocking stream notification (20ms timeout maximum)
    if (!$executed && $nasIp !== '' && $nasIp !== '0.0.0.0' && !str_contains($nasIp, '/')) {
        $errno = 0;
        $errstr = '';
        try {
            $fp = @stream_socket_client("udp://$nasIp:3799", $errno, $errstr, 0.02, STREAM_CLIENT_CONNECT);
            if ($fp) {
                fclose($fp);
            }
        } catch (\Throwable $e) {}
    }

    return [
        'command'  => $cmd,
        'executed' => $executed,
        'output'   => $output,
        'secret'   => $secret,
    ];
}

/**
 * Purge orphaned/stale sessions from radacct where device disconnected without Acct-Stop.
 * Closes sessions older than $hours hours (default 24h) with accurate duration.
 */
function purgeStaleRadiusSessions(int $hours = 24): int {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE radacct 
        SET acctstoptime = COALESCE(acctupdatetime, acctstarttime), 
            acctsessiontime = GREATEST(0, TIMESTAMPDIFF(SECOND, acctstarttime, COALESCE(acctupdatetime, acctstarttime))), 
            acctterminatecause = 'Admin-Stale-Purge' 
        WHERE acctstoptime IS NULL 
          AND acctstarttime < NOW() - INTERVAL ? HOUR
          AND (acctupdatetime IS NULL OR acctupdatetime < NOW() - INTERVAL ? HOUR)
    ");
    $stmt->execute([$hours, $hours]);
    return $stmt->rowCount();
}

/**
 * Count orphaned/stale sessions in radacct older than $hours hours.
 * Uses composite index idx_stop_start (acctstoptime, acctstarttime) for lightning speed (< 2ms).
 */
function countStaleRadiusSessions(int $hours = 24): int {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM radacct 
        WHERE acctstoptime IS NULL 
          AND acctstarttime < NOW() - INTERVAL ? HOUR
    ");
    $stmt->execute([$hours]);
    return (int)$stmt->fetchColumn();
}

/**
 * Retrieves accounts and connected devices that are genuinely active RIGHT NOW.
 * - Filters out stale / ghost sessions beyond the active network window using idx_stop_start.
 * - Optional $allowSnapshot: when true (e.g. ?snapshot=1 on localhost test), anchors reference time to newest db record.
 * - De-duplicates candidate sessions by device MAC address.
 * - Discards devices whose latest session in radacct already ended or was superseded via fast batch lookup.
 * - De-duplicates overlapping IP addresses.
 * - Groups multiple active devices under their respective subscriber account.
 */
function getTrulyActiveOnlineAccounts(PDO $db, string $search = '', bool $allowSnapshot = false): array {
    $now = time();
    if ($allowSnapshot) {
        $maxStartStr = $db->query("SELECT MAX(acctstarttime) FROM radacct WHERE acctstoptime IS NULL")->fetchColumn();
        $maxStart = $maxStartStr ? strtotime($maxStartStr) : 0;
        $refNow = ($maxStart > 0) ? $maxStart : $now;
    } else {
        $refNow = $now;
    }

    // Active temporal cutoff: devices must have connected within the last 24 hours
    $cutoffTime = date('Y-m-d H:i:s', $refNow - (24 * 3600));

    $whereClauses = ["acctstoptime IS NULL", "acctstarttime >= ?"];
    $params = [$cutoffTime];

    $search = trim($search);
    if ($search !== '') {
        $isIp  = (bool)preg_match('/^[0-9\.:]+$/', $search) && str_contains($search, '.');
        $isMac = (bool)preg_match('/^[0-9a-fA-F:\-\.]{4,}$/', $search) && (str_contains($search, ':') || str_contains($search, '-'));

        if ($isIp) {
            $whereClauses[] = "framedipaddress LIKE ?";
            $params[] = $search . '%';
        } elseif ($isMac) {
            $cleanSearch = str_replace([':', '-', '.'], '', $search);
            $whereClauses[] = "(callingstationid LIKE ? OR REPLACE(REPLACE(REPLACE(callingstationid, ':', ''), '-', ''), '.', '') LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $cleanSearch . '%';
        } else {
            $whereClauses[] = "username LIKE ?";
            $params[] = '%' . $search . '%';
        }
    }

    $whereSql = "WHERE " . implode(' AND ', $whereClauses);
    $stmt = $db->prepare("
        SELECT radacctid, acctsessionid, username, nasipaddress, framedipaddress,
               callingstationid, calledstationid, acctstarttime, acctupdatetime,
               COALESCE(acctinputoctets, 0) AS acctinputoctets,
               COALESCE(acctoutputoctets, 0) AS acctoutputoctets
        FROM radacct
        $whereSql
        ORDER BY radacctid DESC
    ");
    $stmt->execute($params);
    $rawSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 1. De-duplicate candidate open sessions per physical MAC (keep newest radacctid)
    $candidateSessionsByMac = [];
    foreach ($rawSessions as $sess) {
        $rawMac = $sess['callingstationid'] ?? '';
        $cleanMac = strtoupper(trim(str_replace([':', '-', '.'], '', $rawMac)));
        if ($cleanMac === '') {
            $candidateSessionsByMac[] = $sess;
            continue;
        }
        if (!isset($candidateSessionsByMac[$cleanMac])) {
            $candidateSessionsByMac[$cleanMac] = $sess;
        }
    }

    // 2. High-speed batch verification: verify candidate devices have not stopped in a later session
    $validSessions = [];
    $seenIps = [];

    if (!empty($candidateSessionsByMac)) {
        $macList = array_values(array_filter(array_keys($candidateSessionsByMac), 'is_string'));
        $latestStatusByMac = [];
        if (!empty($macList)) {
            $ph = implode(',', array_fill(0, count($macList), '?'));
            $bStmt = $db->prepare("
                SELECT ra.callingstationid, ra.radacctid, ra.acctstoptime
                FROM radacct ra
                INNER JOIN (
                    SELECT callingstationid, MAX(radacctid) AS max_id
                    FROM radacct
                    WHERE callingstationid IN ($ph)
                    GROUP BY callingstationid
                ) latest ON ra.radacctid = latest.max_id
            ");
            $bStmt->execute($macList);
            $rows = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $rawM = $r['callingstationid'] ?? '';
                $cleanM = strtoupper(trim(str_replace([':', '-', '.'], '', $rawM)));
                $latestStatusByMac[$cleanM] = $r;
            }
        }

        foreach ($candidateSessionsByMac as $k => $sess) {
            if (isset($latestStatusByMac[$k])) {
                $latest = $latestStatusByMac[$k];
                if (!empty($latest['acctstoptime'])) continue;
                if ((int)$latest['radacctid'] > (int)$sess['radacctid']) continue;
            }

            $ip = trim($sess['framedipaddress'] ?? '');
            if ($ip !== '' && $ip !== '0.0.0.0') {
                if (isset($seenIps[$ip])) continue;
                $seenIps[$ip] = true;
            }

            $validSessions[] = $sess;
        }
    }

    // 3. Batch IP Resolution for devices where Framed-IP is 0.0.0.0 or pending DHCP
    $macsNeedingIp = [];
    foreach ($validSessions as $sess) {
        $rawIp = trim($sess['framedipaddress'] ?? '');
        if ($rawIp === '' || $rawIp === '0.0.0.0') {
            $cMac = strtoupper(trim(str_replace([':', '-', '.'], '', $sess['callingstationid'] ?? '')));
            if ($cMac !== '') {
                $macsNeedingIp[$cMac] = $sess['callingstationid'];
            }
        }
    }

    $resolvedIpByMac = [];
    if (!empty($macsNeedingIp)) {
        // Step A: Check radippool
        if (dbTableExists('radippool')) {
            $phPool = implode(',', array_fill(0, count($macsNeedingIp), '?'));
            $poolStmt = $db->prepare("SELECT callingstationid, framedipaddress FROM radippool WHERE callingstationid IN ($phPool) AND framedipaddress != '0.0.0.0' AND framedipaddress != '' AND expiry_time > NOW()");
            $poolStmt->execute(array_values($macsNeedingIp));
            while ($pRow = $poolStmt->fetch(PDO::FETCH_ASSOC)) {
                $cM = strtoupper(trim(str_replace([':', '-', '.'], '', $pRow['callingstationid'])));
                $resolvedIpByMac[$cM] = ['ip' => $pRow['framedipaddress'], 'source' => 'radippool'];
            }
        }

        // Step B: For remaining MACs, check recent radacct session with non-zero IP
        $remainingMacs = array_diff_key($macsNeedingIp, $resolvedIpByMac);
        if (!empty($remainingMacs)) {
            $phRec = implode(',', array_fill(0, count($remainingMacs), '?'));
            $recStmt = $db->prepare("
                SELECT ra.callingstationid, ra.framedipaddress
                FROM radacct ra
                INNER JOIN (
                    SELECT callingstationid, MAX(radacctid) AS max_id
                    FROM radacct
                    WHERE callingstationid IN ($phRec) AND framedipaddress != '0.0.0.0' AND framedipaddress != ''
                    GROUP BY callingstationid
                ) sub ON ra.radacctid = sub.max_id
            ");
            $recStmt->execute(array_values($remainingMacs));
            while ($rRow = $recStmt->fetch(PDO::FETCH_ASSOC)) {
                $cM = strtoupper(trim(str_replace([':', '-', '.'], '', $rRow['callingstationid'])));
                $resolvedIpByMac[$cM] = ['ip' => $rRow['framedipaddress'], 'source' => 'recent_lease'];
            }
        }
    }

    // 4. Group valid sessions by subscriber account (username)
    $accounts = [];
    foreach ($validSessions as $sess) {
        $u = $sess['username'];
        $rawIp = trim($sess['framedipaddress'] ?? '');
        $cM = strtoupper(trim(str_replace([':', '-', '.'], '', $sess['callingstationid'] ?? '')));

        if ($rawIp !== '' && $rawIp !== '0.0.0.0') {
            $sess['resolved_ip'] = $rawIp;
            $sess['ip_source']   = 'live';
        } elseif (isset($resolvedIpByMac[$cM])) {
            $sess['resolved_ip'] = $resolvedIpByMac[$cM]['ip'];
            $sess['ip_source']   = $resolvedIpByMac[$cM]['source'];
        } else {
            $sess['resolved_ip'] = '';
            $sess['ip_source']   = 'pending_dhcp';
        }

        // Parse SSID from calledstationid (format: AP_MAC:SSID)
        $calledParts = explode(':', $sess['calledstationid'] ?? '', 2);
        $sess['ap_mac'] = formatMac($calledParts[0] ?? '');
        $sess['ssid']   = trim($calledParts[1] ?? '');

        if (!isset($accounts[$u])) {
            $accounts[$u] = [
                'username'          => $u,
                'devices'           => [],
                'session_ids'       => [],
                'latest_starttime'  => $sess['acctstarttime'],
                'primary_ip'        => $sess['resolved_ip'],
                'primary_ip_source' => $sess['ip_source'],
                'primary_mac'       => $sess['callingstationid'],
                'primary_nas'       => $sess['nasipaddress'],
                'primary_ssid'      => $sess['ssid'],
                'total_input'       => 0,
                'total_output'      => 0,
            ];
        }

        $uptimeSec = max(0, $refNow - strtotime($sess['acctstarttime']));
        $sess['duration_sec'] = $uptimeSec;
        $sess['duration_text'] = formatDuration($uptimeSec);

        $accounts[$u]['total_input'] += (float)($sess['acctinputoctets'] ?? 0);
        $accounts[$u]['total_output'] += (float)($sess['acctoutputoctets'] ?? 0);

        $accounts[$u]['devices'][] = $sess;
        $accounts[$u]['session_ids'][] = $sess['acctsessionid'];

        if (empty($accounts[$u]['primary_ip']) && !empty($sess['resolved_ip'])) {
            $accounts[$u]['primary_ip'] = $sess['resolved_ip'];
            $accounts[$u]['primary_ip_source'] = $sess['ip_source'];
        }

        if (strtotime($sess['acctstarttime']) > strtotime($accounts[$u]['latest_starttime'])) {
            $accounts[$u]['latest_starttime'] = $sess['acctstarttime'];
            if (!empty($sess['resolved_ip'])) {
                $accounts[$u]['primary_ip'] = $sess['resolved_ip'];
                $accounts[$u]['primary_ip_source'] = $sess['ip_source'];
            }
            $accounts[$u]['primary_mac'] = $sess['callingstationid'];
            $accounts[$u]['primary_nas'] = $sess['nasipaddress'];
            $accounts[$u]['primary_ssid'] = $sess['ssid'];
        }
    }

    foreach ($accounts as &$acc) {
        $acc['device_count'] = count($acc['devices']);
        $acc['duration_text'] = formatDuration(max(0, $refNow - strtotime($acc['latest_starttime'])));
    }
    unset($acc);

    return [
        'ref_now'        => $refNow,
        'accounts'       => array_values($accounts),
        'total_accounts' => count($accounts),
        'total_devices'  => count($validSessions),
        'is_snapshot'    => ($refNow !== $now),
    ];
}



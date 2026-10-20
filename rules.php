<?php
/**
 * CENDANA — Device & Session Access Rules (Simultaneous-Use Management)
 * Manage concurrent device connectivity policies for registered subscriber accounts and group policies.
 */

require_once __DIR__ . '/auth.php';
requireLogin();
$db = getDB();

$page_title = 'Rules';

// ── FAST AJAX ENDPOINTS ──────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user_devices') {
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    $targetUser = trim($_GET['username'] ?? '');
    if ($targetUser === '') {
        echo json_encode(['success' => false, 'error' => 'Username required']);
        exit;
    }
    try {
        $devStmt = $db->prepare("
            SELECT radacctid, acctsessionid, username, nasipaddress, framedipaddress,
                   callingstationid, acctstarttime, acctsessiontime,
                   acctinputoctets, acctoutputoctets
            FROM radacct
            WHERE username = ? AND acctstoptime IS NULL AND acctstarttime >= NOW() - INTERVAL 24 HOUR
            ORDER BY radacctid DESC
        ");
        $devStmt->execute([$targetUser]);
        $devices = $devStmt->fetchAll(PDO::FETCH_ASSOC);

        $refNow = time();
        foreach ($devices as &$d) {
            $startTs = !empty($d['acctstarttime']) ? strtotime($d['acctstarttime']) : $refNow;
            $d['duration_text'] = formatDuration(max(0, $refNow - $startTs));
            $d['upload_text']   = formatBytes($d['acctinputoctets'] ?? 0);
            $d['download_text'] = formatBytes($d['acctoutputoctets'] ?? 0);
            $d['mac_formatted'] = formatMac($d['callingstationid'] ?? '');
        }
        echo json_encode(['success' => true, 'devices' => $devices]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── FORM ACTIONS (POST) ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isReadOnly()) {
        flash('Read-only accounts cannot modify access rules.', 'danger');
        header('Location: rules.php');
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    $returnUrl = $_POST['return_url'] ?? 'rules.php';

    // 1. Single User Rule Update
    if ($action === 'set_user_rule') {
        $username = trim($_POST['username'] ?? '');
        $limit    = (int)($_POST['limit'] ?? 0);

        if ($username !== '') {
            $db->prepare("DELETE FROM radcheck WHERE username=? AND attribute='Simultaneous-Use'")->execute([$username]);
            if ($limit > 0) {
                $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Simultaneous-Use', ':=', ?)")
                   ->execute([$username, (string)$limit]);
                auditLog('RULE_USER_SET', $username, "Set Simultaneous-Use to $limit device(s)");
                flash("Device rule for <strong>" . htmlspecialchars($username) . "</strong> updated to <strong>$limit device(s)</strong>.", 'success');
            } elseif ($limit === -1) {
                auditLog('RULE_USER_SET', $username, "Reset Simultaneous-Use to Group Policy");
                flash("Device rule for <strong>" . htmlspecialchars($username) . "</strong> reset to <strong>Group Policy</strong>.", 'success');
            } else {
                auditLog('RULE_USER_SET', $username, "Removed Simultaneous-Use (Unlimited devices)");
                flash("Device rule for <strong>" . htmlspecialchars($username) . "</strong> set to <strong>Unlimited</strong>.", 'success');
            }
        }
        header('Location: ' . $returnUrl);
        exit;
    }

    // 2. Bulk User Rule Update
    if ($action === 'bulk_user_rule') {
        $usernames = (array)($_POST['usernames'] ?? []);
        $bulkLimit = (int)($_POST['bulk_limit'] ?? 1);

        if (empty($usernames)) {
            flash('No accounts were selected for bulk update.', 'warning');
            header('Location: ' . $returnUrl);
            exit;
        }

        $db->beginTransaction();
        try {
            $delStmt = $db->prepare("DELETE FROM radcheck WHERE username=? AND attribute='Simultaneous-Use'");
            $insStmt = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Simultaneous-Use', ':=', ?)");

            foreach ($usernames as $u) {
                $u = trim($u);
                if ($u === '') continue;
                $delStmt->execute([$u]);
                if ($bulkLimit > 0) {
                    $insStmt->execute([$u, (string)$bulkLimit]);
                }
            }
            $db->commit();

            $cnt = count($usernames);
            if ($bulkLimit > 0) {
                auditLog('RULE_BULK_SET', "$cnt accounts", "Set Simultaneous-Use to $bulkLimit");
                flash("Successfully updated device rule to <strong>$bulkLimit device(s)</strong> for <strong>$cnt account(s)</strong>.", 'success');
            } elseif ($bulkLimit === -1) {
                auditLog('RULE_BULK_SET', "$cnt accounts", "Reset Simultaneous-Use to Group Policy");
                flash("Successfully reset <strong>$cnt account(s)</strong> to inherit their <strong>Group Policy</strong>.", 'success');
            } else {
                auditLog('RULE_BULK_SET', "$cnt accounts", "Set Simultaneous-Use to Unlimited");
                flash("Successfully set <strong>$cnt account(s)</strong> to <strong>Unlimited devices</strong>.", 'success');
            }
        } catch (Throwable $e) {
            $db->rollBack();
            flash('Failed to update rules: ' . $e->getMessage(), 'danger');
        }
        header('Location: ' . $returnUrl);
        exit;
    }

    // 3. Group Policy Update
    if ($action === 'set_group_rule') {
        $groupname  = trim($_POST['groupname'] ?? '');
        $groupLimit = (int)($_POST['group_limit'] ?? 0);

        if ($groupname !== '') {
            $db->prepare("DELETE FROM radgroupcheck WHERE groupname=? AND attribute='Simultaneous-Use'")->execute([$groupname]);
            if ($groupLimit > 0) {
                $db->prepare("INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES (?, 'Simultaneous-Use', ':=', ?)")
                   ->execute([$groupname, (string)$groupLimit]);
                auditLog('RULE_GROUP_SET', $groupname, "Set group Simultaneous-Use to $groupLimit");
                flash("Device policy for group <strong>" . htmlspecialchars($groupname) . "</strong> set to <strong>$groupLimit device(s)</strong>.", 'success');
            } else {
                auditLog('RULE_GROUP_SET', $groupname, "Removed group Simultaneous-Use (Unlimited)");
                flash("Device policy for group <strong>" . htmlspecialchars($groupname) . "</strong> removed (Unlimited).", 'success');
            }
        }
        header('Location: rules.php?tab=groups');
        exit;
    }

    // 4. Enforce Group Policy on All Members (remove individual overrides)
    if ($action === 'sync_group_members') {
        $groupname = trim($_POST['groupname'] ?? '');
        if ($groupname !== '') {
            $stmt = $db->prepare("
                DELETE rc FROM radcheck rc
                INNER JOIN radusergroup rug ON rug.username = rc.username
                WHERE rug.groupname = ? AND rc.attribute = 'Simultaneous-Use'
            ");
            $stmt->execute([$groupname]);
            $affected = $stmt->rowCount();
            auditLog('RULE_GROUP_SYNC', $groupname, "Removed overrides for $affected members");
            flash("All member overrides removed for group <strong>" . htmlspecialchars($groupname) . "</strong> ($affected accounts synchronized to group policy).", 'success');
        }
        header('Location: rules.php?tab=groups');
        exit;
    }

    // 5. Global Default Limit Setting
    if ($action === 'set_global_default') {
        $defaultLimit = max(0, (int)($_POST['default_limit'] ?? 0));
        setSetting('default_device_limit', $defaultLimit);

        $applyExisting = !empty($_POST['apply_to_existing']);
        if ($applyExisting && $defaultLimit > 0) {
            // Apply to all existing accounts that currently have NO Simultaneous-Use rule in radcheck
            $insQuery = "
                INSERT INTO radcheck (username, attribute, op, value)
                SELECT DISTINCT rc.username, 'Simultaneous-Use', ':=', ?
                FROM radcheck rc
                LEFT JOIN radcheck rc2 ON rc2.username = rc.username AND rc2.attribute = 'Simultaneous-Use'
                WHERE rc2.username IS NULL
            ";
            $stmt = $db->prepare($insQuery);
            $stmt->execute([(string)$defaultLimit]);
            $appliedCount = $stmt->rowCount();
            auditLog('RULE_GLOBAL_SET', 'system', "Set default_device_limit=$defaultLimit and applied to $appliedCount accounts");
            flash("Global default set to <strong>$defaultLimit device(s)</strong> and applied to <strong>" . number_format($appliedCount) . " accounts</strong> without rules.", 'success');
        } else {
            auditLog('RULE_GLOBAL_SET', 'system', "Set default_device_limit=$defaultLimit");
            flash("Global default device limit updated to <strong>" . ($defaultLimit > 0 ? "$defaultLimit device(s)" : 'Unlimited') . "</strong>.", 'success');
        }
        header('Location: rules.php');
        exit;
    }

    // 6. Force Disconnect All Devices for User
    if ($action === 'disconnect_user') {
        $username = trim($_POST['username'] ?? '');
        if ($username !== '') {
            $count = terminateRadiusSession('', $username, 'Admin-Disconnect');
            // Attempt RFC 3576 disconnect signal to active NAS APs
            $nasRows = $db->prepare("SELECT DISTINCT nasipaddress, acctsessionid, framedipaddress, callingstationid FROM radacct WHERE username=? AND acctstoptime IS NULL LIMIT 20");
            $nasRows->execute([$username]);
            while ($nr = $nasRows->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($nr['nasipaddress'])) {
                    sendRadiusDisconnect($nr['nasipaddress'], $username, $nr['acctsessionid'] ?? '', $nr['framedipaddress'] ?? '', $nr['callingstationid'] ?? '');
                }
            }
            auditLog('RULE_DISCONNECT_USER', $username, "Terminated $count session(s)");
            flash("All <strong>" . number_format($count) . " active device(s)</strong> for user <strong>" . htmlspecialchars($username) . "</strong> have been disconnected.", 'success');
        }
        header('Location: ' . $returnUrl);
        exit;
    }

    // 7. Force Disconnect Single Session
    if ($action === 'disconnect_session') {
        $sid      = trim($_POST['session_id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $nasIp    = trim($_POST['nas_ip'] ?? '');
        $framedIp = trim($_POST['framed_ip'] ?? '');
        $mac      = trim($_POST['mac'] ?? '');

        if ($sid !== '' || $username !== '') {
            terminateRadiusSession($sid, $username, 'Admin-Disconnect');
            if ($nasIp !== '') {
                sendRadiusDisconnect($nasIp, $username, $sid, $framedIp, $mac);
            }
            auditLog('RULE_DISCONNECT_SESSION', $username, "Terminated session $sid ($framedIp, MAC: $mac)");
            flash("Device connection (IP: " . htmlspecialchars($framedIp) . ") disconnected successfully.", 'success');
        }
        header('Location: ' . $returnUrl);
        exit;
    }
}

// Release session lock immediately to prevent tab blocking
session_write_close();

// ── GET PARAMETERS & ACTIVE TAB ──────────────────────────────────────────────
$tab         = $_GET['tab'] ?? 'accounts';
if (!in_array($tab, ['accounts', 'groups', 'tester'])) $tab = 'accounts';

$search      = trim($_GET['q'] ?? '');
$groupFilter = trim($_GET['group'] ?? '');
$ruleFilter  = trim($_GET['rule'] ?? 'all');   // 'all', '1', '2', 'multi', 'unlimited', 'override', 'inherited'
$stateFilter = trim($_GET['state'] ?? 'all');  // 'all', 'online', 'maxed', 'exceeded', 'offline'
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = (int)($_GET['per_page'] ?? (defined('ROWS_PER_PAGE') ? ROWS_PER_PAGE : 20));
if (!in_array($perPage, [20, 50, 100])) $perPage = 20;

$lim = $perPage;
$off = ($page - 1) * $lim;

// Check userinfo table
$hasUserinfo  = dbTableExists('userinfo');
$userInfoJoin = $hasUserinfo ? "LEFT JOIN userinfo ui ON ui.username = rc.username" : "";
$uiCols       = $hasUserinfo ? "MAX(ui.firstname) AS firstname, MAX(ui.lastname) AS lastname, MAX(ui.department) AS department, MAX(ui.email) AS email," : "";

// System Group Simultaneous-Use Policy Map (< 0.1ms)
$groupSimulMap = $db->query("SELECT groupname, CAST(value AS UNSIGNED) AS simul FROM radgroupcheck WHERE attribute = 'Simultaneous-Use'")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// All distinct groups in system
$allGroups = $db->query("SELECT DISTINCT groupname FROM radusergroup UNION SELECT DISTINCT groupname FROM radgroupcheck ORDER BY groupname")->fetchAll(PDO::FETCH_COLUMN) ?: [];

// System Global Default Limit
$globalDefaultLimit = (int)getSetting('default_device_limit', 0);

// ── SUMMARY KPI COUNTS (High performance indexed scans, < 2ms) ───────────────
$totalRegistered = (int)$db->query("SELECT COUNT(DISTINCT username) FROM radcheck")->fetchColumn();
$countSingle     = (int)$db->query("SELECT COUNT(DISTINCT username) FROM radcheck WHERE attribute = 'Simultaneous-Use' AND value = '1'")->fetchColumn();
$countMulti      = (int)$db->query("SELECT COUNT(DISTINCT username) FROM radcheck WHERE attribute = 'Simultaneous-Use' AND CAST(value AS UNSIGNED) > 1")->fetchColumn();
$countExplicit   = (int)$db->query("SELECT COUNT(DISTINCT username) FROM radcheck WHERE attribute = 'Simultaneous-Use'")->fetchColumn();
$countUnlimited  = max(0, $totalRegistered - $countExplicit);

$activeInfo     = getTrulyActiveOnlineAccounts($db);
$onlineAccounts = $activeInfo['total_accounts'];
$onlineDevices  = $activeInfo['total_devices'];

// ── TAB 1: ACCOUNTS QUERY BUILDER ────────────────────────────────────────────
$whereClauses = [];
$params       = [];

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

if ($groupFilter !== '') {
    $whereClauses[] = "rug.groupname = ?";
    $params[] = $groupFilter;
}

// Rule filter handling
if ($ruleFilter === '1') {
    $whereClauses[] = "COALESCE(rcu.user_simul, rgc.group_simul, 0) = 1";
} elseif ($ruleFilter === '2') {
    $whereClauses[] = "COALESCE(rcu.user_simul, rgc.group_simul, 0) = 2";
} elseif ($ruleFilter === 'multi') {
    $whereClauses[] = "COALESCE(rcu.user_simul, rgc.group_simul, 0) > 1";
} elseif ($ruleFilter === 'unlimited') {
    $whereClauses[] = "COALESCE(rcu.user_simul, rgc.group_simul, 0) = 0";
} elseif ($ruleFilter === 'override') {
    $whereClauses[] = "rcu.user_simul IS NOT NULL";
} elseif ($ruleFilter === 'inherited') {
    $whereClauses[] = "rcu.user_simul IS NULL AND rgc.group_simul IS NOT NULL";
}

// State filter handling
if ($stateFilter === 'online') {
    $whereClauses[] = "ra.active_count > 0";
} elseif ($stateFilter === 'maxed') {
    $whereClauses[] = "ra.active_count >= COALESCE(rcu.user_simul, rgc.group_simul, 0) AND COALESCE(rcu.user_simul, rgc.group_simul, 0) > 0";
} elseif ($stateFilter === 'exceeded') {
    $whereClauses[] = "ra.active_count > COALESCE(rcu.user_simul, rgc.group_simul, 0) AND COALESCE(rcu.user_simul, rgc.group_simul, 0) > 0";
} elseif ($stateFilter === 'offline') {
    $whereClauses[] = "(ra.active_count IS NULL OR ra.active_count = 0)";
}

$whereSql = !empty($whereClauses) ? ("WHERE " . implode(' AND ', $whereClauses)) : "";

// Fast subquery join for rule & active counts
$joinSubqueries = "
    LEFT JOIN (SELECT username, CAST(value AS UNSIGNED) AS user_simul FROM radcheck WHERE attribute = 'Simultaneous-Use') rcu ON rcu.username = rc.username
    LEFT JOIN (SELECT groupname, CAST(value AS UNSIGNED) AS group_simul FROM radgroupcheck WHERE attribute = 'Simultaneous-Use') rgc ON rgc.groupname = rug.groupname
    LEFT JOIN (SELECT username, COUNT(*) AS active_count FROM radacct WHERE acctstoptime IS NULL AND acctstarttime >= NOW() - INTERVAL 24 HOUR GROUP BY username) ra ON ra.username = rc.username
";

$countQuery = "
    SELECT COUNT(DISTINCT rc.username) c
    FROM radcheck rc
    $userInfoJoin
    LEFT JOIN radusergroup rug ON rug.username = rc.username
    $joinSubqueries
    $whereSql
";
$totalStmt = $db->prepare($countQuery);
$totalStmt->execute($params);
$totalUsers = (int)($totalStmt->fetch()['c'] ?? 0);
$pages = max(1, (int)ceil($totalUsers / $lim));

$userListQuery = "
    SELECT DISTINCT rc.username
    FROM radcheck rc
    $userInfoJoin
    LEFT JOIN radusergroup rug ON rug.username = rc.username
    $joinSubqueries
    $whereSql
    ORDER BY rc.username ASC
    LIMIT $lim OFFSET $off
";
$uStmt = $db->prepare($userListQuery);
$uStmt->execute($params);
$usernames = $uStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$userRows = [];
if (!empty($usernames)) {
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));

    // Batch query active sessions count for current page (< 1ms)
    $onlineStmt = $db->prepare("
        SELECT username, COUNT(*) AS device_count
        FROM radacct
        WHERE username IN ($placeholders)
          AND acctstoptime IS NULL
          AND acctstarttime >= NOW() - INTERVAL 24 HOUR
        GROUP BY username
    ");
    $onlineStmt->execute($usernames);
    $deviceMap = $onlineStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Detailed user query for the current page
    $detailStmt = $db->prepare("
        SELECT rc.username,
               MAX(CASE WHEN rc.attribute='Simultaneous-Use' THEN CAST(rc.value AS UNSIGNED) END) AS user_simul,
               MAX(CASE WHEN rc.attribute='Auth-Type' AND rc.value='Reject' THEN 1 ELSE 0 END) AS is_disabled,
               GROUP_CONCAT(DISTINCT rug.groupname ORDER BY rug.priority SEPARATOR ', ') AS groupname,
               $uiCols
               rc.username AS dummy
        FROM radcheck rc
        $userInfoJoin
        LEFT JOIN radusergroup rug ON rug.username = rc.username
        WHERE rc.username IN ($placeholders)
        GROUP BY rc.username
        ORDER BY rc.username ASC
    ");
    $detailStmt->execute($usernames);
    $rawRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawRows as $r) {
        $u = $r['username'];
        $userSimul = isset($r['user_simul']) ? (int)$r['user_simul'] : null;
        $groupStr  = $r['groupname'] ?? '';
        $firstGroup = '';
        if ($groupStr !== '') {
            $parts = explode(',', $groupStr);
            $firstGroup = trim($parts[0]);
        }
        $groupSimul = ($firstGroup !== '' && isset($groupSimulMap[$firstGroup])) ? (int)$groupSimulMap[$firstGroup] : null;

        // Effective rule resolution: Account Rule > Group Rule > Global Default > Unlimited
        if ($userSimul !== null) {
            $effectiveLimit = $userSimul;
            $ruleSource     = 'account';
            $sourceLabel    = 'Account Override';
        } elseif ($groupSimul !== null) {
            $effectiveLimit = $groupSimul;
            $ruleSource     = 'group';
            $sourceLabel    = 'Group: ' . $firstGroup;
        } elseif ($globalDefaultLimit > 0) {
            $effectiveLimit = $globalDefaultLimit;
            $ruleSource     = 'global';
            $sourceLabel    = 'Global Default';
        } else {
            $effectiveLimit = 0; // Unlimited
            $ruleSource     = 'unlimited';
            $sourceLabel    = 'Unlimited';
        }

        $activeDevices = (int)($deviceMap[$u] ?? 0);

        // Capacity status
        if ($effectiveLimit > 0) {
            if ($activeDevices > $effectiveLimit) {
                $capacityStatus = 'exceeded';
            } elseif ($activeDevices === $effectiveLimit) {
                $capacityStatus = 'maxed';
            } else {
                $capacityStatus = 'available';
            }
        } else {
            $capacityStatus = ($activeDevices > 0) ? 'online' : 'offline';
        }

        $fullName = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));

        $userRows[] = [
            'username'         => $u,
            'user_simul'       => $userSimul,
            'group_simul'      => $groupSimul,
            'groupname'        => $groupStr,
            'effective_limit'  => $effectiveLimit,
            'rule_source'      => $ruleSource,
            'source_label'     => $sourceLabel,
            'active_devices'   => $activeDevices,
            'capacity_status'  => $capacityStatus,
            'is_disabled'      => !empty($r['is_disabled']),
            'full_name'        => $fullName,
            'department'       => $r['department'] ?? '',
            'email'            => $r['email'] ?? '',
        ];
    }
}

// ── TAB 2: GROUPS DATA BUILDER ───────────────────────────────────────────────
$groupRows = [];
if ($tab === 'groups' || $tab === 'accounts') {
    $groupMembersStmt = $db->query("
        SELECT rug.groupname, COUNT(DISTINCT rug.username) AS member_count
        FROM radusergroup rug
        GROUP BY rug.groupname
    ");
    $memberCountMap = $groupMembersStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Count of overrides per group
    $overrideCountStmt = $db->query("
        SELECT rug.groupname, COUNT(DISTINCT rc.username) AS override_count
        FROM radusergroup rug
        INNER JOIN radcheck rc ON rc.username = rug.username AND rc.attribute = 'Simultaneous-Use'
        GROUP BY rug.groupname
    ");
    $overrideCountMap = $overrideCountStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    foreach ($allGroups as $g) {
        $gSimul     = isset($groupSimulMap[$g]) ? (int)$groupSimulMap[$g] : 0;
        $mCount     = (int)($memberCountMap[$g] ?? 0);
        $oCount     = (int)($overrideCountMap[$g] ?? 0);
        $groupRows[] = [
            'groupname'      => $g,
            'simul'          => $gSimul,
            'member_count'   => $mCount,
            'override_count' => $oCount,
            'inheriting'     => max(0, $mCount - $oCount),
        ];
    }
}

// ── TAB 3: RULE TESTER & SIMULATOR ───────────────────────────────────────────
$testUser = trim($_GET['test_user'] ?? '');
$testData = null;
if ($testUser !== '') {
    // Check user in radcheck
    $tCheckStmt = $db->prepare("SELECT attribute, op, value FROM radcheck WHERE username=? ORDER BY attribute");
    $tCheckStmt->execute([$testUser]);
    $tChecks = $tCheckStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($tChecks)) {
        $userExists = true;
        $isDisabled = false;
        $tUserSimul = null;
        foreach ($tChecks as $tc) {
            if ($tc['attribute'] === 'Auth-Type' && $tc['value'] === 'Reject') $isDisabled = true;
            if ($tc['attribute'] === 'Simultaneous-Use') $tUserSimul = (int)$tc['value'];
        }

        // Group
        $tGroupStmt = $db->prepare("SELECT groupname FROM radusergroup WHERE username=? ORDER BY priority ASC LIMIT 1");
        $tGroupStmt->execute([$testUser]);
        $tGroup = $tGroupStmt->fetchColumn() ?: '';
        $tGroupSimul = ($tGroup !== '' && isset($groupSimulMap[$tGroup])) ? (int)$groupSimulMap[$tGroup] : null;

        // Userinfo
        $tInfo = null;
        if ($hasUserinfo) {
            $tInfoStmt = $db->prepare("SELECT firstname, lastname, department, email FROM userinfo WHERE username=? LIMIT 1");
            $tInfoStmt->execute([$testUser]);
            $tInfo = $tInfoStmt->fetch(PDO::FETCH_ASSOC);
        }

        // Effective limit
        if ($tUserSimul !== null) {
            $tEffective = $tUserSimul;
            $tSource    = 'Account Override (radcheck)';
        } elseif ($tGroupSimul !== null) {
            $tEffective = $tGroupSimul;
            $tSource    = "Group Policy ($tGroup)";
        } elseif ($globalDefaultLimit > 0) {
            $tEffective = $globalDefaultLimit;
            $tSource    = 'Global Default';
        } else {
            $tEffective = 0;
            $tSource    = 'Unlimited';
        }

        // Active sessions
        $tActiveStmt = $db->prepare("
            SELECT radacctid, acctsessionid, nasipaddress, framedipaddress,
                   callingstationid, acctstarttime, acctsessiontime,
                   acctinputoctets, acctoutputoctets
            FROM radacct
            WHERE username=? AND acctstoptime IS NULL AND acctstarttime >= NOW() - INTERVAL 24 HOUR
            ORDER BY radacctid DESC
        ");
        $tActiveStmt->execute([$testUser]);
        $tSessions = $tActiveStmt->fetchAll(PDO::FETCH_ASSOC);
        $tActiveCount = count($tSessions);

        $refNow = time();
        foreach ($tSessions as &$ts) {
            $startTs = !empty($ts['acctstarttime']) ? strtotime($ts['acctstarttime']) : $refNow;
            $ts['duration_text'] = formatDuration(max(0, $refNow - $startTs));
            $ts['upload_text']   = formatBytes($ts['acctinputoctets'] ?? 0);
            $ts['download_text'] = formatBytes($ts['acctoutputoctets'] ?? 0);
            $ts['mac_formatted'] = formatMac($ts['callingstationid'] ?? '');
        }

        // Simulation Decision
        if ($isDisabled) {
            $decision = 'REJECT';
            $decisionReason = 'Account is administratively disabled in radcheck (Auth-Type := Reject).';
            $decisionClass  = 'danger';
        } elseif ($tEffective > 0 && $tActiveCount >= $tEffective) {
            $decision = 'REJECT';
            $decisionReason = "Simultaneous login limit reached ($tActiveCount of $tEffective device(s) connected). FreeRADIUS will reject new logins with 'Simultaneous-Use limit exceeded'.";
            $decisionClass  = 'danger';
        } elseif ($tEffective > 0) {
            $slotsLeft = $tEffective - $tActiveCount;
            $decision = 'ACCEPT';
            $decisionReason = "User has $slotsLeft available device slot(s) ($tActiveCount of $tEffective in use). Next login will be accepted.";
            $decisionClass  = 'success';
        } else {
            $decision = 'ACCEPT';
            $decisionReason = 'Account has no simultaneous connection limits. Concurrent logins are fully allowed.';
            $decisionClass  = 'info';
        }

        $testData = [
            'username'         => $testUser,
            'user_exists'      => true,
            'is_disabled'      => $isDisabled,
            'user_simul'       => $tUserSimul,
            'group'            => $tGroup,
            'group_simul'      => $tGroupSimul,
            'effective_limit'  => $tEffective,
            'source'           => $tSource,
            'active_sessions'  => $tSessions,
            'active_count'     => $tActiveCount,
            'decision'         => $decision,
            'decision_reason'  => $decisionReason,
            'decision_class'   => $decisionClass,
            'info'             => $tInfo,
        ];
    } else {
        $testData = [
            'username'    => $testUser,
            'user_exists' => false,
        ];
    }
}

// Current return URL builder
$returnParams = [];
if ($tab !== 'accounts') $returnParams['tab'] = $tab;
if ($search !== '')      $returnParams['q'] = $search;
if ($groupFilter !== '') $returnParams['group'] = $groupFilter;
if ($ruleFilter !== 'all')  $returnParams['rule'] = $ruleFilter;
if ($stateFilter !== 'all') $returnParams['state'] = $stateFilter;
if ($page > 1)           $returnParams['page'] = $page;
if ($perPage !== 20)     $returnParams['per_page'] = $perPage;
$currentReturnUrl = 'rules.php' . (!empty($returnParams) ? ('?' . http_build_query($returnParams)) : '');

$exportParams = array_merge($returnParams, ['type' => 'rules']);
$exportUrl = 'export.php?' . http_build_query($exportParams);

$flash = getFlash();

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Device &amp; Session Access Rules</h4>
        <p class="text-muted mb-0">Manage concurrent device connectivity policies (<code>Simultaneous-Use</code>) across subscriber accounts and user groups.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#globalDefaultModal" title="Set Global Default Device Limit">
            <i class="bi bi-gear"></i> <span>Global Default</span>
            <?php if ($globalDefaultLimit > 0): ?>
            <span class="badge bg-primary ms-1"><?= $globalDefaultLimit ?> Dev</span>
            <?php endif; ?>
        </button>
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 shadow-sm" title="Export Rules to CSV">
            <i class="bi bi-download"></i> <span class="d-none d-md-inline">Export CSV</span>
        </a>
        <a href="rules.php?tab=tester" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1.5 shadow-sm" title="Simulate & Test Connection Allowance">
            <i class="bi bi-cpu"></i> <span>Rule Tester</span>
        </a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
    <?= $flash['msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-people text-primary"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($totalRegistered) ?></div>
                <div class="stat-label">Registered Accounts <i class="bi bi-chevron-right small text-muted"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <a href="rules.php?rule=1" class="stat-card" title="View Single Device Accounts">
            <div class="stat-icon" style="background:#f0fdf4">
                <i class="bi bi-phone text-success"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= number_format($countSingle) ?></div>
                <div class="stat-label">Single Device Limit <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:.68rem;">1:1 Ratio</span></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="rules.php?rule=multi" class="stat-card" title="View Multi-Device Accounts">
            <div class="stat-icon" style="background:#fdf4ff">
                <i class="bi bi-laptop" style="color:#9333ea"></i>
            </div>
            <div>
                <div class="stat-value" style="color:#9333ea"><?= number_format($countMulti) ?></div>
                <div class="stat-label">Multi-Device (2+) <span class="badge bg-purple-subtle text-purple border ms-1" style="font-size:.68rem; background:#f3e8ff; color:#9333ea;">Shared</span></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="sessions.php" class="stat-card" title="View Live Online Sessions">
            <div class="stat-icon" style="background:#fefce8">
                <i class="bi bi-broadcast text-warning"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($onlineAccounts) ?></div>
                <div class="stat-label">Online Accounts <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-dark ms-1" style="font-size:.68rem;"><?= number_format($onlineDevices) ?> Devices</span></div>
            </div>
        </a>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'accounts' ? 'active fw-bold' : '' ?>" href="rules.php?tab=accounts">
            <i class="bi bi-person-check me-1.5"></i>Account Rules
            <span class="badge bg-secondary-subtle text-secondary ms-1.5"><?= number_format($totalUsers) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'groups' ? 'active fw-bold' : '' ?>" href="rules.php?tab=groups">
            <i class="bi bi-collection me-1.5"></i>Group Policies
            <span class="badge bg-secondary-subtle text-secondary ms-1.5"><?= count($allGroups) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'tester' ? 'active fw-bold' : '' ?>" href="rules.php?tab=tester">
            <i class="bi bi-cpu me-1.5"></i>Rule Simulator &amp; Diagnostics
        </a>
    </li>
</ul>

<?php if ($tab === 'accounts'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 1: ACCOUNT DEVICE RULES                                            -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->

<!-- Filter & Search Bar -->
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-2.5 px-3">
        <form method="GET" action="rules.php" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="accounts">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search account, name, dept..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="group" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— All Groups —</option>
                    <?php foreach ($allGroups as $g): ?>
                    <option value="<?= htmlspecialchars($g) ?>" <?= ($groupFilter === $g) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="rule" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($ruleFilter === 'all') ? 'selected' : '' ?>>All Device Rules</option>
                    <option value="1" <?= ($ruleFilter === '1') ? 'selected' : '' ?>>1 Device Limit</option>
                    <option value="2" <?= ($ruleFilter === '2') ? 'selected' : '' ?>>2 Devices Limit</option>
                    <option value="multi" <?= ($ruleFilter === 'multi') ? 'selected' : '' ?>>Multi-Device (2+)</option>
                    <option value="unlimited" <?= ($ruleFilter === 'unlimited') ? 'selected' : '' ?>>Unlimited Devices</option>
                    <option value="override" <?= ($ruleFilter === 'override') ? 'selected' : '' ?>>Account Overrides</option>
                    <option value="inherited" <?= ($ruleFilter === 'inherited') ? 'selected' : '' ?>>Group Inherited</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="state" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($stateFilter === 'all') ? 'selected' : '' ?>>All Activity</option>
                    <option value="online" <?= ($stateFilter === 'online') ? 'selected' : '' ?>>Online Devices (&gt;0)</option>
                    <option value="maxed" <?= ($stateFilter === 'maxed') ? 'selected' : '' ?>>At Max Limit (Full)</option>
                    <option value="exceeded" <?= ($stateFilter === 'exceeded') ? 'selected' : '' ?>>Over Limit (&gt;Max)</option>
                    <option value="offline" <?= ($stateFilter === 'offline') ? 'selected' : '' ?>>Offline (0)</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1.5 justify-content-end">
                <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Filter</button>
                <?php if ($search !== '' || $groupFilter !== '' || $ruleFilter !== 'all' || $stateFilter !== 'all'): ?>
                <a href="rules.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions Toolbar -->
<form method="POST" action="rules.php" id="bulkRulesForm">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="bulk_user_rule">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentReturnUrl) ?>">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 px-1">
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted" id="selectedCountBadge">0 selected</span>
            <div class="vr mx-1"></div>
            <div class="dropdown d-inline-block">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle disabled" type="button" id="btnBulkActions" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-check2-square me-1"></i>Apply Rule to Selected
                </button>
                <ul class="dropdown-menu shadow">
                    <li><h6 class="dropdown-header small text-muted">Set Concurrent Device Limit</h6></li>
                    <li><button type="submit" name="bulk_limit" value="1" class="dropdown-item d-flex align-items-center gap-2"><i class="bi bi-phone text-primary"></i> Set 1 Device (Strict)</button></li>
                    <li><button type="submit" name="bulk_limit" value="2" class="dropdown-item d-flex align-items-center gap-2"><i class="bi bi-laptop text-info"></i> Set 2 Devices</button></li>
                    <li><button type="submit" name="bulk_limit" value="3" class="dropdown-item d-flex align-items-center gap-2"><i class="bi bi-hdd-network text-teal"></i> Set 3 Devices</button></li>
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" onclick="openBulkCustomModal()"><i class="bi bi-123 text-secondary"></i> Set Custom Limit...</button></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><button type="submit" name="bulk_limit" value="0" class="dropdown-item d-flex align-items-center gap-2"><i class="bi bi-infinity text-muted"></i> Set Unlimited (No Limit)</button></li>
                    <li><button type="submit" name="bulk_limit" value="-1" class="dropdown-item d-flex align-items-center gap-2 text-warning"><i class="bi bi-arrow-counterclockwise"></i> Reset to Group Policy</button></li>
                </ul>
            </div>
        </div>
        <div class="small text-muted">
            Showing <?= number_format(min($totalUsers, $off + 1)) ?> &ndash; <?= number_format(min($totalUsers, $off + $lim)) ?> of <?= number_format($totalUsers) ?> accounts
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card shadow-sm border mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 38px;">
                            <input class="form-check-input" type="checkbox" id="checkAllUsers" title="Select all on this page">
                        </th>
                        <th>Account / Subscriber</th>
                        <th>Assigned Group</th>
                        <th>Device Limit Rule</th>
                        <th>Connected Devices</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($userRows)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-shield-x fs-2 d-block mb-2 text-secondary opacity-50"></i>
                            No subscriber accounts match the selected filters.
                        </td>
                    </tr>
                <?php else: foreach ($userRows as $u): ?>
                    <tr>
                        <td>
                            <input class="form-check-input user-checkbox" type="checkbox" name="usernames[]" value="<?= htmlspecialchars($u['username']) ?>">
                        </td>
                        <td>
                            <div class="fw-bold">
                                <a href="user-edit.php?username=<?= urlencode($u['username']) ?>" class="text-decoration-none text-dark hover-primary" title="Edit User">
                                    <?= htmlspecialchars($u['username']) ?>
                                </a>
                                <?php if ($u['is_disabled']): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size:.65rem">Disabled</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($u['full_name'] !== '' || $u['department'] !== ''): ?>
                            <div class="text-muted small">
                                <?= htmlspecialchars($u['full_name']) ?>
                                <?= ($u['full_name'] !== '' && $u['department'] !== '') ? '&bull;' : '' ?>
                                <?= htmlspecialchars($u['department']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['groupname'] !== ''): ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($u['groupname']) ?></span>
                            <?php if ($u['group_simul'] !== null): ?>
                            <div class="small text-muted" style="font-size:.72rem;">(Group rule: <?= $u['group_simul'] ?> dev)</div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted small">&mdash; No Group &mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['effective_limit'] === 1): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 py-1 px-2">
                                <i class="bi bi-phone"></i> 1 Device
                            </span>
                            <?php elseif ($u['effective_limit'] > 1): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1 py-1 px-2">
                                <i class="bi bi-laptop"></i> <?= $u['effective_limit'] ?> Devices
                            </span>
                            <?php else: ?>
                            <span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1 py-1 px-2">
                                <i class="bi bi-infinity"></i> Unlimited
                            </span>
                            <?php endif; ?>

                            <div class="small text-muted mt-0.5" style="font-size:.72rem;">
                                <?= htmlspecialchars($u['source_label']) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['active_devices'] === 0): ?>
                            <span class="text-muted small d-inline-flex align-items-center gap-1">
                                <i class="bi bi-circle text-secondary opacity-50" style="font-size:0.5rem"></i> 0 Connected
                            </span>
                            <?php elseif ($u['capacity_status'] === 'exceeded'): ?>
                            <button type="button" class="btn btn-sm btn-danger py-0 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="inspectUserDevices('<?= htmlspecialchars(addslashes($u['username'])) ?>')" title="Inspect Active Devices">
                                <i class="bi bi-exclamation-triangle-fill"></i> <?= $u['active_devices'] ?> / <?= $u['effective_limit'] ?> (Over Limit!)
                            </button>
                            <?php elseif ($u['capacity_status'] === 'maxed'): ?>
                            <button type="button" class="btn btn-sm btn-warning text-dark py-0 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="inspectUserDevices('<?= htmlspecialchars(addslashes($u['username'])) ?>')" title="Inspect Active Devices">
                                <i class="bi bi-dash-circle-fill"></i> <?= $u['active_devices'] ?> / <?= $u['effective_limit'] ?> (Full)
                            </button>
                            <?php elseif ($u['effective_limit'] > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="inspectUserDevices('<?= htmlspecialchars(addslashes($u['username'])) ?>')" title="Inspect Active Devices">
                                <i class="bi bi-check-circle-fill"></i> <?= $u['active_devices'] ?> / <?= $u['effective_limit'] ?> Devices
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="inspectUserDevices('<?= htmlspecialchars(addslashes($u['username'])) ?>')" title="Inspect Active Devices">
                                <i class="bi bi-broadcast"></i> <?= $u['active_devices'] ?> Connected
                            </button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-sliders me-1"></i>Rule
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><h6 class="dropdown-header small text-muted">Set Device Limit</h6></li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 <?= $u['user_simul'] === 1 ? 'active fw-semibold' : '' ?>" onclick="submitQuickRule('<?= htmlspecialchars(addslashes($u['username'])) ?>', 1)">
                                            <i class="bi bi-phone text-primary"></i> 1 Device (Strict)
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 <?= $u['user_simul'] === 2 ? 'active fw-semibold' : '' ?>" onclick="submitQuickRule('<?= htmlspecialchars(addslashes($u['username'])) ?>', 2)">
                                            <i class="bi bi-laptop text-info"></i> 2 Devices
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 <?= $u['user_simul'] === 3 ? 'active fw-semibold' : '' ?>" onclick="submitQuickRule('<?= htmlspecialchars(addslashes($u['username'])) ?>', 3)">
                                            <i class="bi bi-hdd-network text-teal"></i> 3 Devices
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2" onclick="openSingleCustomModal('<?= htmlspecialchars(addslashes($u['username'])) ?>', <?= (int)($u['user_simul'] ?? 0) ?>)">
                                            <i class="bi bi-123 text-secondary"></i> Custom Limit...
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 <?= ($u['user_simul'] === 0) ? 'active fw-semibold' : '' ?>" onclick="submitQuickRule('<?= htmlspecialchars(addslashes($u['username'])) ?>', 0)">
                                            <i class="bi bi-infinity text-muted"></i> Unlimited (No Limit)
                                        </button>
                                    </li>
                                    <?php if ($u['user_simul'] !== null): ?>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-warning" onclick="submitQuickRule('<?= htmlspecialchars(addslashes($u['username'])) ?>', -1)">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Group Policy
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><h6 class="dropdown-header small text-muted">Diagnostics &amp; Sessions</h6></li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="rules.php?tab=tester&test_user=<?= urlencode($u['username']) ?>">
                                            <i class="bi bi-cpu text-info"></i> Test &amp; Simulate Rule
                                        </a>
                                    </li>
                                    <?php if ($u['active_devices'] > 0): ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="inspectUserDevices('<?= htmlspecialchars(addslashes($u['username'])) ?>'); return false;">
                                            <i class="bi bi-hdd-stack text-success"></i> View Connected Devices (<?= $u['active_devices'] ?>)
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger" onclick="submitDisconnectUser('<?= htmlspecialchars(addslashes($u['username'])) ?>', <?= $u['active_devices'] ?>)">
                                            <i class="bi bi-power"></i> Disconnect All Devices
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 text-secondary" href="user-edit.php?username=<?= urlencode($u['username']) ?>">
                                            <i class="bi bi-pencil-square"></i> Edit User Profile
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="small text-muted">
        Page <?= $page ?> of <?= $pages ?> (<?= number_format($totalUsers) ?> accounts total)
    </div>
    <?= paginationLinks(['page' => $page, 'total_pages' => $pages], 'rules.php?' . http_build_query(array_diff_key($returnParams, ['page' => 1]))) ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'groups'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 2: GROUP DEVICE POLICIES                                            -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm border mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-collection text-primary me-2"></i>Group Device Policies (<code>radgroupcheck</code>)</h6>
            <p class="text-muted small mb-0">Subscribers without individual account overrides automatically inherit their group's device policy.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Group Name</th>
                    <th>Total Members</th>
                    <th>Group Device Limit (Policy)</th>
                    <th>Adoption Breakdown</th>
                    <th class="text-end" style="min-width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($groupRows)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        No user groups currently configured in the RADIUS database.
                    </td>
                </tr>
            <?php else: foreach ($groupRows as $g): ?>
                <tr>
                    <td>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($g['groupname']) ?></span>
                    </td>
                    <td>
                        <a href="rules.php?group=<?= urlencode($g['groupname']) ?>" class="badge bg-light text-dark border text-decoration-none">
                            <i class="bi bi-people me-1"></i><?= number_format($g['member_count']) ?> Members
                        </a>
                    </td>
                    <td>
                        <?php if ($g['simul'] === 1): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5">
                            <i class="bi bi-phone me-1"></i>1 Device Limit
                        </span>
                        <?php elseif ($g['simul'] > 1): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2.5">
                            <i class="bi bi-laptop me-1"></i><?= $g['simul'] ?> Devices Limit
                        </span>
                        <?php else: ?>
                        <span class="badge bg-light text-secondary border py-1 px-2.5">
                            <i class="bi bi-infinity me-1"></i>No Group Limit (Unlimited)
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small">
                            <span class="text-success fw-semibold"><?= number_format($g['inheriting']) ?></span> inheriting policy
                            <?php if ($g['override_count'] > 0): ?>
                            &bull; <span class="text-warning fw-semibold"><?= number_format($g['override_count']) ?></span> custom override(s)
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2.5" onclick="openGroupPolicyModal('<?= htmlspecialchars(addslashes($g['groupname'])) ?>', <?= (int)$g['simul'] ?>)">
                            <i class="bi bi-pencil me-1"></i>Edit Policy
                        </button>
                        <?php if ($g['override_count'] > 0): ?>
                        <form method="POST" action="rules.php" class="d-inline" onsubmit="return confirm('Reset all <?= $g['member_count'] ?> members of group <?= htmlspecialchars($g['groupname']) ?> to use the group policy? Any individual overrides will be removed.');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="sync_group_members">
                            <input type="hidden" name="groupname" value="<?= htmlspecialchars($g['groupname']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning text-dark py-1 px-2.5" title="Remove individual overrides and enforce group policy">
                                <i class="bi bi-arrow-repeat me-1"></i>Enforce on All
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'tester'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 3: RULE TESTER & SIMULATOR                                         -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-cpu text-primary me-2"></i>Test &amp; Simulate Subscriber Rule</h6>
                <p class="text-muted small mb-0">Simulate whether a new device connection would be accepted or rejected by FreeRADIUS.</p>
            </div>
            <div class="card-body">
                <form method="GET" action="rules.php">
                    <input type="hidden" name="tab" value="tester">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Subscriber Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="test_user" class="form-control" placeholder="e.g. john.doe" value="<?= htmlspecialchars($testUser) ?>" required autofocus>
                            <button type="submit" class="btn btn-primary px-3"><i class="bi bi-play-fill me-1"></i>Test Rule</button>
                        </div>
                        <div class="form-text">Enter any registered RADIUS account to evaluate policy.</div>
                    </div>
                </form>

                <hr class="my-3">

                <div class="small text-muted">
                    <h6 class="fw-bold small text-dark mb-2"><i class="bi bi-info-circle text-primary me-1"></i>How Simultaneous-Use is Evaluated:</h6>
                    <ol class="ps-3 mb-0">
                        <li class="mb-1"><strong>Account Check:</strong> FreeRADIUS queries <code>radcheck</code> for <code>Simultaneous-Use</code> on the account.</li>
                        <li class="mb-1"><strong>Group Policy:</strong> If not found, FreeRADIUS checks <code>radgroupcheck</code> for the user's assigned group.</li>
                        <li class="mb-1"><strong>Active Session Scan:</strong> FreeRADIUS counts active rows in <code>radacct</code> (<code>acctstoptime IS NULL</code>).</li>
                        <li><strong>Decision:</strong> If active sessions &ge; limit, FreeRADIUS issues an <code>Access-Reject</code> packet.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($testUser === ''): ?>
        <div class="card shadow-sm border h-100 d-flex align-items-center justify-content-center text-center p-5 text-muted">
            <i class="bi bi-diagram-3 fs-1 text-secondary opacity-50 mb-2"></i>
            <h5>No Subscriber Selected</h5>
            <p class="small mb-0">Enter a subscriber username on the left to inspect device rules and simulate authentication decisions.</p>
        </div>
        <?php elseif (!$testData['user_exists']): ?>
        <div class="card shadow-sm border h-100 border-danger p-4">
            <div class="text-danger mb-2">
                <i class="bi bi-x-circle-fill fs-3 me-2"></i>
                <h5 class="d-inline-block fw-bold mb-0">Account Not Found</h5>
            </div>
            <p class="text-secondary small mb-3">No account with username <strong><?= htmlspecialchars($testUser) ?></strong> exists in <code>radcheck</code>.</p>
            <a href="rules.php?tab=tester" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Try Another Account
            </a>
        </div>
        <?php else: ?>
        <div class="card shadow-sm border h-100">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold">Evaluation for: <span class="text-primary"><?= htmlspecialchars($testData['username']) ?></span></h6>
                    <?php if (!empty($testData['info']['firstname']) || !empty($testData['info']['department'])): ?>
                    <div class="text-muted small">
                        <?= htmlspecialchars(trim(($testData['info']['firstname'] ?? '') . ' ' . ($testData['info']['lastname'] ?? ''))) ?>
                        &bull; <?= htmlspecialchars($testData['info']['department'] ?? '') ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-1.5">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openSingleCustomModal('<?= htmlspecialchars(addslashes($testData['username'])) ?>', <?= (int)($testData['user_simul'] ?? 0) ?>)">
                        <i class="bi bi-pencil me-1"></i>Change Limit
                    </button>
                    <a href="user-edit.php?username=<?= urlencode($testData['username']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-person me-1"></i>Edit Profile
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Decision Alert Box -->
                <div class="alert alert-<?= $testData['decision_class'] ?> shadow-sm d-flex align-items-center gap-3 py-3 px-3 mb-3">
                    <div class="fs-2">
                        <?php if ($testData['decision'] === 'ACCEPT'): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Simulated Decision: Access-<?= $testData['decision'] ?></h6>
                        <div class="small"><?= htmlspecialchars($testData['decision_reason']) ?></div>
                    </div>
                </div>

                <!-- Trace Table -->
                <h6 class="fw-bold small text-muted text-uppercase mb-2">Policy Trace Resolution</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0 small">
                        <tbody>
                            <tr>
                                <td class="bg-light fw-semibold" style="width: 35%;">Account Limit (<code>radcheck</code>)</td>
                                <td>
                                    <?php if ($testData['user_simul'] !== null): ?>
                                    <span class="badge bg-primary"><?= $testData['user_simul'] ?> Device(s)</span>
                                    <?php else: ?>
                                    <span class="text-muted">None (Inherits from Group / Global)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-semibold">Assigned Group</td>
                                <td>
                                    <?= $testData['group'] !== '' ? htmlspecialchars($testData['group']) : '<span class="text-muted">— No Group —</span>' ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-semibold">Group Policy (<code>radgroupcheck</code>)</td>
                                <td>
                                    <?php if ($testData['group_simul'] !== null): ?>
                                    <span class="badge bg-info text-dark"><?= $testData['group_simul'] ?> Device(s)</span>
                                    <?php else: ?>
                                    <span class="text-muted">No Group Policy</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-semibold">Effective Limit Applied</td>
                                <td>
                                    <strong><?= $testData['effective_limit'] > 0 ? ($testData['effective_limit'] . ' Device(s)') : 'Unlimited (No Limit)' ?></strong>
                                    <span class="text-muted ms-1">(Source: <?= htmlspecialchars($testData['source']) ?>)</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-semibold">Currently Connected</td>
                                <td>
                                    <strong><?= $testData['active_count'] ?> Device(s) online right now</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Connected Devices List -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold small text-muted text-uppercase mb-0">Active Connected Devices (<?= $testData['active_count'] ?>)</h6>
                    <?php if ($testData['active_count'] > 0): ?>
                    <form method="POST" action="rules.php" onsubmit="return confirm('Disconnect all <?= $testData['active_count'] ?> devices for <?= htmlspecialchars($testData['username']) ?>?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="disconnect_user">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($testData['username']) ?>">
                        <input type="hidden" name="return_url" value="rules.php?tab=tester&test_user=<?= urlencode($testData['username']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0.5 px-2" style="font-size:0.75rem">
                            <i class="bi bi-power me-1"></i>Disconnect All
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($testData['active_sessions'])): ?>
                <div class="p-3 bg-light rounded text-center small text-muted">
                    No active sessions found in <code>radacct</code> for this account.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>IP Address</th>
                                <th>MAC / Client</th>
                                <th>NAS Gateway</th>
                                <th>Duration</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testData['active_sessions'] as $s): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($s['framedipaddress'] ?: '—') ?></code></td>
                                <td><code><?= htmlspecialchars($s['mac_formatted'] ?: '—') ?></code></td>
                                <td><?= htmlspecialchars($s['nasipaddress'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($s['duration_text']) ?></td>
                                <td class="text-end">
                                    <form method="POST" action="rules.php" class="d-inline" onsubmit="return confirm('Disconnect device <?= htmlspecialchars($s['framedipaddress'] ?: $s['mac_formatted']) ?>?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="disconnect_session">
                                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($s['acctsessionid']) ?>">
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($testData['username']) ?>">
                                        <input type="hidden" name="nas_ip" value="<?= htmlspecialchars($s['nasipaddress']) ?>">
                                        <input type="hidden" name="framed_ip" value="<?= htmlspecialchars($s['framedipaddress']) ?>">
                                        <input type="hidden" name="mac" value="<?= htmlspecialchars($s['callingstationid']) ?>">
                                        <input type="hidden" name="return_url" value="rules.php?tab=tester&test_user=<?= urlencode($testData['username']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1.5" title="Disconnect device" style="font-size:0.75rem">
                                            <i class="bi bi-power"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: EDIT SINGLE ACCOUNT RULE                                        -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="singleRuleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content">
            <form method="POST" action="rules.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_user_rule">
                <input type="hidden" name="username" id="modalSingleUsername">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentReturnUrl) ?>">

                <div class="modal-header border-bottom py-3">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-shield-lock text-primary me-2"></i>Set Device Rule: <span id="modalSingleUserLabel" class="text-primary"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Allowed Simultaneous Devices (<code>Simultaneous-Use</code>)</label>
                        <select name="limit" id="modalSingleLimitSelect" class="form-select" onchange="toggleCustomLimitInput(this.value, 'modalSingleCustomWrap')">
                            <option value="1">1 Device (Strict Single-Device Policy)</option>
                            <option value="2">2 Devices</option>
                            <option value="3">3 Devices</option>
                            <option value="4">4 Devices</option>
                            <option value="5">5 Devices</option>
                            <option value="custom">Custom Limit...</option>
                            <option value="0">Unlimited (No Limit)</option>
                            <option value="-1">Reset to Group Policy (Remove Override)</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="modalSingleCustomWrap">
                        <label class="form-label fw-semibold small">Enter Custom Integer Limit</label>
                        <input type="number" min="1" max="999" class="form-control" id="modalSingleCustomVal" placeholder="e.g. 10" oninput="document.getElementById('modalSingleLimitSelect').value = this.value">
                    </div>

                    <div class="small text-muted">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        When a user attempts to log in from more devices than allowed, FreeRADIUS rejects authentication immediately.
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Save Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: BULK SET CUSTOM LIMIT                                            -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="bulkCustomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-check2-square text-primary me-2"></i>Set Custom Device Limit for Selected
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Enter Device Limit per Account</label>
                    <input type="number" min="1" max="999" class="form-control" id="bulkCustomLimitInput" value="5" placeholder="e.g. 5">
                    <div class="form-text">Will be applied to all checked accounts.</div>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary px-3 shadow-sm" onclick="submitBulkCustomLimit()">Apply Limit</button>
            </div>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: GROUP POLICY MODAL                                               -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="groupPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content">
            <form method="POST" action="rules.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_group_rule">
                <input type="hidden" name="groupname" id="modalGroupname">

                <div class="modal-header border-bottom py-3">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-collection text-primary me-2"></i>Group Policy: <span id="modalGroupLabel" class="text-primary"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Simultaneous Device Limit (<code>radgroupcheck</code>)</label>
                        <select name="group_limit" id="modalGroupLimitSelect" class="form-select">
                            <option value="1">1 Device Limit</option>
                            <option value="2">2 Devices Limit</option>
                            <option value="3">3 Devices Limit</option>
                            <option value="4">4 Devices Limit</option>
                            <option value="5">5 Devices Limit</option>
                            <option value="10">10 Devices Limit</option>
                            <option value="0">Unlimited (Remove Group Limit)</option>
                        </select>
                    </div>
                    <div class="small text-muted">
                        All subscribers assigned to this group will inherit this device limit unless an individual account override is defined.
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Save Group Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: GLOBAL DEFAULT RULE                                              -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="globalDefaultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content">
            <form method="POST" action="rules.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_global_default">

                <div class="modal-header border-bottom py-3">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-gear text-primary me-2"></i>Global Default Device Policy
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">System Default Device Limit</label>
                        <select name="default_limit" class="form-select">
                            <option value="0" <?= $globalDefaultLimit === 0 ? 'selected' : '' ?>>0 &mdash; Unlimited (No Default Limit)</option>
                            <option value="1" <?= $globalDefaultLimit === 1 ? 'selected' : '' ?>>1 Device (Single Device Default)</option>
                            <option value="2" <?= $globalDefaultLimit === 2 ? 'selected' : '' ?>>2 Devices</option>
                            <option value="3" <?= $globalDefaultLimit === 3 ? 'selected' : '' ?>>3 Devices</option>
                            <option value="5" <?= $globalDefaultLimit === 5 ? 'selected' : '' ?>>5 Devices</option>
                        </select>
                        <div class="form-text">Used for new registrations or accounts without explicit group/user limits.</div>
                    </div>

                    <div class="form-check p-3 bg-light rounded border mb-2">
                        <input class="form-check-input" type="checkbox" name="apply_to_existing" value="1" id="chkApplyExisting">
                        <label class="form-check-label small fw-semibold text-dark" for="chkApplyExisting">
                            Apply this limit now to all existing accounts currently without rules
                        </label>
                        <div class="text-muted small mt-1" style="font-size:0.75rem;">
                            Inserts <code>Simultaneous-Use</code> for accounts that currently have no individual device rules.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Save Global Default</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: AJAX CONNECTED DEVICES INSPECTION                                -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="userDevicesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-broadcast text-primary me-2"></i>Active Connected Devices: <span id="modalDeviceUsername" class="text-primary"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3" id="modalDeviceContent">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div>Loading connected devices...</div>
                </div>
            </div>
            <div class="modal-footer border-top py-2 d-flex justify-content-between">
                <form method="POST" action="rules.php" id="modalDisconnectAllForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="disconnect_user">
                    <input type="hidden" name="username" id="modalDisconnectAllUser">
                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentReturnUrl) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" id="btnModalDisconnectAll">
                        <i class="bi bi-power me-1"></i>Disconnect All Devices
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Form helper for quick single-click dropdown submissions -->
<form method="POST" action="rules.php" id="quickRuleForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="set_user_rule">
    <input type="hidden" name="username" id="quickRuleUsername">
    <input type="hidden" name="limit" id="quickRuleLimit">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentReturnUrl) ?>">
</form>

<form method="POST" action="rules.php" id="quickDisconnectForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="disconnect_user">
    <input type="hidden" name="username" id="quickDisconnectUsername">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentReturnUrl) ?>">
</form>

<?php
$extra_js = '<script>
// Check all checkboxes in account table
const checkAll = document.getElementById("checkAllUsers");
const userCheckboxes = document.querySelectorAll(".user-checkbox");
const bulkBtn = document.getElementById("btnBulkActions");
const countBadge = document.getElementById("selectedCountBadge");

function updateBulkState() {
    let checkedCount = 0;
    userCheckboxes.forEach(cb => { if (cb.checked) checkedCount++; });
    if (countBadge) countBadge.textContent = checkedCount + " selected";
    if (bulkBtn) {
        if (checkedCount > 0) {
            bulkBtn.classList.remove("disabled");
        } else {
            bulkBtn.classList.add("disabled");
        }
    }
}

if (checkAll) {
    checkAll.addEventListener("change", function() {
        userCheckboxes.forEach(cb => cb.checked = checkAll.checked);
        updateBulkState();
    });
}
userCheckboxes.forEach(cb => {
    cb.addEventListener("change", updateBulkState);
});

// Quick single-click submit
function submitQuickRule(username, limit) {
    document.getElementById("quickRuleUsername").value = username;
    document.getElementById("quickRuleLimit").value = limit;
    document.getElementById("quickRuleForm").submit();
}

function submitDisconnectUser(username, count) {
    if (confirm("Disconnect all " + count + " active devices for user " + username + "?")) {
        document.getElementById("quickDisconnectUsername").value = username;
        document.getElementById("quickDisconnectForm").submit();
    }
}

// Single custom modal
function openSingleCustomModal(username, currentVal) {
    document.getElementById("modalSingleUsername").value = username;
    document.getElementById("modalSingleUserLabel").textContent = username;
    const sel = document.getElementById("modalSingleLimitSelect");
    const wrap = document.getElementById("modalSingleCustomWrap");
    const customInp = document.getElementById("modalSingleCustomVal");

    if ([0, 1, 2, 3, 4, 5, -1].includes(currentVal)) {
        sel.value = currentVal;
        wrap.classList.add("d-none");
    } else {
        sel.value = "custom";
        wrap.classList.remove("d-none");
        customInp.value = currentVal > 0 ? currentVal : "";
    }
    new bootstrap.Modal(document.getElementById("singleRuleModal")).show();
}

function toggleCustomLimitInput(val, wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    if (val === "custom") {
        wrap.classList.remove("d-none");
        const inp = wrap.querySelector("input");
        if (inp) inp.focus();
    } else {
        wrap.classList.add("d-none");
    }
}

// Bulk custom modal
function openBulkCustomModal() {
    new bootstrap.Modal(document.getElementById("bulkCustomModal")).show();
}

function submitBulkCustomLimit() {
    const val = parseInt(document.getElementById("bulkCustomLimitInput").value, 10);
    if (isNaN(val) || val < 1) {
        alert("Please enter a valid positive integer limit.");
        return;
    }
    const form = document.getElementById("bulkRulesForm");
    const hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = "bulk_limit";
    hidden.value = val;
    form.appendChild(hidden);
    form.submit();
}

// Group policy modal
function openGroupPolicyModal(groupname, currentVal) {
    document.getElementById("modalGroupname").value = groupname;
    document.getElementById("modalGroupLabel").textContent = groupname;
    document.getElementById("modalGroupLimitSelect").value = currentVal;
    new bootstrap.Modal(document.getElementById("groupPolicyModal")).show();
}

// Active Devices Modal (AJAX)
function inspectUserDevices(username) {
    document.getElementById("modalDeviceUsername").textContent = username;
    document.getElementById("modalDisconnectAllUser").value = username;
    const content = document.getElementById("modalDeviceContent");
    content.innerHTML = \'<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div>Loading connected devices...</div></div>\';

    const modal = new bootstrap.Modal(document.getElementById("userDevicesModal"));
    modal.show();

    fetch("rules.php?ajax=user_devices&username=" + encodeURIComponent(username))
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.devices || data.devices.length === 0) {
                content.innerHTML = \'<div class="alert alert-light border text-center text-muted py-4 mb-0"><i class="bi bi-info-circle me-1"></i>No active connected devices found for this account right now.</div>\';
                document.getElementById("btnModalDisconnectAll").style.display = "none";
                return;
            }
            document.getElementById("btnModalDisconnectAll").style.display = "";
            let html = \'<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>IP Address</th><th>MAC Address</th><th>NAS Gateway</th><th>Duration</th><th>Traffic</th><th class="text-end">Action</th></tr></thead><tbody>\';
            data.devices.forEach(d => {
                html += "<tr>";
                html += "<td><code>" + (d.framedipaddress || "—") + "</code></td>";
                html += "<td><code>" + (d.mac_formatted || "—") + "</code></td>";
                html += "<td>" + (d.nasipaddress || "—") + "</td>";
                html += "<td>" + d.duration_text + "</td>";
                html += "<td><span class=\"text-success\">↓" + d.download_text + "</span> / <span class=\"text-primary\">↑" + d.upload_text + "</span></td>";
                html += "<td class=\"text-end\">";
                html += "<form method=\"POST\" action=\"rules.php\" class=\"d-inline\" onsubmit=\"return confirm(\'Disconnect this specific device (" + (d.framedipaddress || d.mac_formatted) + ")?\');\">";
                html += \'' . addslashes(csrfField()) . '\';
                html += "<input type=\"hidden\" name=\"action\" value=\"disconnect_session\">";
                html += "<input type=\"hidden\" name=\"session_id\" value=\"" + (d.acctsessionid || "") + "\">";
                html += "<input type=\"hidden\" name=\"username\" value=\"" + encodeURIComponent(username) + "\">";
                html += "<input type=\"hidden\" name=\"nas_ip\" value=\"" + (d.nasipaddress || "") + "\">";
                html += "<input type=\"hidden\" name=\"framed_ip\" value=\"" + (d.framedipaddress || "") + "\">";
                html += "<input type=\"hidden\" name=\"mac\" value=\"" + (d.callingstationid || "") + "\">";
                html += "<button type=\"submit\" class=\"btn btn-sm btn-outline-danger py-0 px-1.5\" title=\"Disconnect device\"><i class=\"bi bi-power\"></i> Disconnect</button>";
                html += "</form>";
                html += "</td>";
                html += "</tr>";
            });
            html += "</tbody></table></div>";
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = \'<div class="alert alert-danger py-3">Failed to load device sessions: \' + err.message + \'</div>\';
        });
}
</script>';

include __DIR__ . '/includes/footer.php';

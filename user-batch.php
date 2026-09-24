<?php
require_once __DIR__ . '/auth.php';
requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: users.php');
    exit;
}

verifyCsrf();

$action = trim($_POST['batch_action'] ?? '');
$usernames = $_POST['usernames'] ?? [];
$targetGroup = trim($_POST['target_group'] ?? '');
$returnUrl = trim($_POST['return_url'] ?? 'users.php');

if (empty($returnUrl) || str_contains($returnUrl, '://') || (!str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, 'user'))) {
    $returnUrl = 'users.php';
}

if (!is_array($usernames) || empty($usernames)) {
    flash('warning', 'No users were selected for the batch action.');
    header('Location: ' . $returnUrl);
    exit;
}

// Filter usernames array for strings only
$usernames = array_values(array_unique(array_filter(array_map('trim', $usernames))));
$totalSelected = count($usernames);

if ($totalSelected === 0) {
    flash('warning', 'No valid usernames selected.');
    header('Location: ' . $returnUrl);
    exit;
}

$db = getDB();
$placeholders = implode(',', array_fill(0, $totalSelected, '?'));

// 1. Export Selected Users to CSV
if ($action === 'export') {
    $hasUserinfo = dbTableExists('userinfo');
    $uiCols = $hasUserinfo ? "MAX(ui.firstname) AS firstname, MAX(ui.lastname) AS lastname, MAX(ui.department) AS department, MAX(ui.email) AS email," : "";
    $uiJoin = $hasUserinfo ? "LEFT JOIN userinfo ui ON ui.username = rc.username" : "";

    $stmt = $db->prepare("SELECT rc.username,
        COALESCE(
            MAX(CASE WHEN rc.attribute='Cleartext-Password' THEN rc.value END),
            MAX(CASE WHEN rc.attribute='User-Password' THEN rc.value END)
        ) AS password,
        MAX(CASE WHEN rc.attribute='Auth-Type' AND rc.value='Reject' THEN 1 ELSE 0 END) AS is_disabled,
        GROUP_CONCAT(DISTINCT rug.groupname ORDER BY rug.priority SEPARATOR ', ') AS groupname,
        $uiCols
        rc.username AS dummy
      FROM radcheck rc
      $uiJoin
      LEFT JOIN radusergroup rug ON rug.username = rc.username
      WHERE rc.username IN ($placeholders)
      GROUP BY rc.username
      ORDER BY rc.username");
    $stmt->execute($usernames);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="selected_users_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Microsoft Excel compatibility
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Username', 'Password', 'Groups', 'Status', 'First Name', 'Last Name', 'Department', 'Email']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['username'],
            $r['password'] ?? '',
            $r['groupname'] ?? '',
            !empty($r['is_disabled']) ? 'Disabled' : 'Active',
            $r['firstname'] ?? '',
            $r['lastname'] ?? '',
            $r['department'] ?? '',
            $r['email'] ?? ''
        ]);
    }
    fclose($out);
    auditLog('user.batch_export', "$totalSelected users", "Exported to CSV");
    exit;
}

// 2. Enable Selected Users
if ($action === 'enable') {
    dbQuery("DELETE FROM radcheck WHERE username IN ($placeholders) AND attribute = 'Auth-Type'", $usernames);
    auditLog('user.batch_enable', "$totalSelected users", implode(', ', array_slice($usernames, 0, 10)) . ($totalSelected > 10 ? '...' : ''));
    flash('success', "Successfully enabled $totalSelected selected user(s).");
    header('Location: ' . $returnUrl);
    exit;
}

// 3. Disable Selected Users
if ($action === 'disable') {
    // Find who is already disabled to avoid duplicates
    $checkStmt = $db->prepare("SELECT username FROM radcheck WHERE username IN ($placeholders) AND attribute = 'Auth-Type' AND value = 'Reject'");
    $checkStmt->execute($usernames);
    $already = array_flip($checkStmt->fetchAll(PDO::FETCH_COLUMN));

    $insertStmt = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
    $disabledCount = 0;
    foreach ($usernames as $u) {
        if (!isset($already[$u])) {
            $insertStmt->execute([$u]);
            $disabledCount++;
        }
    }
    auditLog('user.batch_disable', "$totalSelected users", "Newly disabled: $disabledCount users");
    flash('warning', "Successfully disabled $totalSelected selected user(s) (Auth-Type := Reject).");
    header('Location: ' . $returnUrl);
    exit;
}

// 4. Delete Selected Users
if ($action === 'delete') {
    dbQuery("DELETE FROM radcheck WHERE username IN ($placeholders)", $usernames);
    dbQuery("DELETE FROM radreply WHERE username IN ($placeholders)", $usernames);
    dbQuery("DELETE FROM radusergroup WHERE username IN ($placeholders)", $usernames);
    if (dbTableExists('userinfo')) {
        dbQuery("DELETE FROM userinfo WHERE username IN ($placeholders)", $usernames);
    }
    auditLog('user.batch_delete', "$totalSelected users", implode(', ', array_slice($usernames, 0, 10)) . ($totalSelected > 10 ? '...' : ''));
    flash('danger', "Permanently deleted $totalSelected selected user(s) from all tables.");
    header('Location: ' . $returnUrl);
    exit;
}

// 5. Change Group for Selected Users
if ($action === 'change_group') {
    if (empty($targetGroup)) {
        flash('danger', 'Please choose a target group for the batch group assignment.');
        header('Location: ' . $returnUrl);
        exit;
    }

    dbQuery("DELETE FROM radusergroup WHERE username IN ($placeholders)", $usernames);
    $ins = $db->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
    foreach ($usernames as $u) {
        $ins->execute([$u, $targetGroup]);
    }
    auditLog('user.batch_group', "$totalSelected users", "Assigned to group: $targetGroup");
    flash('success', "Assigned $totalSelected selected user(s) to group <strong>" . sanitize($targetGroup) . "</strong>.");
    header('Location: ' . $returnUrl);
    exit;
}

flash('danger', 'Invalid batch action specified.');
header('Location: ' . $returnUrl);
exit;


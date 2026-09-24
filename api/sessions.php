<?php
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $username = trim($_GET['username'] ?? '');
    $nasIp    = trim($_GET['nasipaddress'] ?? '');
    $limit    = min(200, max(1, (int)($_GET['limit'] ?? 50)));

    $where = ["ra.acctstoptime IS NULL"];
    $params = [];

    if ($username !== '') {
        $where[] = "ra.username = ?";
        $params[] = $username;
    }

    if ($nasIp !== '') {
        $where[] = "ra.nasipaddress = ?";
        $params[] = $nasIp;
    }

    $whereSql = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT ra.radacctid,
               ra.acctsessionid,
               ra.acctuniqueid,
               ra.username,
               ra.nasipaddress,
               COALESCE(n.shortname, ra.nasipaddress) AS nas_shortname,
               ra.framedipaddress,
               ra.callingstationid,
               ra.acctstarttime,
               ra.acctinputoctets,
               ra.acctoutputoctets,
               (ra.acctinputoctets + ra.acctoutputoctets) AS total_bytes,
               TIMESTAMPDIFF(SECOND, ra.acctstarttime, NOW()) AS current_duration_seconds
        FROM radacct ra
        LEFT JOIN nas n ON n.nasname = ra.nasipaddress
        WHERE $whereSql
        ORDER BY ra.radacctid DESC
        LIMIT $limit
    ");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiSuccess($sessions, [
        'count' => count($sessions),
        'limit' => $limit,
    ]);
}

if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? $_POST['action'] ?? '';
    $id = (int)($input['id'] ?? $_POST['id'] ?? 0);

    if ($action !== 'disconnect' && $action !== 'kick') {
        apiError("Invalid action. Supported actions: 'disconnect'.", 422);
    }

    if ($id <= 0) {
        apiError("Invalid session ID.", 422);
    }

    $stmt = $db->prepare("SELECT * FROM radacct WHERE radacctid = ? AND acctstoptime IS NULL");
    $stmt->execute([$id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        apiError("Active session #$id not found or already closed.", 404);
    }

    // Mark session as terminated in radacct
    $stopStmt = $db->prepare("
        UPDATE radacct
        SET acctstoptime = NOW(),
            acctterminatecause = 'Admin-Reset',
            acctsessiontime = GREATEST(0, TIMESTAMPDIFF(SECOND, acctstarttime, NOW()))
        WHERE radacctid = ?
    ");
    $stopStmt->execute([$id]);

    auditLog('api_session_disconnect', $session['username'], "Disconnected session #$id via REST API (NAS: {$session['nasipaddress']})");

    apiSuccess([
        'radacctid'     => $id,
        'username'      => $session['username'],
        'nasipaddress'  => $session['nasipaddress'],
        'status'        => 'disconnected',
        'message'       => "Session #$id for user '{$session['username']}' terminated successfully."
    ]);
}

apiError("Method '$method' not allowed.", 405);

<?php
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    apiError("Method '$method' not allowed.", 405);
}

$db = getDB();

$from     = trim($_GET['from'] ?? '');
$to       = trim($_GET['to'] ?? '');
$username = trim($_GET['username'] ?? '');
$nasIp    = trim($_GET['nasipaddress'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
$offset   = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($from !== '') {
    $where[] = "ra.acctstarttime >= ?";
    $params[] = "$from 00:00:00";
}
if ($to !== '') {
    $where[] = "ra.acctstarttime <= ?";
    $params[] = "$to 23:59:59";
}
if ($username !== '') {
    $where[] = "ra.username = ?";
    $params[] = $username;
}
if ($nasIp !== '') {
    $where[] = "ra.nasipaddress = ?";
    $params[] = $nasIp;
}

$whereSql = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM radacct ra WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch records
$dataStmt = $db->prepare("
    SELECT ra.radacctid,
           ra.acctsessionid,
           ra.username,
           ra.nasipaddress,
           COALESCE(n.shortname, ra.nasipaddress) AS nas_shortname,
           ra.framedipaddress,
           ra.callingstationid,
           ra.acctstarttime,
           ra.acctstoptime,
           ra.acctsessiontime,
           ra.acctinputoctets,
           ra.acctoutputoctets,
           (ra.acctinputoctets + ra.acctoutputoctets) AS total_bytes,
           ra.acctterminatecause
    FROM radacct ra
    LEFT JOIN nas n ON n.nasname = ra.nasipaddress
    WHERE $whereSql
    ORDER BY ra.radacctid DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

apiSuccess($rows, [
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
    'pages'    => max(1, (int)ceil($total / $perPage)),
]);

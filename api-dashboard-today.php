<?php
/**
 * CENDANA — Async Real-Time Telemetry API for Today's Charts
 * Fast indexed JSON endpoint for dynamic, seamless background chart updates.
 * Releases session lock immediately (< 0.1ms) to eliminate browser lock contention.
 */

require_once __DIR__ . '/auth.php';
requireLogin();

// Close session immediately so other concurrent requests or tabs are never blocked
session_write_close();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    $todayDate = date('Y-m-d');

    $requestedDate = trim($_GET['date'] ?? '');
    if ($requestedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
        $anchorDay = $requestedDate;
        $isHistoricalDay = ($anchorDay !== $todayDate);
    } else {
        $anchorDay = $todayDate;
        $isHistoricalDay = false;
    }
    $prevDay = date('Y-m-d', strtotime('-1 day', strtotime($anchorDay)));

    // 1. Anchor Day Traffic (Fast index scan, < 1ms)
    $trafficStmt = $db->prepare("SELECT
        COALESCE(SUM(acctinputoctets), 0)  AS upload,
        COALESCE(SUM(acctoutputoctets), 0) AS download
      FROM radacct WHERE acctstarttime >= ? AND acctstarttime < ? + INTERVAL 1 DAY");
    $trafficStmt->execute(["$anchorDay 00:00:00", "$anchorDay 00:00:00"]);
    $trafficRow = $trafficStmt->fetch();

    $uploadToday   = formatBytes($trafficRow['upload'] ?? 0);
    $downloadToday = formatBytes($trafficRow['download'] ?? 0);

    // 2. Active Sessions Count (Accurate deduplicated online accounts & devices)
    $activeInfo     = getTrulyActiveOnlineAccounts($db);
    $onlineAccounts = $activeInfo['total_accounts'];
    $onlineDevices  = $activeInfo['total_devices'];
    $activeSessions = $onlineAccounts;

    // 3. Combined Hourly Sessions & Hourly Bandwidth (Single indexed scan, < 1ms)
    $hourlyStmt = $db->prepare("
        SELECT HOUR(acctstarttime) AS h,
               COUNT(*) AS c,
               COALESCE(SUM(acctinputoctets), 0) AS up,
               COALESCE(SUM(acctoutputoctets), 0) AS down
        FROM radacct
        WHERE acctstarttime >= ? AND acctstarttime <= ?
        GROUP BY HOUR(acctstarttime)
    ");
    $hourlyStmt->execute(["$anchorDay 00:00:00", "$anchorDay 23:59:59"]);
    $todayHourMap = [];
    $hourlyBwMap  = [];
    while ($r = $hourlyStmt->fetch(PDO::FETCH_ASSOC)) {
        $h = (int)$r['h'];
        $todayHourMap[$h] = (int)$r['c'];
        $hourlyBwMap[$h]  = $r;
    }

    // Previous day comparison
    $prevHoursStmt = $db->prepare("
        SELECT HOUR(acctstarttime) AS h, COUNT(*) AS c
        FROM radacct
        WHERE acctstarttime >= ? AND acctstarttime <= ?
        GROUP BY HOUR(acctstarttime)
    ");
    $prevHoursStmt->execute(["$prevDay 00:00:00", "$prevDay 23:59:59"]);
    $prevHourMap = $prevHoursStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $hourlyLabels     = [];
    $hourlyToday      = [];
    $hourlyPrev       = [];
    $hourlyUploadMB   = [];
    $hourlyDownloadMB = [];
    for ($h = 0; $h < 24; $h++) {
        $hourlyLabels[]     = sprintf('%02d:00', $h);
        $hourlyToday[]      = (int)($todayHourMap[$h] ?? 0);
        $hourlyPrev[]       = (int)($prevHourMap[$h] ?? 0);
        $hourlyUploadMB[]   = round(($hourlyBwMap[$h]['up'] ?? 0) / (1024 * 1024), 2);
        $hourlyDownloadMB[] = round(($hourlyBwMap[$h]['down'] ?? 0) / (1024 * 1024), 2);
    }

    // 4. Hourly Authentication Decisions (Accept vs Reject) (< 1ms)
    $hourlyAccepts = array_fill(0, 24, 0);
    $hourlyRejects = array_fill(0, 24, 0);
    $latestPostAuthId = (int)($db->query("SELECT id FROM radpostauth ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
    $authIdBound = max(1, $latestPostAuthId - 500000);
    $hourlyAuthStmt = $db->prepare("
        SELECT HOUR(authdate) AS h,
               SUM(CASE WHEN reply = 'Access-Accept' THEN 1 ELSE 0 END) AS accepts,
               SUM(CASE WHEN reply != 'Access-Accept' THEN 1 ELSE 0 END) AS rejects
        FROM radpostauth
        WHERE " . (!$isHistoricalDay && $latestPostAuthId > 0 ? "id >= ? AND " : "") . "authdate >= ? AND authdate <= ?
        GROUP BY HOUR(authdate)
    ");
    $hourlyAuthParams = (!$isHistoricalDay && $latestPostAuthId > 0)
        ? [$authIdBound, "$anchorDay 00:00:00", "$anchorDay 23:59:59"]
        : ["$anchorDay 00:00:00", "$anchorDay 23:59:59"];
    $hourlyAuthStmt->execute($hourlyAuthParams);
    while ($r = $hourlyAuthStmt->fetch(PDO::FETCH_ASSOC)) {
        $h = (int)$r['h'];
        if ($h >= 0 && $h < 24) {
            $hourlyAccepts[$h] = (int)$r['accepts'];
            $hourlyRejects[$h] = (int)$r['rejects'];
        }
    }
    $authCountDisplay = array_sum($hourlyAccepts) + array_sum($hourlyRejects);

    // 5. Top 5 NAS Devices by Bandwidth (< 1ms)
    $topNasTodayStmt = $db->prepare("
        SELECT nasipaddress,
               COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_bytes
        FROM radacct
        WHERE acctstarttime >= ? AND acctstarttime <= ?
        GROUP BY nasipaddress
        ORDER BY total_bytes DESC
        LIMIT 5
    ");
    $topNasTodayStmt->execute(["$anchorDay 00:00:00", "$anchorDay 23:59:59"]);
    $topNas = $topNasTodayStmt->fetchAll(PDO::FETCH_ASSOC);

    $nasTodayLabels = [];
    $nasTodayValues = [];
    if (!empty($topNas)) {
        $nasIps = array_column($topNas, 'nasipaddress');
        $placeholders = implode(',', array_fill(0, count($nasIps), '?'));
        $nasMapStmt = $db->prepare("SELECT nasname, shortname FROM nas WHERE nasname IN ($placeholders)");
        $nasMapStmt->execute($nasIps);
        $shortMap = $nasMapStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        foreach ($topNas as $nr) {
            $nasTodayLabels[] = $shortMap[$nr['nasipaddress']] ?? $nr['nasipaddress'];
            $nasTodayValues[] = round($nr['total_bytes'] / (1024 * 1024), 2);
        }
    }

    echo json_encode([
        'success'          => true,
        'anchorDay'        => $anchorDay,
        'prevDay'          => $prevDay,
        'isHistoricalDay'  => $isHistoricalDay,
        'uploadToday'      => $uploadToday,
        'downloadToday'    => $downloadToday,
        'activeSessions'   => $activeSessions,
        'onlineAccounts'   => $onlineAccounts,
        'onlineDevices'    => $onlineDevices,
        'authCountDisplay' => $authCountDisplay,
        'hourlyLabels'     => $hourlyLabels,
        'hourlyToday'      => $hourlyToday,
        'hourlyPrev'       => $hourlyPrev,
        'hourlyUploadMB'   => $hourlyUploadMB,
        'hourlyDownloadMB' => $hourlyDownloadMB,
        'hourlyAccepts'    => $hourlyAccepts,
        'hourlyRejects'    => $hourlyRejects,
        'nasTodayLabels'   => $nasTodayLabels,
        'nasTodayValues'   => $nasTodayValues,
        'refreshedAt'      => date('H:i:s'),
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}


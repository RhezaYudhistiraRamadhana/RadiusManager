<?php
/**
 * CENDANA — NAS Device Async Health Check API
 * Probes NAS reachable status asynchronously without blocking UI navigation.
 */
require_once __DIR__ . '/auth.php';
requireLogin();
if (!hasRole('superadmin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$nas = $db->query("SELECT id, nasname, shortname, type FROM nas ORDER BY shortname, nasname")->fetchAll(PDO::FETCH_ASSOC);

$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$force = isset($_GET['force']) && $_GET['force'] === '1';

// Read existing session cache
$cache = $_SESSION['nas_status_cache'] ?? [];
if (!is_array($cache)) {
    $cache = [];
}

// Release session lock immediately so other browser tabs/requests don't wait
session_write_close();

$results = [];

foreach ($nas as $n) {
    $id = (int)$n['id'];
    $host = trim($n['nasname'] ?? '');

    // Subnet / Wildcard check
    if (str_contains($host, '/') || $host === '0.0.0.0') {
        $results[$id] = [
            'type'    => 'wildcard',
            'status'  => 'wildcard',
            'label'   => 'Subnet / Any',
            'latency' => 0
        ];
        continue;
    }

    // Check cache if not forcing refresh (cache valid for 120s)
    if (!$force && isset($cache[$host]) && isset($cache[$host]['time']) && (time() - $cache[$host]['time'] < 120)) {
        $results[$id] = $cache[$host];
        continue;
    }

    // Fast socket / UDP ping probe
    $t0 = microtime(true);
    $online = false;
    $latency = 0;

    // 1. Try fast UDP RADIUS socket probe (50ms timeout)
    try {
        $fp = @stream_socket_client("udp://$host:1812", $errno, $errstr, 0.05, STREAM_CLIENT_CONNECT);
        if ($fp) {
            fclose($fp);
            $online = true;
        }
    } catch (\Throwable $e) {}

    // 2. If not detected via UDP, try fast ICMP ping
    if (!$online) {
        $cmd = $isWin ? "ping -n 1 -w 200 " . escapeshellarg($host) : "ping -c 1 -W 1 " . escapeshellarg($host);
        @exec($cmd, $out, $ret);
        if ($ret === 0) {
            $online = true;
        }
    }

    $t1 = microtime(true);
    $latency = max(0.5, round(($t1 - $t0) * 1000, 1));

    if ($online) {
        $st = [
            'type'    => 'host',
            'status'  => 'online',
            'label'   => 'Online',
            'latency' => $latency,
            'time'    => time()
        ];
    } else {
        $st = [
            'type'    => 'host',
            'status'  => 'offline',
            'label'   => 'Offline',
            'latency' => $latency,
            'time'    => time()
        ];
    }

    $cache[$host] = $st;
    $results[$id] = $st;
}

// Re-open session briefly to update cache
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
    $_SESSION['nas_status_cache'] = $cache;
    session_write_close();
}

echo json_encode([
    'success'  => true,
    'statuses' => $results,
    'timestamp'=> time()
]);

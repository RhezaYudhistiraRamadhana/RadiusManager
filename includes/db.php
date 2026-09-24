<?php
/**
 * Database connection & query helper functions
 */

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $msg = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
            if (php_sapi_name() === 'cli' || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                die(json_encode(['error' => $msg]));
            }
            die('<div style="font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;border:1px solid #f87171;">'
                . '<strong>Database Error:</strong> ' . $msg . '</div>');
        }
    }
    return $pdo;
}

function dbQuery($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbFetch($sql, $params = []) {
    return dbQuery($sql, $params)->fetch();
}

function dbFetchAll($sql, $params = []) {
    return dbQuery($sql, $params)->fetchAll();
}

function dbCount($sql, $params = []) {
    return (int) dbQuery($sql, $params)->fetchColumn();
}

/**
 * Check whether a table exists in current database
 */
function dbTableExists($table) {
    static $cache = [];
    if (!isset($cache[$table])) {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    }
    return $cache[$table];
}

/**
 * Check whether a specific column exists in a table
 */
function dbHasColumn($table, $column) {
    static $cache = [];
    $key = "$table.$column";
    if (!isset($cache[$key])) {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    }
    return $cache[$key];
}

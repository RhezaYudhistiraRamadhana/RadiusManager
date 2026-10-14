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
            $pdo->exec("SET time_zone = '+07:00'");
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
 * Check whether a table exists in current database (cached in-memory, 0.001ms)
 */
function dbTableExists($table) {
    static $tableMap = null;
    if ($tableMap === null) {
        try {
            $tables = getDB()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $tableMap = array_flip(array_map('strtolower', $tables));
        } catch (Throwable $e) {
            $tableMap = [];
        }
    }
    return isset($tableMap[strtolower($table)]);
}

/**
 * Check whether a specific column exists in a table (fast SHOW COLUMNS, cached per request)
 */
function dbHasColumn($table, $column) {
    static $columnCache = [];
    $key = strtolower("$table.$column");
    if (!isset($columnCache[$key])) {
        if (!dbTableExists($table)) {
            $columnCache[$key] = false;
            return false;
        }
        try {
            $safeCol = str_replace('`', '', $column);
            $stmt = getDB()->query("SHOW COLUMNS FROM `$table` LIKE " . getDB()->quote($safeCol));
            $columnCache[$key] = ($stmt && $stmt->fetch() !== false);
        } catch (Throwable $e) {
            $columnCache[$key] = false;
        }
    }
    return $columnCache[$key];
}

/**
 * Self-healing performance indexer:
 * Ensures FreeRADIUS tables (radacct, radpostauth) have essential composite indexes.
 * Checks are throttled to run at most once per 24 hours per server environment.
 */
function ensurePerformanceIndexes(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $cacheFile = sys_get_temp_dir() . '/cendana_idx_' . md5((defined('DB_HOST') ? DB_HOST : '') . (defined('DB_NAME') ? DB_NAME : ''));
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
        return;
    }

    try {
        $db = getDB();
        // 1. Check radacct indexes
        if (dbTableExists('radacct')) {
            $stmt = $db->query("SHOW INDEX FROM `radacct`");
            $radacctIdx = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $radacctIdx[$r['Key_name']] = true;
            }

            if (!isset($radacctIdx['idx_callingstation'])) {
                @$db->exec("ALTER TABLE `radacct` ADD INDEX `idx_callingstation` (`callingstationid`)");
            }
            if (!isset($radacctIdx['idx_stop_start'])) {
                @$db->exec("ALTER TABLE `radacct` ADD INDEX `idx_stop_start` (`acctstoptime`, `acctstarttime`)");
            }
            if (!isset($radacctIdx['idx_user_stoptime'])) {
                @$db->exec("ALTER TABLE `radacct` ADD INDEX `idx_user_stoptime` (`username`, `acctstoptime`)");
            }
        }

        // 2. Check radpostauth indexes
        if (dbTableExists('radpostauth')) {
            $stmt = $db->query("SHOW INDEX FROM `radpostauth`");
            $authIdx = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $authIdx[$r['Key_name']] = true;
            }

            if (!isset($authIdx['idx_authdate'])) {
                @$db->exec("ALTER TABLE `radpostauth` ADD INDEX `idx_authdate` (`authdate`)");
            }
        }

        @touch($cacheFile);
    } catch (Throwable $e) {
        @touch($cacheFile);
    }
}

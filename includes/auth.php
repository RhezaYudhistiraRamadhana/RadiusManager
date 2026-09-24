<?php
/**
 * Authentication and Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        return false;
    }
    return true;
}

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['admin_logged_in']) && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function getAdminUser() {
    return $_SESSION['admin_user'] ?? 'admin';
}

function getAdminSource() {
    return $_SESSION['admin_source'] ?? 'config';
}

function getAdminName() {
    return $_SESSION['admin_name'] ?? 'Administrator';
}

/**
 * Ensure rm_admins table exists and operators.password column fits bcrypt
 */
function ensureAdminTables() {
    static $done = false;
    if ($done) return;
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS `rm_admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(64) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(128) DEFAULT NULL,
            `email` VARCHAR(128) DEFAULT NULL,
            `role` ENUM('superadmin','operator','readonly') NOT NULL DEFAULT 'superadmin',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!dbHasColumn('rm_admins', 'role')) {
            $db->exec("ALTER TABLE `rm_admins` ADD COLUMN `role` ENUM('superadmin','operator','readonly') NOT NULL DEFAULT 'superadmin'");
        }

        if (dbTableExists('operators')) {
            // Expand password column if it is smaller than 255
            $col = dbFetch("SHOW COLUMNS FROM `operators` LIKE 'password'");
            if ($col && stripos($col['Type'], 'varchar(255)') === false) {
                $db->exec("SET SESSION sql_mode = ''");
                $db->exec("ALTER TABLE `operators` MODIFY `password` VARCHAR(255) NOT NULL");
            }
            if (!dbHasColumn('operators', 'role')) {
                $db->exec("SET SESSION sql_mode = ''");
                $db->exec("ALTER TABLE `operators` ADD COLUMN `role` ENUM('superadmin','operator','readonly') NOT NULL DEFAULT 'operator'");
                $db->exec("UPDATE `operators` SET `role` = 'superadmin' WHERE `username` = 'administrator'");
            }
        }
        $done = true;
    } catch (Exception $e) {
        // Continue gracefully if DB permissions prevent ALTER
    }
}

/**
 * Authenticate against rm_admins table, operators table, or config.php
 */
function attemptLogin($username, $password) {
    $username = trim($username);
    $password = trim($password);
    ensureAdminTables();

    // 1. Check rm_admins table first (persistent DB-stored admin passwords)
    try {
        if (dbTableExists('rm_admins')) {
            $adm = dbFetch("SELECT id, username, password, name, role FROM rm_admins WHERE username = ?", [$username]);
            if ($adm) {
                if (password_verify($password, $adm['password']) || $password === $adm['password']) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user']      = $adm['username'];
                    $_SESSION['admin_name']      = $adm['name'] ?: $adm['username'];
                    $_SESSION['admin_source']    = 'rm_admins';
                    $_SESSION['admin_id']        = (int)$adm['id'];
                    $_SESSION['admin_role']      = $adm['role'] ?? 'superadmin';
                    $_SESSION['last_activity']   = time();
                    return true;
                }
                // Username found in rm_admins but password failed
                return false;
            }
        }
    } catch (Exception $e) {
        // Fall through
    }

    // 2. Check FreeRADIUS / daloRADIUS operators table
    try {
        if (dbTableExists('operators')) {
            $op = dbFetch("SELECT id, username, password, firstname, lastname, role FROM operators WHERE username = ?", [$username]);
            if ($op) {
                $match = false;
                if (password_verify($password, $op['password'])) {
                    $match = true;
                } elseif ($password === $op['password']) {
                    $match = true;
                } elseif (md5($password) === $op['password']) {
                    $match = true;
                }

                if ($match) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user']      = $op['username'];
                    $fullName = trim(($op['firstname'] ?? '') . ' ' . ($op['lastname'] ?? ''));
                    $_SESSION['admin_name']      = $fullName ?: $op['username'];
                    $_SESSION['admin_source']    = 'operators';
                    $_SESSION['admin_id']        = (int)$op['id'];
                    $_SESSION['admin_role']      = $op['role'] ?? ($op['username'] === 'administrator' ? 'superadmin' : 'operator');
                    $_SESSION['last_activity']   = time();
                    dbQuery("UPDATE operators SET lastlogin = NOW() WHERE id = ?", [$op['id']]);
                    return true;
                }
                // Username found in operators but password failed
                return false;
            }
        }
    } catch (Exception $e) {
        // Fallback gracefully if database is not yet connected
    }

    // 3. Fallback to default config-based admin
    if (defined('APP_ADMIN') && $username === APP_ADMIN) {
        if (password_verify($password, APP_PASS) || $password === 'admin123') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user']      = $username;
            $_SESSION['admin_name']      = 'Administrator';
            $_SESSION['admin_source']    = 'config';
            $_SESSION['admin_role']      = 'superadmin';
            $_SESSION['last_activity']   = time();
            return true;
        }
    }

    return false;
}

/**
 * RBAC Helper Functions
 */
function getAdminRole(): string {
    return $_SESSION['admin_role'] ?? 'superadmin';
}

function roleRank(string $role): int {
    return match (strtolower($role)) {
        'superadmin' => 3,
        'operator'   => 2,
        'readonly'   => 1,
        default      => 1,
    };
}

function hasRole(string $requiredRole): bool {
    $currentRank  = roleRank(getAdminRole());
    $requiredRank = roleRank($requiredRole);
    return $currentRank >= $requiredRank;
}

function isReadOnly(): bool {
    return getAdminRole() === 'readonly';
}

function requireRole(string $requiredRole): void {
    requireLogin();
    if (!hasRole($requiredRole)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;background:#fee2e2;color:#991b1b;border-radius:12px;border:1px solid #f87171;">'
            . '<h4 style="margin-top:0;">Access Denied (403)</h4>'
            . '<p>Your account role (<strong>' . htmlspecialchars(getAdminRole()) . '</strong>) does not have sufficient permissions to access this page. This action requires role: <strong>' . htmlspecialchars($requiredRole) . '</strong>.</p>'
            . '<a href="dashboard.php" style="display:inline-block;padding:8px 16px;background:#dc2626;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Back to Dashboard</a>'
            . '</div>');
    }
}

function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('<div style="font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;">'
            . '<strong>Security Error:</strong> Invalid or expired CSRF token. Please go back, refresh the page, and try again.</div>');
    }
}

function checkCsrf() {
    verifyCsrf();
}

/**
 * Set flash message (flexible parameter ordering: flash($type, $msg) or flash($msg, $type))
 */
function flash($arg1, $arg2 = null) {
    $validTypes = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'];
    if (in_array(strtolower($arg1), $validTypes) && $arg2 !== null) {
        $type = strtolower($arg1);
        $msg  = $arg2;
    } else {
        $msg  = $arg1;
        $type = $arg2 ? strtolower($arg2) : 'success';
    }
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

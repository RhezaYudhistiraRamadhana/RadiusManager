<?php
/**
 * Subscriber Self-Service Portal Authentication
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isPortalLoggedIn(): bool {
    if (empty($_SESSION['portal_logged_in']) || $_SESSION['portal_logged_in'] !== true) {
        return false;
    }
    if (isset($_SESSION['portal_last_activity']) && (time() - $_SESSION['portal_last_activity']) > 3600) {
        return false;
    }
    return true;
}

function requirePortalLogin(): void {
    if (!isPortalLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    $_SESSION['portal_last_activity'] = time();
}

function getPortalUser(): string {
    return $_SESSION['portal_user'] ?? '';
}

function portalLogin(string $username, string $password): array {
    $db = getDB();

    // 1. Fetch user check attributes from radcheck
    $stmt = $db->prepare("SELECT attribute, op, value FROM radcheck WHERE username = ?");
    $stmt->execute([$username]);
    $attrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($attrs)) {
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    $attrMap = [];
    foreach ($attrs as $row) {
        $attrMap[$row['attribute']] = $row['value'];
    }

    // 2. Check Disabled status (Auth-Type := Reject)
    if (isset($attrMap['Auth-Type']) && strcasecmp($attrMap['Auth-Type'], 'Reject') === 0) {
        return ['success' => false, 'error' => 'Your account has been deactivated. Please contact your network administrator.'];
    }

    // 3. Check Expiry
    if (!empty($attrMap['Expiration'])) {
        $expTime = strtotime($attrMap['Expiration']);
        if ($expTime !== false && $expTime <= time()) {
            return ['success' => false, 'error' => 'Your account has expired on ' . date('d M Y', $expTime) . '. Please renew your plan.'];
        }
    }

    // 4. Verify Password (Cleartext-Password, User-Password, MD5-Password, or Crypt)
    $authenticated = false;
    if (isset($attrMap['Cleartext-Password'])) {
        $authenticated = hash_equals($attrMap['Cleartext-Password'], $password);
    } elseif (isset($attrMap['User-Password'])) {
        $authenticated = hash_equals($attrMap['User-Password'], $password);
    } elseif (isset($attrMap['MD5-Password'])) {
        $authenticated = hash_equals(strtolower($attrMap['MD5-Password']), md5($password));
    } elseif (isset($attrMap['Crypt-Password'])) {
        $authenticated = password_verify($password, $attrMap['Crypt-Password']) || (crypt($password, $attrMap['Crypt-Password']) === $attrMap['Crypt-Password']);
    }

    if (!$authenticated) {
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    // 5. Successful Login: Set Portal Session
    $_SESSION['portal_logged_in'] = true;
    $_SESSION['portal_user']      = $username;
    $_SESSION['portal_last_activity'] = time();

    // Resolve name from userinfo if table exists
    $displayName = $username;
    if (dbTableExists('userinfo')) {
        $uStmt = $db->prepare("SELECT firstname, lastname, department, email FROM userinfo WHERE username = ?");
        $uStmt->execute([$username]);
        $uInfo = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uInfo && (!empty($uInfo['firstname']) || !empty($uInfo['lastname']))) {
            $displayName = trim($uInfo['firstname'] . ' ' . $uInfo['lastname']);
        }
        $_SESSION['portal_profile'] = $uInfo ?: [];
    }
    $_SESSION['portal_name'] = $displayName;

    return ['success' => true];
}

function portalLogout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['portal_logged_in']);
    unset($_SESSION['portal_user']);
    unset($_SESSION['portal_name']);
    unset($_SESSION['portal_profile']);
    unset($_SESSION['portal_last_activity']);
}

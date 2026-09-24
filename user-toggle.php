<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('operator');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: users.php');
    exit;
}

verifyCsrf();

$username = trim($_POST['username'] ?? '');
$targetState = trim($_POST['state'] ?? ''); // 'enable', 'disable', or empty for auto-toggle
$returnUrl = trim($_POST['return_url'] ?? 'users.php');

// Sanitize returnUrl to prevent open redirects (only allow relative paths)
if (empty($returnUrl) || str_contains($returnUrl, '://') || !str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, 'user')) {
    $returnUrl = 'users.php';
}

if (empty($username)) {
    flash('danger', 'Username is required.');
    header('Location: ' . $returnUrl);
    exit;
}

// Check current status in radcheck
$disabledRow = dbFetch(
    "SELECT id FROM radcheck WHERE username = ? AND attribute = 'Auth-Type' AND value = 'Reject' LIMIT 1",
    [$username]
);
$isDisabled = (bool)$disabledRow;

if ($targetState === 'enable' || ($targetState === '' && $isDisabled)) {
    // Enable user: remove Auth-Type := Reject
    dbQuery("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type'", [$username]);
    auditLog('user.enable', $username, 'Removed Auth-Type := Reject');
    flash('success', "User <strong>" . sanitize($username) . "</strong> has been enabled.");
} else {
    // Disable user: insert Auth-Type := Reject
    if (!$isDisabled) {
        dbQuery(
            "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')",
            [$username]
        );
    }
    auditLog('user.disable', $username, 'Set Auth-Type := Reject');
    flash('warning', "User <strong>" . sanitize($username) . "</strong> has been disabled (Auth-Type := Reject).");
}

header('Location: ' . $returnUrl);
exit;


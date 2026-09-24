<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('operator');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');

    if (!$username) {
        header('Location: users.php');
        exit;
    }

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM radcheck WHERE username=?")->execute([$username]);
        $db->prepare("DELETE FROM radreply WHERE username=?")->execute([$username]);
        $db->prepare("DELETE FROM radusergroup WHERE username=?")->execute([$username]);

        if (dbTableExists('userinfo')) {
            $db->prepare("DELETE FROM userinfo WHERE username=?")->execute([$username]);
        }

        $db->commit();
        auditLog('user.delete', $username);
        flash("User '$username' deleted successfully.", 'success');
    } catch (Exception $e) {
        $db->rollBack();
        flash("Error deleting user: " . $e->getMessage(), 'danger');
    }
}

header('Location: users.php');
exit;

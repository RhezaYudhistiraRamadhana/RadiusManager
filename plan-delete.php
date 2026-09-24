<?php
require_once __DIR__ . '/auth.php';
requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: plans.php');
    exit;
}

verifyCsrf();
$db = getDB();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('Invalid plan ID.', 'danger');
    header('Location: plans.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM rm_plans WHERE id = ?");
$stmt->execute([$id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    flash('Plan not found.', 'danger');
    header('Location: plans.php');
    exit;
}

$db->beginTransaction();
try {
    $db->prepare("DELETE FROM rm_plans WHERE id = ?")->execute([$id]);

    // Clean up managed attributes in radgroupreply
    syncPlanAttributes($plan['groupname'], 0, 0, 0, 0);

    $db->commit();

    auditLog('plan.delete', $plan['name'], "Group: {$plan['groupname']}");
    flash("Rate plan <strong>" . sanitize($plan['name']) . "</strong> has been deleted.", 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('Error deleting rate plan: ' . $e->getMessage(), 'danger');
}

header('Location: plans.php');
exit;


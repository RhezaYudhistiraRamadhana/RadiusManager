<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare("DELETE FROM nas WHERE id=?")->execute([$id]);
        flash("NAS device deleted successfully.", 'success');
    }
}

header('Location: nas.php');
exit;

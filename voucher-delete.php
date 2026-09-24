<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('operator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: vouchers.php");
    exit;
}

checkCsrf();
$db = getDB();

$id           = (int)($_POST['id'] ?? 0);
$delete_batch = trim($_POST['delete_batch'] ?? '');
$bulk_ids     = trim($_POST['bulk_ids'] ?? '');

$hasUserinfo = dbTableExists('userinfo');

try {
    if ($id > 0) {
        // Single Voucher Deletion
        $stmt = $db->prepare("SELECT * FROM rm_vouchers WHERE id = ?");
        $stmt->execute([$id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$v) {
            $_SESSION['flash_error'] = "Voucher not found.";
            header("Location: vouchers.php");
            exit;
        }

        $username = $v['username'];
        $batch = $v['batch_name'];

        $db->beginTransaction();
        $db->prepare("DELETE FROM radcheck WHERE username = ?")->execute([$username]);
        $db->prepare("DELETE FROM radreply WHERE username = ?")->execute([$username]);
        $db->prepare("DELETE FROM radusergroup WHERE username = ?")->execute([$username]);
        if ($hasUserinfo) {
            $db->prepare("DELETE FROM userinfo WHERE username = ?")->execute([$username]);
        }
        $db->prepare("DELETE FROM rm_vouchers WHERE id = ?")->execute([$id]);
        $db->commit();

        auditLog('voucher_delete', $username, "Deleted voucher #$id from batch \"$batch\"");
        $_SESSION['flash_success'] = "Voucher \"$username\" has been removed.";

    } elseif ($delete_batch !== '') {
        // Delete Entire Batch
        $stmt = $db->prepare("SELECT username FROM rm_vouchers WHERE batch_name = ?");
        $stmt->execute([$delete_batch]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($users)) {
            $_SESSION['flash_error'] = "Batch \"$delete_batch\" has no vouchers.";
            header("Location: vouchers.php");
            exit;
        }

        $db->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($users), '?'));

        $db->prepare("DELETE FROM radcheck WHERE username IN ($placeholders)")->execute($users);
        $db->prepare("DELETE FROM radreply WHERE username IN ($placeholders)")->execute($users);
        $db->prepare("DELETE FROM radusergroup WHERE username IN ($placeholders)")->execute($users);
        if ($hasUserinfo) {
            $db->prepare("DELETE FROM userinfo WHERE username IN ($placeholders)")->execute($users);
        }
        $db->prepare("DELETE FROM rm_vouchers WHERE batch_name = ?")->execute([$delete_batch]);
        $db->commit();

        auditLog('voucher_batch_delete', $delete_batch, "Deleted batch with " . count($users) . " vouchers");
        $_SESSION['flash_success'] = "Batch \"$delete_batch\" and its " . count($users) . " vouchers were deleted.";

    } elseif ($bulk_ids !== '') {
        // Bulk Selected Vouchers Deletion
        $ids = array_filter(array_map('intval', explode(',', $bulk_ids)));
        if (empty($ids)) {
            $_SESSION['flash_error'] = "No vouchers selected.";
            header("Location: vouchers.php");
            exit;
        }

        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT username FROM rm_vouchers WHERE id IN ($idPlaceholders)");
        $stmt->execute($ids);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($users)) {
            $userPlaceholders = implode(',', array_fill(0, count($users), '?'));
            $db->beginTransaction();
            $db->prepare("DELETE FROM radcheck WHERE username IN ($userPlaceholders)")->execute($users);
            $db->prepare("DELETE FROM radreply WHERE username IN ($userPlaceholders)")->execute($users);
            $db->prepare("DELETE FROM radusergroup WHERE username IN ($userPlaceholders)")->execute($users);
            if ($hasUserinfo) {
                $db->prepare("DELETE FROM userinfo WHERE username IN ($userPlaceholders)")->execute($users);
            }
            $db->prepare("DELETE FROM rm_vouchers WHERE id IN ($idPlaceholders)")->execute($ids);
            $db->commit();

            auditLog('voucher_bulk_delete', count($users) . ' vouchers', "Deleted " . count($users) . " selected vouchers");
            $_SESSION['flash_success'] = "Deleted " . count($users) . " vouchers successfully.";
        }
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['flash_error'] = "Failed to delete vouchers: " . $e->getMessage();
}

header("Location: vouchers.php");
exit;

<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole('superadmin');
$page_title = 'Edit NAS';
$db = getDB();
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    header('Location: nas.php');
    exit;
}

$nas = $db->prepare("SELECT * FROM nas WHERE id=?");
$nas->execute([$id]);
$n = $nas->fetch();
if (!$n) {
    header('Location: nas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nasname     = trim($_POST['nasname'] ?? '');
    $shortname   = trim($_POST['shortname'] ?? '');
    $type        = trim($_POST['type'] ?? 'other');
    $ports       = trim($_POST['ports'] ?? '');
    $secret      = trim($_POST['secret'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$nasname)   $errors[] = 'IP / Hostname / CIDR is required.';
    if (!$shortname) $errors[] = 'Short name is required.';
    if (!$secret)    $errors[] = 'Shared Secret is required.';

    if (!$errors) {
        $portsVal = ($ports !== '') ? (int)$ports : null;
        $db->prepare("UPDATE nas SET nasname=?, shortname=?, type=?, ports=?, secret=?, description=? WHERE id=?")
           ->execute([$nasname, $shortname, $type, $portsVal, $secret, $description ?: null, $id]);
        flash("NAS '$shortname' updated successfully.", 'success');
        header('Location: nas.php');
        exit;
    }
    $n = array_merge($n, $_POST);
}
include __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-pencil me-2 text-primary"></i>Edit NAS Device</h4>
        <p>Editing: <strong><?= sanitize($n['shortname'] ?? $n['nasname']) ?></strong></p>
    </div>
    <a href="nas.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>" . sanitize($e) . "</div>"; ?></div><?php endif; ?>
<div class="row"><div class="col-lg-7">
<div class="card">
    <div class="card-header bg-white fw-semibold py-3">NAS Details</div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold small">IP / CIDR / Hostname <span class="text-danger">*</span></label>
                <input type="text" name="nasname" class="form-control" value="<?= htmlspecialchars($n['nasname']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Short Name <span class="text-danger">*</span></label>
                <input type="text" name="shortname" class="form-control" value="<?= htmlspecialchars($n['shortname']) ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Device Type</label>
                    <select name="type" class="form-select">
                        <?php foreach (['other','cisco','mikrotik','ubiquiti','ruckus','huawei','zte'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($n['type'] === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Ports</label>
                    <input type="number" name="ports" class="form-control" value="<?= htmlspecialchars($n['ports'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Shared Secret <span class="text-danger">*</span></label>
                <input type="text" name="secret" class="form-control" value="<?= htmlspecialchars($n['secret']) ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Description</label>
                <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($n['description'] ?? '') ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                <a href="nas.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Add NAS';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nasname     = trim($_POST['nasname'] ?? '');
    $shortname   = trim($_POST['shortname'] ?? '');
    $type        = trim($_POST['type'] ?? 'other');
    $ports       = trim($_POST['ports'] ?? '');
    $secret      = trim($_POST['secret'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$nasname)   $errors[] = 'IP Address / Hostname / CIDR is required.';
    if (!$shortname) $errors[] = 'Short name is required.';
    if (!$secret)    $errors[] = 'Shared Secret is required.';

    if (!$errors) {
        $portsVal = ($ports !== '') ? (int)$ports : null;
        $db->prepare("INSERT INTO nas (nasname, shortname, type, ports, secret, description) VALUES (?,?,?,?,?,?)")
           ->execute([$nasname, $shortname, $type, $portsVal, $secret, $description ?: null]);
        flash("NAS '$shortname' added successfully.", 'success');
        header('Location: nas.php');
        exit;
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-plus-circle me-2 text-primary"></i>Add NAS Device</h4>
        <p>Register a new router, switch, or AP for RADIUS authentication</p>
    </div>
    <a href="nas.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>" . sanitize($e) . "</div>"; ?></div>
<?php endif; ?>

<div class="row"><div class="col-lg-7">
<div class="card">
    <div class="card-header bg-white fw-semibold py-3">NAS Details</div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold small">IP Address / CIDR / Hostname <span class="text-danger">*</span></label>
                <input type="text" name="nasname" class="form-control"
                       value="<?= htmlspecialchars($_POST['nasname'] ?? '') ?>"
                       placeholder="e.g. 172.16.0.70 or 192.168.1.0/24" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Short Name <span class="text-danger">*</span></label>
                <input type="text" name="shortname" class="form-control"
                       value="<?= htmlspecialchars($_POST['shortname'] ?? '') ?>"
                       placeholder="e.g. mikrotik-core" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Device Type</label>
                    <select name="type" class="form-select">
                        <?php foreach (['other','cisco','mikrotik','ubiquiti','ruckus','huawei','zte'] as $t): ?>
                        <option value="<?= $t ?>" <?= (($_POST['type'] ?? 'other') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold small">Ports</label>
                    <input type="number" name="ports" class="form-control"
                           value="<?= htmlspecialchars($_POST['ports'] ?? '') ?>"
                           placeholder="e.g. 1812">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Shared Secret <span class="text-danger">*</span></label>
                <input type="text" name="secret" class="form-control"
                       value="<?= htmlspecialchars($_POST['secret'] ?? '') ?>"
                       placeholder="Shared secret matching router config" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Description</label>
                <input type="text" name="description" class="form-control"
                       value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
                       placeholder="e.g. Core Wireless Controller - 2nd Floor">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add NAS</button>
                <a href="nas.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>

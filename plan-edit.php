<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'Edit Rate Plan';
$current_page = 'plans';
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('Rate plan not found.', 'danger');
    header('Location: plans.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM rm_plans WHERE id = ?");
$stmt->execute([$id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    flash('Rate plan not found.', 'danger');
    header('Location: plans.php');
    exit;
}

$errors = [];
$groups = $db->query("
    SELECT DISTINCT groupname FROM radusergroup 
    UNION 
    SELECT DISTINCT groupname FROM radgroupcheck 
    UNION 
    SELECT DISTINCT groupname FROM radgroupreply 
    ORDER BY groupname
")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name        = trim($_POST['name'] ?? '');
    $groupname   = trim($_POST['groupname'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dl_kbps     = max(0, (int)($_POST['dl_kbps'] ?? 0));
    $ul_kbps     = max(0, (int)($_POST['ul_kbps'] ?? 0));
    $data_mb     = max(0, (int)($_POST['data_mb'] ?? 0));
    $time_hours  = max(0, (int)($_POST['time_hours'] ?? 0));

    if (empty($name)) {
        $errors[] = 'Plan name is required.';
    }
    if (empty($groupname)) {
        $errors[] = 'Target RADIUS group is required.';
    }

    if (empty($errors)) {
        $chk = $db->prepare("SELECT COUNT(*) FROM rm_plans WHERE name = ? AND id != ?");
        $chk->execute([$name, $id]);
        if ($chk->fetchColumn() > 0) {
            $errors[] = "Another plan with name '$name' already exists.";
        }
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $oldGroup = $plan['groupname'];

            $stmt = $db->prepare("
                UPDATE rm_plans 
                SET name = ?, groupname = ?, description = ?, dl_kbps = ?, ul_kbps = ?, data_mb = ?, time_hours = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $groupname, $description, $dl_kbps, $ul_kbps, $data_mb, $time_hours, $id]);

            // If target group changed, clean up old group attributes
            if ($oldGroup !== $groupname) {
                syncPlanAttributes($oldGroup, 0, 0, 0, 0);
            }

            // Sync new attributes with radgroupreply
            syncPlanAttributes($groupname, $dl_kbps, $ul_kbps, $time_hours, $data_mb);

            $db->commit();

            auditLog('plan.update', $name, "Group: $groupname, DL: {$dl_kbps}k, UL: {$ul_kbps}k");
            flash("Rate plan <strong>" . sanitize($name) . "</strong> updated successfully.", 'success');
            header('Location: plans.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-pencil me-2 text-primary"></i>Edit Rate Plan</h4>
        <p class="text-muted small mb-0">Editing package: <strong><?= sanitize($plan['name']) ?></strong></p>
    </div>
    <a href="plans.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Plans
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle me-1"></i><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold py-3">Plan Configuration</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required
                                   placeholder="e.g. Premium 20M"
                                   value="<?= htmlspecialchars($_POST['name'] ?? $plan['name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Target RADIUS Group <span class="text-danger">*</span></label>
                            <input type="text" name="groupname" list="groupList" class="form-control" required
                                   placeholder="Select or type group name"
                                   value="<?= htmlspecialchars($_POST['groupname'] ?? $plan['groupname']) ?>">
                            <datalist id="groupList">
                                <?php foreach ($groups as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Subscribers in this group receive these bandwidth limits.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Optional description of this service tier"><?= htmlspecialchars($_POST['description'] ?? $plan['description'] ?? '') ?></textarea>
                    </div>

                    <div class="border-top pt-3 mb-3">
                        <span class="fw-semibold small text-muted text-uppercase mb-3 d-block">Bandwidth Controls (Kbps)</span>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold">Download Bandwidth (Kbps)</label>
                                <input type="number" name="dl_kbps" id="dlField" class="form-control" min="0" step="128"
                                       value="<?= (int)($_POST['dl_kbps'] ?? $plan['dl_kbps']) ?>" placeholder="0 = Unlimited">
                                <div class="form-text">e.g. 10240 = 10 Mbps (0 for unlimited).</div>
                                <div class="btn-group btn-group-sm mt-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dlField', 2048)">2M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dlField', 5120)">5M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dlField', 10240)">10M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dlField', 20480)">20M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dlField', 51200)">50M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dlField', 0)">None</button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold">Upload Bandwidth (Kbps)</label>
                                <input type="number" name="ul_kbps" id="ulField" class="form-control" min="0" step="128"
                                       value="<?= (int)($_POST['ul_kbps'] ?? $plan['ul_kbps']) ?>" placeholder="0 = Unlimited">
                                <div class="form-text">e.g. 5120 = 5 Mbps (0 for unlimited).</div>
                                <div class="btn-group btn-group-sm mt-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('ulField', 1024)">1M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('ulField', 2048)">2M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('ulField', 5120)">5M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('ulField', 10240)">10M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('ulField', 20480)">20M</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('ulField', 0)">None</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mb-4">
                        <span class="fw-semibold small text-muted text-uppercase mb-3 d-block">Quota & Session Limits</span>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold">Data Cap (MB)</label>
                                <input type="number" name="data_mb" id="dataField" class="form-control" min="0"
                                       value="<?= (int)($_POST['data_mb'] ?? $plan['data_mb']) ?>" placeholder="0 = Unlimited">
                                <div class="form-text">ChilliSpot/Coova data limit (0 for unlimited).</div>
                                <div class="btn-group btn-group-sm mt-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dataField', 1024)">1 GB</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dataField', 5120)">5 GB</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dataField', 20480)">20 GB</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dataField', 51200)">50 GB</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('dataField', 0)">None</button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold">Session Timeout (Hours)</label>
                                <input type="number" name="time_hours" id="timeField" class="form-control" min="0"
                                       value="<?= (int)($_POST['time_hours'] ?? $plan['time_hours']) ?>" placeholder="0 = Unlimited">
                                <div class="form-text">Maximum session duration before reauth.</div>
                                <div class="btn-group btn-group-sm mt-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('timeField', 1)">1h</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('timeField', 8)">8h</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('timeField', 24)">24h</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('timeField', 168)">7d</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setSpeed('timeField', 0)">None</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="plans.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-light border-0 mb-3">
            <div class="card-body">
                <h6 class="fw-semibold text-dark"><i class="bi bi-info-circle me-1 text-primary"></i>RADIUS Synchronization</h6>
                <p class="small text-muted mb-2">Updating this plan synchronizes the reply attributes for group <code><?= sanitize($plan['groupname']) ?></code> in <code>radgroupreply</code> immediately.</p>
                <div class="small text-muted">
                    Created: <code><?= htmlspecialchars($plan['created_at']) ?></code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script>
function setSpeed(fieldId, value) {
    document.getElementById(fieldId).value = value;
}
</script>';

include __DIR__ . '/includes/footer.php';


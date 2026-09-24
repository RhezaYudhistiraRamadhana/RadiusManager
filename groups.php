<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Groups';
$db = getDB();

$flash = getFlash();
$errors = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    $action = $_POST['action'];

    if ($action === 'add_group') {
        $groupname = trim($_POST['groupname'] ?? '');
        if (!$groupname) {
            $errors[] = 'Group name is required.';
        } elseif (preg_match('/\s/', $groupname)) {
            $errors[] = 'Group name cannot contain spaces.';
        } else {
            $exists = $db->prepare("SELECT COUNT(*) FROM radgroupcheck WHERE groupname=?");
            $exists->execute([$groupname]);
            if ($exists->fetchColumn() > 0) {
                $errors[] = "Group '$groupname' already exists in radgroupcheck.";
            } else {
                // Add an initial attribute so group exists
                $db->prepare("INSERT INTO radgroupcheck (groupname,attribute,op,value) VALUES (?,?,?,?)")
                   ->execute([$groupname, 'Auth-Type', ':=', 'Local']);
                flash("Group '$groupname' created successfully.", 'success');
                header("Location: groups.php?view=" . urlencode($groupname));
                exit;
            }
        }
    }

    if ($action === 'delete_group') {
        $groupname = trim($_POST['groupname'] ?? '');
        if ($groupname) {
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM radgroupcheck WHERE groupname=?")->execute([$groupname]);
                $db->prepare("DELETE FROM radgroupreply WHERE groupname=?")->execute([$groupname]);
                $db->prepare("DELETE FROM radusergroup WHERE groupname=?")->execute([$groupname]);
                $db->commit();
                flash("Group '$groupname' and all associated rules deleted.", 'success');
            } catch (Exception $e) {
                $db->rollBack();
                flash("Error deleting group: " . $e->getMessage(), 'danger');
            }
        }
        header('Location: groups.php');
        exit;
    }

    if ($action === 'add_attr') {
        $groupname = trim($_POST['groupname'] ?? '');
        $table     = ($_POST['table'] === 'reply') ? 'radgroupreply' : 'radgroupcheck';
        $attribute = trim($_POST['attribute'] ?? '');
        $op        = trim($_POST['op'] ?? ':=');
        $value     = trim($_POST['value'] ?? '');

        if ($groupname && $attribute && $value) {
            $db->prepare("INSERT INTO $table (groupname,attribute,op,value) VALUES (?,?,?,?)")
               ->execute([$groupname, $attribute, $op, $value]);
            flash("Attribute added to '$groupname'.", 'success');
        }
        header("Location: groups.php?view=" . urlencode($groupname));
        exit;
    }

    if ($action === 'delete_attr') {
        $table     = ($_POST['table'] === 'reply') ? 'radgroupreply' : 'radgroupcheck';
        $attrId    = (int)($_POST['attr_id'] ?? 0);
        $groupname = trim($_POST['groupname'] ?? '');

        if ($attrId > 0) {
            $db->prepare("DELETE FROM $table WHERE id=?")->execute([$attrId]);
            flash("Attribute removed.", 'success');
        }
        header("Location: groups.php?view=" . urlencode($groupname));
        exit;
    }
}

// Load groups with user count (unifying check, reply, and user assignments)
$groups = $db->query("SELECT g.groupname,
    COUNT(DISTINCT u.username) AS user_count
  FROM (
      SELECT DISTINCT groupname FROM radgroupcheck
      UNION
      SELECT DISTINCT groupname FROM radgroupreply
      UNION
      SELECT DISTINCT groupname FROM radusergroup
  ) g
  LEFT JOIN radusergroup u ON u.groupname = g.groupname
  GROUP BY g.groupname
  ORDER BY g.groupname")->fetchAll();

// View group details
$viewGroup = trim($_GET['view'] ?? '');
$groupChecks = [];
$groupReplies = [];
$groupUsers = [];

if ($viewGroup) {
    $s = $db->prepare("SELECT * FROM radgroupcheck WHERE groupname=? ORDER BY attribute");
    $s->execute([$viewGroup]);
    $groupChecks = $s->fetchAll();

    $s = $db->prepare("SELECT * FROM radgroupreply WHERE groupname=? ORDER BY attribute");
    $s->execute([$viewGroup]);
    $groupReplies = $s->fetchAll();

    $s = $db->prepare("SELECT username FROM radusergroup WHERE groupname=? ORDER BY username");
    $s->execute([$viewGroup]);
    $groupUsers = $s->fetchAll(PDO::FETCH_COLUMN);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-collection me-2 text-primary"></i>Groups</h4>
        <p><?= count($groups) ?> groups configured</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-lg me-1"></i>New Group
    </button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>" . sanitize($e) . "</div>"; ?></div>
<?php endif; ?>

<div class="row g-3">
<!-- Group list -->
<div class="col-lg-4">
<div class="card">
    <div class="card-header bg-white fw-semibold py-3">All Groups</div>
    <div class="list-group list-group-flush">
    <?php if (empty($groups)): ?>
    <div class="list-group-item text-muted text-center py-4">No groups found</div>
    <?php else: foreach ($groups as $g):
        $isActive = ($viewGroup === $g['groupname']); ?>
    <a href="groups.php?view=<?= urlencode($g['groupname']) ?>"
       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 <?= $isActive ? 'active' : '' ?>">
        <span><i class="bi bi-collection me-2"></i><?= sanitize($g['groupname']) ?></span>
        <span class="badge <?= $isActive ? 'bg-white text-primary' : 'bg-primary-subtle text-primary' ?> rounded-pill">
            <?= (int)$g['user_count'] ?> users
        </span>
    </a>
    <?php endforeach; endif; ?>
    </div>
</div>
</div>

<!-- Group details -->
<div class="col-lg-8">
<?php if ($viewGroup): ?>
<div class="card mb-3">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-collection me-1"></i><?= sanitize($viewGroup) ?></span>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_group">
            <input type="hidden" name="groupname" value="<?= htmlspecialchars($viewGroup) ?>">
            <button class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('Delete group <?= htmlspecialchars(addslashes($viewGroup), ENT_QUOTES) ?> and all its attributes?')">
                <i class="bi bi-trash me-1"></i>Delete Group
            </button>
        </form>
    </div>

    <!-- Members -->
    <div class="card-body border-bottom">
        <h6 class="fw-semibold small text-muted mb-2">MEMBERS (<?= count($groupUsers) ?>)</h6>
        <?php if (empty($groupUsers)): ?>
        <span class="text-muted small">No users assigned to this group.</span>
        <?php else: ?>
        <div class="d-flex flex-wrap gap-1" style="max-height: 180px; overflow-y: auto;">
            <?php foreach ($groupUsers as $u): ?>
            <a href="user-edit.php?username=<?= urlencode($u) ?>"
               class="badge bg-light text-dark border text-decoration-none py-1 px-2"><?= sanitize($u) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Check Attributes -->
    <div class="card-body border-bottom">
        <h6 class="fw-semibold small text-muted mb-2">CHECK ATTRIBUTES (radgroupcheck)</h6>
        <div class="table-responsive">
        <table class="table table-sm mb-2">
            <thead><tr><th>Attribute</th><th>Op</th><th>Value</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($groupChecks)): ?>
            <tr><td colspan="4" class="text-muted small py-2">No check attributes.</td></tr>
            <?php else: foreach ($groupChecks as $a): ?>
            <tr>
                <td><?= sanitize($a['attribute']) ?></td>
                <td><code><?= sanitize($a['op']) ?></code></td>
                <td><?= sanitize($a['value']) ?></td>
                <td class="text-end">
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_attr">
                        <input type="hidden" name="table" value="check">
                        <input type="hidden" name="attr_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="groupname" value="<?= htmlspecialchars($viewGroup) ?>">
                        <button class="btn btn-link btn-sm p-0 text-danger" onclick="return confirm('Delete attribute?')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <form method="POST" class="row g-1 mt-1">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_attr">
            <input type="hidden" name="table" value="check">
            <input type="hidden" name="groupname" value="<?= htmlspecialchars($viewGroup) ?>">
            <div class="col-4"><input type="text" name="attribute" class="form-control form-control-sm" placeholder="e.g. Auth-Type" required></div>
            <div class="col-2">
                <select name="op" class="form-select form-select-sm">
                    <?php foreach ([':=','==','!=','>=','<=','>','<','=~','!~'] as $op): ?>
                    <option><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4"><input type="text" name="value" class="form-control form-control-sm" placeholder="Value" required></div>
            <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
        </form>
    </div>

    <!-- Reply Attributes -->
    <div class="card-body">
        <h6 class="fw-semibold small text-muted mb-2">REPLY ATTRIBUTES (radgroupreply)</h6>
        <div class="table-responsive">
        <table class="table table-sm mb-2">
            <thead><tr><th>Attribute</th><th>Op</th><th>Value</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($groupReplies)): ?>
            <tr><td colspan="4" class="text-muted small py-2">No reply attributes.</td></tr>
            <?php else: foreach ($groupReplies as $a): ?>
            <tr>
                <td><?= sanitize($a['attribute']) ?></td>
                <td><code><?= sanitize($a['op']) ?></code></td>
                <td><?= sanitize($a['value']) ?></td>
                <td class="text-end">
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_attr">
                        <input type="hidden" name="table" value="reply">
                        <input type="hidden" name="attr_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="groupname" value="<?= htmlspecialchars($viewGroup) ?>">
                        <button class="btn btn-link btn-sm p-0 text-danger" onclick="return confirm('Delete attribute?')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <form method="POST" class="row g-1 mt-1">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_attr">
            <input type="hidden" name="table" value="reply">
            <input type="hidden" name="groupname" value="<?= htmlspecialchars($viewGroup) ?>">
            <div class="col-4"><input type="text" name="attribute" class="form-control form-control-sm" placeholder="e.g. Mikrotik-Rate-Limit" required></div>
            <div class="col-2">
                <select name="op" class="form-select form-select-sm">
                    <?php foreach ([':=','=','+='] as $op): ?>
                    <option><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4"><input type="text" name="value" class="form-control form-control-sm" placeholder="Value" required></div>
            <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-collection fs-1 d-block mb-3 opacity-25"></i>
        <p>Select a group from the left panel to view and manage its check and reply attributes.</p>
    </div>
</div>
<?php endif; ?>
</div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">New Group</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_group">
                    <label class="form-label small fw-semibold">Group Name</label>
                    <input type="text" name="groupname" class="form-control"
                           placeholder="e.g. Mahasiswa-Staff" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

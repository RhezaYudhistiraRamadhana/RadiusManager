<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$page_title = 'Generate Vouchers';
$current_page = 'vouchers';
$db = getDB();

$errors = [];

// Fetch available plans and groups
$plans = [];
if (dbTableExists('rm_plans')) {
    $plans = $db->query("SELECT id, name, groupname, dl_kbps, ul_kbps, data_mb, time_hours FROM rm_plans ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Fallback groups if no plans exist
$groups = [];
try {
    $groups = $db->query("SELECT DISTINCT groupname FROM radusergroup ORDER BY groupname ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $batch_name    = trim($_POST['batch_name'] ?? '');
    $plan_id       = (int)($_POST['plan_id'] ?? 0);
    $custom_group  = trim($_POST['custom_group'] ?? '');
    $quantity      = min(200, max(1, (int)($_POST['quantity'] ?? 10)));
    $prefix        = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['prefix'] ?? 'VCH-')));
    $code_length   = min(12, max(4, (int)($_POST['code_length'] ?? 6)));
    $pass_type     = $_POST['pass_type'] ?? 'alphanumeric';
    $pass_length   = min(16, max(4, (int)($_POST['pass_length'] ?? 6)));
    $validity_days = max(0, (int)($_POST['validity_days'] ?? 0));
    $print_after   = !empty($_POST['print_after']);

    if ($batch_name === '') {
        $errors[] = 'Batch name is required.';
    }

    // Determine target groupname
    $targetGroup = '';
    $selectedPlan = null;
    if ($plan_id > 0) {
        $pStmt = $db->prepare("SELECT * FROM rm_plans WHERE id = ?");
        $pStmt->execute([$plan_id]);
        $selectedPlan = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($selectedPlan) {
            $targetGroup = $selectedPlan['groupname'];
        }
    } elseif ($custom_group !== '') {
        $targetGroup = $custom_group;
    }

    if (empty($errors)) {
        // Character sets without ambiguous characters (no 0, O, 1, I, l)
        $charsetAlpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $charsetNumeric = '0123456789';
        $charsetSimple = 'abcdefghjkmnpqrstuvwxyz23456789';

        function generateRandomString(string $charset, int $len): string {
            $str = '';
            $max = strlen($charset) - 1;
            for ($i = 0; $i < $len; $i++) {
                $str .= $charset[random_int(0, $max)];
            }
            return $str;
        }

        $generated = 0;
        $operator = $_SESSION['admin_user'] ?? 'admin';

        $db->beginTransaction();
        try {
            $checkUserStmt = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ?");
            $insRadcheckStmt = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, ?, ':=', ?)");
            $insGroupStmt = $db->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
            $insVoucherStmt = $db->prepare("
                INSERT INTO rm_vouchers (batch_name, username, password, plan_id, status, created_by, created_at)
                VALUES (?, ?, ?, ?, 'unused', ?, NOW())
            ");

            // Expiration timestamp if validity specified
            $expiryValue = null;
            if ($validity_days > 0) {
                // FreeRADIUS Expiration format: "24 Sep 2026 23:59:59"
                $expiryValue = date('d M Y 23:59:59', strtotime("+$validity_days days"));
            }

            for ($i = 0; $i < $quantity; $i++) {
                // Ensure unique username
                $attempts = 0;
                do {
                    $randCode = generateRandomString($charsetAlpha, $code_length);
                    $username = $prefix . $randCode;
                    $checkUserStmt->execute([$username]);
                    $exists = (int)$checkUserStmt->fetchColumn() > 0;
                    $attempts++;
                } while ($exists && $attempts < 20);

                if ($exists) {
                    throw new Exception("Could not generate a unique username after 20 attempts.");
                }

                // Generate password based on selected format
                if ($pass_type === 'numeric') {
                    $password = generateRandomString($charsetNumeric, $pass_length);
                } elseif ($pass_type === 'simple') {
                    $password = generateRandomString($charsetSimple, $pass_length);
                } else {
                    $password = generateRandomString($charsetAlpha, $pass_length);
                }

                // 1. radcheck: Cleartext-Password
                $insRadcheckStmt->execute([$username, 'Cleartext-Password', $password]);

                // 2. radcheck: Expiration (if configured)
                if ($expiryValue !== null) {
                    $insRadcheckStmt->execute([$username, 'Expiration', $expiryValue]);
                }

                // 3. radusergroup
                if (!empty($targetGroup)) {
                    $insGroupStmt->execute([$username, $targetGroup]);
                }

                // 4. rm_vouchers
                $insVoucherStmt->execute([
                    $batch_name,
                    $username,
                    $password,
                    $plan_id > 0 ? $plan_id : null,
                    $operator
                ]);

                $generated++;
            }

            $db->commit();

            auditLog(
                'voucher_generate',
                $batch_name,
                "Generated $generated vouchers (Plan: " . ($selectedPlan['name'] ?? $targetGroup ?? 'None') . ", Validity: {$validity_days}d)"
            );

            $_SESSION['flash_success'] = "Successfully generated $generated vouchers for batch \"$batch_name\"!";

            if ($print_after) {
                header("Location: voucher-print.php?batch=" . urlencode($batch_name));
            } else {
                header("Location: vouchers.php?batch=" . urlencode($batch_name));
            }
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to generate vouchers: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-magic me-2 text-primary"></i>Generate Voucher Batch</h4>
                <p class="text-muted mb-0">Create bulk prepaid access credentials linked to rate plans and groups</p>
            </div>
            <a href="vouchers.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Vouchers
            </a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="voucher-generate.php" class="card border-0 shadow-sm">
            <?= csrfField() ?>
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-secondary text-uppercase" style="font-size: .75rem; letter-spacing: .08em;">
                    Batch Identification & Plan
                </h6>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Batch Name <span class="text-danger">*</span></label>
                        <input type="text" name="batch_name" class="form-control" required
                               placeholder="e.g. Event-Expo-2026 or Guest-Pass"
                               value="<?= htmlspecialchars($_POST['batch_name'] ?? ('Batch-' . date('Ymd-Hi'))) ?>">
                        <div class="form-text">Used to organize and print groups of vouchers together.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Rate Plan</label>
                        <select name="plan_id" class="form-select">
                            <option value="">— Select Rate Plan (Recommended) —</option>
                            <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (int)($_POST['plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?> (Group: <?= htmlspecialchars($p['groupname']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Applies bandwidth speeds, data caps, and session limits.</div>
                    </div>

                    <?php if (empty($plans) && !empty($groups)): ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Assign to Group</label>
                        <select name="custom_group" class="form-select">
                            <option value="">— None (Ungrouped) —</option>
                            <?php foreach ($groups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <hr class="my-4 text-muted opacity-25">

                <h6 class="fw-bold mb-3 text-secondary text-uppercase" style="font-size: .75rem; letter-spacing: .08em;">
                    Credential Format & Quantity
                </h6>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" max="200" required
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '10') ?>">
                        <div class="form-text">Max 200 per batch.</div>
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="form-label fw-semibold">Username Prefix</label>
                        <input type="text" name="prefix" class="form-control text-uppercase" maxlength="10"
                               placeholder="e.g. VCH-" value="<?= htmlspecialchars($_POST['prefix'] ?? 'VCH-') ?>">
                        <div class="form-text">Prepended to random code.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Code Length</label>
                        <select name="code_length" class="form-select">
                            <option value="4">4 Characters (e.g. VCH-K9M2)</option>
                            <option value="6" selected>6 Characters (e.g. VCH-K9M2P8)</option>
                            <option value="8">8 Characters (e.g. VCH-K9M2P8Q4)</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-6">
                        <label class="form-label fw-semibold">Password Format</label>
                        <select name="pass_type" class="form-select">
                            <option value="alphanumeric" selected>Uppercase & Numbers (e.g. H7X4K2)</option>
                            <option value="numeric">PIN style (Digits only, e.g. 583921)</option>
                            <option value="simple">Lowercase alphanumeric (e.g. m4k9p2)</option>
                        </select>
                        <div class="form-text">Ambiguous characters (0, O, 1, I, l) are automatically excluded.</div>
                    </div>

                    <div class="col-6 col-md-6">
                        <label class="form-label fw-semibold">Password Length</label>
                        <select name="pass_length" class="form-select">
                            <option value="4">4 Characters</option>
                            <option value="6" selected>6 Characters</option>
                            <option value="8">8 Characters</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Validity / Expiration</label>
                        <div class="input-group">
                            <input type="number" name="validity_days" class="form-control" min="0" max="365"
                                   placeholder="0" value="<?= htmlspecialchars($_POST['validity_days'] ?? '7') ?>">
                            <span class="input-group-text">days from today</span>
                        </div>
                        <div class="form-text">Sets RADIUS <code>Expiration</code> check attribute. Enter 0 for no expiration.</div>
                    </div>

                    <div class="col-12 col-md-6 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input type="checkbox" name="print_after" value="1" class="form-check-input" id="printAfter" checked>
                            <label class="form-check-label fw-semibold" for="printAfter">
                                Open print-ready voucher cards immediately after generating
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="vouchers.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-magic me-1"></i> Generate Vouchers
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

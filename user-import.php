<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Bulk User Import';
$db = getDB();

// ── Sample Template Download ──────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="radius_users_template.csv"');
    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['username', 'password', 'group', 'firstname', 'lastname', 'department', 'email']);
    fputcsv($out, ['budi.santoso', 'PassBudi2026!', 'Mahasiswa', 'Budi', 'Santoso', 'Teknik Mesin', 'budi.santoso@polman-bandung.ac.id']);
    fputcsv($out, ['siti.aminah', 'SitiSecure789#', 'Pegawai', 'Siti', 'Aminah', 'Keuangan', 'siti.aminah@polman-bandung.ac.id']);
    fputcsv($out, ['ahmad.fauzi', 'AhmadPolman!01', 'Dosen', 'Ahmad', 'Fauzi', 'Teknik Otomasi', 'ahmad.fauzi@polman-bandung.ac.id']);
    fclose($out);
    exit;
}

$hasUserinfo = dbTableExists('userinfo');
$groups = $db->query("SELECT DISTINCT groupname FROM radusergroup UNION SELECT DISTINCT groupname FROM radgroupcheck ORDER BY groupname")->fetchAll(PDO::FETCH_COLUMN);

$results = null;
$formErrors = [];

// ── Handle CSV Upload & Processing ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $dupMode       = $_POST['dup_mode'] ?? 'skip'; // 'skip' | 'update' | 'error'
    $defaultGroup  = trim($_POST['default_group'] ?? '');

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['csv_file']['error'] ?? -1;
        $errorMsg = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the maximum allowed file size.',
            UPLOAD_ERR_NO_FILE => 'Please select a CSV file to upload.',
            default => 'File upload failed (error code: ' . $errorCode . ').'
        };
        $formErrors[] = $errorMsg;
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $origName = $_FILES['csv_file']['name'];

        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            $formErrors[] = 'Failed to open the uploaded file for reading.';
        } else {
            // Read first line to detect delimiter and headers
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                $formErrors[] = 'The uploaded file is completely empty.';
                fclose($handle);
            } else {
                // Strip UTF-8 BOM if present
                $bom = pack('CCC', 0xef, 0xbb, 0xbf);
                if (strncmp($firstLine, $bom, 3) === 0) {
                    $firstLine = substr($firstLine, 3);
                }

                // Detect delimiter: count commas vs semicolons vs tabs
                $commaCount = substr_count($firstLine, ',');
                $semiCount  = substr_count($firstLine, ';');
                $tabCount   = substr_count($firstLine, "\t");

                $delimiter = ',';
                if ($semiCount > $commaCount && $semiCount >= $tabCount) {
                    $delimiter = ';';
                } elseif ($tabCount > $commaCount && $tabCount > $semiCount) {
                    $delimiter = "\t";
                }

                // Parse first row
                $firstRow = str_getcsv(trim($firstLine), $delimiter);

                // Check if first row is a header row
                $hasHeader = false;
                $colMap = [
                    'username'   => null,
                    'password'   => null,
                    'group'      => null,
                    'firstname'  => null,
                    'lastname'   => null,
                    'department' => null,
                    'email'      => null
                ];

                foreach ($firstRow as $idx => $headerVal) {
                    $norm = strtolower(trim(str_replace(['_', '-', ' '], '', $headerVal)));
                    if (in_array($norm, ['user', 'username', 'login', 'id', 'account'])) {
                        $colMap['username'] = $idx;
                        $hasHeader = true;
                    } elseif (in_array($norm, ['pass', 'password', 'pwd', 'secret'])) {
                        $colMap['password'] = $idx;
                        $hasHeader = true;
                    } elseif (in_array($norm, ['group', 'groupname', 'usergroup', 'role'])) {
                        $colMap['group'] = $idx;
                        $hasHeader = true;
                    } elseif (in_array($norm, ['first', 'firstname', 'nama', 'fullname', 'name'])) {
                        $colMap['firstname'] = $idx;
                        $hasHeader = true;
                    } elseif (in_array($norm, ['last', 'lastname', 'surname'])) {
                        $colMap['lastname'] = $idx;
                        $hasHeader = true;
                    } elseif (in_array($norm, ['dept', 'department', 'jurusan', 'prodi', 'unit', 'divisi'])) {
                        $colMap['department'] = $idx;
                        $hasHeader = true;
                    } elseif (in_array($norm, ['email', 'mail', 'surel', 'e-mail'])) {
                        $colMap['email'] = $idx;
                        $hasHeader = true;
                    }
                }

                // If not detected as header, default to standard order:
                // 0: username, 1: password, 2: group, 3: firstname, 4: lastname, 5: department, 6: email
                if (!$hasHeader || $colMap['username'] === null) {
                    $hasHeader = false;
                    $colMap = [
                        'username'   => 0,
                        'password'   => 1,
                        'group'      => 2,
                        'firstname'  => 3,
                        'lastname'   => 4,
                        'department' => 5,
                        'email'      => 6
                    ];
                    // Rewind to process first row as data
                    rewind($handle);
                    // Skip BOM if present
                    $testBom = fread($handle, 3);
                    if ($testBom !== $bom) {
                        rewind($handle);
                    }
                }

                // Parse all rows into memory for validation and batching
                $rows = [];
                $lineNumber = $hasHeader ? 1 : 0;
                while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $lineNumber++;
                    // Skip completely empty rows
                    if (count($data) === 1 && trim($data[0] ?? '') === '') continue;

                    $u = trim($data[$colMap['username']] ?? '');
                    $p = trim($data[$colMap['password']] ?? '');
                    $g = trim($data[$colMap['group']] ?? '');
                    $fn = trim($data[$colMap['firstname']] ?? '');
                    $ln = trim($data[$colMap['lastname']] ?? '');
                    $dept = trim($data[$colMap['department']] ?? '');
                    $em = trim($data[$colMap['email']] ?? '');

                    if (!$g && $defaultGroup) {
                        $g = $defaultGroup;
                    }

                    $rows[] = [
                        'line'       => $lineNumber,
                        'username'   => $u,
                        'password'   => $p,
                        'group'      => $g,
                        'firstname'  => $fn,
                        'lastname'   => $ln,
                        'department' => $dept,
                        'email'      => $em
                    ];
                }
                fclose($handle);

                // ── Validation and Batch Database Execution ──
                $summary = [
                    'total'     => count($rows),
                    'imported'  => 0,
                    'updated'   => 0,
                    'skipped'   => 0,
                    'failed'    => 0,
                    'logs'      => []
                ];

                if (empty($rows)) {
                    $formErrors[] = 'No valid data rows found in the uploaded CSV file.';
                } else {
                    // Extract all unique non-empty usernames to check existence in radcheck
                    $usernamesToCheck = [];
                    foreach ($rows as $r) {
                        if ($r['username'] !== '') {
                            $usernamesToCheck[$r['username']] = true;
                        }
                    }

                    // Query existing users in batches of 500
                    $existingUsers = [];
                    $chunkedUsernames = array_chunk(array_keys($usernamesToCheck), 500);
                    foreach ($chunkedUsernames as $chunk) {
                        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                        $stmt = $db->prepare("SELECT DISTINCT username FROM radcheck WHERE username IN ($placeholders)");
                        $stmt->execute($chunk);
                        while ($existingU = $stmt->fetchColumn()) {
                            $existingUsers[$existingU] = true;
                        }
                    }

                    // Track in-file duplicates
                    $seenInFile = [];

                    // Prepared statements for insertion/updates
                    $insertRadcheck = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
                    $updateRadcheck = $db->prepare("UPDATE radcheck SET value=? WHERE username=? AND attribute IN ('Cleartext-Password', 'User-Password')");

                    $deleteGroup = $db->prepare("DELETE FROM radusergroup WHERE username=?");
                    $insertGroup = $db->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 0)");

                    if ($hasUserinfo) {
                        $insertUserinfo = $db->prepare("INSERT INTO userinfo (username, firstname, lastname, department, email, creationdate, creationby) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                        $checkUserinfo  = $db->prepare("SELECT COUNT(*) FROM userinfo WHERE username=?");
                        $updateUserinfo = $db->prepare("UPDATE userinfo SET firstname=?, lastname=?, department=?, email=?, updatedate=NOW(), updateby=? WHERE username=?");
                    }

                    $currentAdmin = $_SESSION['admin_user'] ?? 'admin';

                    // Process in chunks of 100 within transactions
                    $rowChunks = array_chunk($rows, 100);

                    foreach ($rowChunks as $chunk) {
                        $db->beginTransaction();
                        try {
                            foreach ($chunk as $r) {
                                $u    = $r['username'];
                                $p    = $r['password'];
                                $g    = $r['group'];
                                $fn   = $r['firstname'];
                                $ln   = $r['lastname'];
                                $dept = $r['department'];
                                $em   = $r['email'];
                                $line = $r['line'];

                                // Validation
                                if (!$u) {
                                    $summary['failed']++;
                                    $summary['logs'][] = ['line' => $line, 'username' => '-', 'status' => 'danger', 'msg' => 'Username is blank.'];
                                    continue;
                                }

                                if (preg_match('/\s/', $u)) {
                                    $summary['failed']++;
                                    $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'danger', 'msg' => 'Username contains spaces.'];
                                    continue;
                                }

                                if (!$p && !isset($existingUsers[$u])) {
                                    $summary['failed']++;
                                    $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'danger', 'msg' => 'Password is required for new user.'];
                                    continue;
                                }

                                // Check in-file duplicate
                                if (isset($seenInFile[$u])) {
                                    $summary['skipped']++;
                                    $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'warning', 'msg' => "Duplicate in CSV file (first seen at line {$seenInFile[$u]})."];
                                    continue;
                                }
                                $seenInFile[$u] = $line;

                                // Check database duplicate
                                $isExisting = isset($existingUsers[$u]);

                                if ($isExisting) {
                                    if ($dupMode === 'skip') {
                                        $summary['skipped']++;
                                        $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'warning', 'msg' => 'Already exists in database (skipped).'];
                                        continue;
                                    } elseif ($dupMode === 'error') {
                                        $summary['failed']++;
                                        $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'danger', 'msg' => 'Already exists in database (error mode).'];
                                        continue;
                                    } elseif ($dupMode === 'update') {
                                        // Update password if provided
                                        if ($p !== '') {
                                            $updateRadcheck->execute([$p, $u]);
                                        }

                                        // Update group if provided
                                        if ($g !== '') {
                                            $deleteGroup->execute([$u]);
                                            $insertGroup->execute([$u, $g]);
                                        }

                                        // Update userinfo if table exists
                                        if ($hasUserinfo && ($fn || $ln || $dept || $em)) {
                                            $checkUserinfo->execute([$u]);
                                            if ($checkUserinfo->fetchColumn() > 0) {
                                                $updateUserinfo->execute([$fn, $ln, $dept, $em, $currentAdmin, $u]);
                                            } else {
                                                $insertUserinfo->execute([$u, $fn, $ln, $dept, $em, $currentAdmin]);
                                            }
                                        }

                                        $summary['updated']++;
                                        $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'info', 'msg' => 'Updated credentials and profile.'];
                                        continue;
                                    }
                                }

                                // Insert new user
                                $insertRadcheck->execute([$u, $p]);
                                $existingUsers[$u] = true; // Mark as seen

                                if ($g !== '') {
                                    $insertGroup->execute([$u, $g]);
                                }

                                if ($hasUserinfo && ($fn || $ln || $dept || $em)) {
                                    $insertUserinfo->execute([$u, $fn, $ln, $dept, $em, $currentAdmin]);
                                }

                                $summary['imported']++;
                                $summary['logs'][] = ['line' => $line, 'username' => $u, 'status' => 'success', 'msg' => 'Successfully imported.'];
                            }
                            $db->commit();
                        } catch (Exception $e) {
                            $db->rollBack();
                            $summary['failed'] += count($chunk);
                            $summary['logs'][] = ['line' => '-', 'username' => '-', 'status' => 'danger', 'msg' => 'Batch database error: ' . $e->getMessage()];
                        }
                    }

                    $results = $summary;
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Bulk User Import</h4>
        <p>Import RADIUS user credentials and profiles from a CSV file</p>
    </div>
    <div class="d-flex gap-2">
        <a href="user-import.php?action=template" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Download CSV Template
        </a>
        <a href="users.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Users
        </a>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Import Error</div>
    <ul class="mb-0 ps-3">
        <?php foreach ($formErrors as $err): ?>
            <li><?= sanitize($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($results !== null): ?>
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2 text-primary"></i>Import Results Summary</h6>
        <span class="badge bg-light text-dark border">Total Rows: <?= number_format($results['total']) ?></span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="fs-4 fw-bold text-success"><?= number_format($results['imported']) ?></div>
                    <div class="small text-muted text-uppercase fw-semibold">Imported</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="fs-4 fw-bold text-info"><?= number_format($results['updated']) ?></div>
                    <div class="small text-muted text-uppercase fw-semibold">Updated</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="fs-4 fw-bold text-warning"><?= number_format($results['skipped']) ?></div>
                    <div class="small text-muted text-uppercase fw-semibold">Skipped</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="fs-4 fw-bold text-danger"><?= number_format($results['failed']) ?></div>
                    <div class="small text-muted text-uppercase fw-semibold">Failed</div>
                </div>
            </div>
        </div>

        <?php if (!empty($results['logs'])): ?>
        <h6 class="fw-semibold mb-2">Detailed Log (<?= count($results['logs']) ?> entries)</h6>
        <div class="table-responsive border rounded" style="max-height: 380px; overflow-y: auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 80px;">Line</th>
                        <th style="width: 200px;">Username</th>
                        <th style="width: 120px;">Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['logs'] as $log): ?>
                    <tr>
                        <td class="text-muted font-monospace small"><?= $log['line'] ?></td>
                        <td class="fw-semibold"><?= sanitize($log['username']) ?></td>
                        <td>
                            <span class="badge bg-<?= $log['status'] ?>">
                                <?= ucfirst($log['status'] === 'danger' ? 'Failed' : ($log['status'] === 'warning' ? 'Skipped' : ($log['status'] === 'info' ? 'Updated' : 'Success'))) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= sanitize($log['msg']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="mt-3 text-end">
            <a href="users.php" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-people me-1"></i>Go to User List
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Upload Form -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="bi bi-upload me-2 text-primary"></i>Upload CSV File
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv,text/plain" required>
                        <div class="form-text">Supported delimiters: comma (<code>,</code>), semicolon (<code>;</code>), or tab. Max 10 MB.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Existing User Handling</label>
                            <select name="dup_mode" class="form-select">
                                <option value="skip" selected>Skip (Ignore existing users)</option>
                                <option value="update">Update (Overwrite password & profile)</option>
                                <option value="error">Error (Report as failed row)</option>
                            </select>
                            <div class="form-text">Choose action when username already exists in <code>radcheck</code>.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Default Fallback Group</label>
                            <select name="default_group" class="form-select">
                                <option value="">-- None (No Group) --</option>
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?= sanitize($g) ?>"><?= sanitize($g) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Assigned if row does not specify a group.</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="text-muted small">
                            <i class="bi bi-shield-check text-success me-1"></i>Executed in safe transactions
                        </span>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-cloud-arrow-up me-1"></i>Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Instructions & Format Card -->
    <div class="col-lg-5">
        <div class="card mb-4 bg-light border-0">
            <div class="card-header bg-transparent fw-semibold py-3">
                <i class="bi bi-info-circle me-2 text-primary"></i>CSV Format Guidelines
            </div>
            <div class="card-body">
                <p class="small text-muted">You can upload a CSV with or without a header row. If headers are provided, column names are matched automatically.</p>

                <h6 class="small fw-bold text-uppercase text-secondary mb-2">Standard Column Order:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered bg-white small mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Column</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>1</td><td><code>username</code></td><td><span class="badge bg-danger">Yes</span></td></tr>
                            <tr><td>2</td><td><code>password</code></td><td><span class="badge bg-danger">Yes</span></td></tr>
                            <tr><td>3</td><td><code>group</code></td><td><span class="badge bg-secondary">Optional</span></td></tr>
                            <tr><td>4</td><td><code>firstname</code></td><td><span class="badge bg-secondary">Optional</span></td></tr>
                            <tr><td>5</td><td><code>lastname</code></td><td><span class="badge bg-secondary">Optional</span></td></tr>
                            <tr><td>6</td><td><code>department</code></td><td><span class="badge bg-secondary">Optional</span></td></tr>
                            <tr><td>7</td><td><code>email</code></td><td><span class="badge bg-secondary">Optional</span></td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="small fw-bold text-uppercase text-secondary mb-1">Example CSV Content:</h6>
                <pre class="bg-dark text-light p-2 rounded small mb-3" style="font-size: 0.78rem; line-height: 1.4;"><code>username,password,group,firstname,lastname,department,email
budi,Pass123!,Mahasiswa,Budi,Santoso,Mesin,budi@domain.ac.id
siti,Sec456#,Pegawai,Siti,Aminah,Keuangan,siti@domain.ac.id</code></pre>

                <div class="d-grid">
                    <a href="user-import.php?action=template" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Template (.CSV)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


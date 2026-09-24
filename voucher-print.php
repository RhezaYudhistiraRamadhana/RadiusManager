<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$db = getDB();

$batch = trim($_GET['batch'] ?? '');
$id    = (int)($_GET['id'] ?? 0);
$ids   = trim($_GET['ids'] ?? '');

$where = [];
$params = [];

if ($id > 0) {
    $where[] = "v.id = ?";
    $params[] = $id;
} elseif ($ids !== '') {
    $idList = array_filter(array_map('intval', explode(',', $ids)));
    if (!empty($idList)) {
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $where[] = "v.id IN ($placeholders)";
        $params = $idList;
    }
} elseif ($batch !== '') {
    $where[] = "v.batch_name = ?";
    $params[] = $batch;
}

if (empty($where)) {
    die("No vouchers selected for printing. <a href='vouchers.php'>Back to Vouchers</a>");
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT v.*,
           p.name AS plan_name,
           p.groupname AS plan_group,
           p.dl_kbps, p.ul_kbps, p.data_mb, p.time_hours,
           rc_exp.value AS expiration_date
    FROM rm_vouchers v
    LEFT JOIN rm_plans p ON p.id = v.plan_id
    LEFT JOIN radcheck rc_exp ON rc_exp.username = v.username AND rc_exp.attribute = 'Expiration'
    WHERE $whereSql
    ORDER BY v.id ASC
");
$stmt->execute($params);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($vouchers)) {
    die("No vouchers found. <a href='vouchers.php'>Back to Vouchers</a>");
}

function formatSpeed(int $kbps): string {
    if ($kbps <= 0) return 'Standard Speed';
    if ($kbps >= 1024) return round($kbps / 1024, 1) . ' Mbps';
    return $kbps . ' Kbps';
}

function formatData(int $mb): string {
    if ($mb <= 0) return 'Unlimited Data';
    if ($mb >= 1024) return round($mb / 1024, 1) . ' GB Quota';
    return $mb . ' MB Quota';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Vouchers (<?= count($vouchers) ?> Cards) - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }

        .print-toolbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: .75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,.04);
        }

        .voucher-sheet {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }

        .voucher-card {
            background: #ffffff;
            border: 2px dashed #94a3b8;
            border-radius: 12px;
            padding: 1.25rem;
            position: relative;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
            page-break-inside: avoid;
        }

        .voucher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: .6rem;
            margin-bottom: .85rem;
        }

        .voucher-brand {
            font-weight: 700;
            font-size: .95rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .voucher-batch {
            font-size: .7rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .cred-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: .65rem .85rem;
            margin-bottom: .75rem;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: .25rem;
        }
        .cred-row:last-child { margin-bottom: 0; }

        .cred-label {
            font-size: .7rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            letter-spacing: .05em;
        }

        .cred-value {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-weight: 700;
            font-size: 1.15rem;
            color: #0f172a;
            letter-spacing: .08em;
        }

        .plan-pill {
            font-size: .75rem;
            font-weight: 600;
            padding: .2rem .6rem;
            border-radius: 20px;
            background: #e0e7ff;
            color: #3730a3;
            display: inline-block;
        }

        .voucher-instructions {
            font-size: .7rem;
            color: #64748b;
            margin-top: .75rem;
            padding-top: .5rem;
            border-top: 1px dotted #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .voucher-sheet {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .voucher-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: .75rem !important;
            }
            .voucher-card {
                box-shadow: none !important;
                border: 1.5px dashed #475569 !important;
                padding: 1rem !important;
            }
            @page {
                size: A4;
                margin: 1cm;
            }
        }
    </style>
</head>
<body>

<div class="print-toolbar d-flex justify-content-between align-items-center">
    <div>
        <span class="fw-bold fs-6 me-2"><i class="bi bi-printer me-1 text-primary"></i>Print Voucher Cards</span>
        <span class="text-muted small">(<?= count($vouchers) ?> voucher<?= count($vouchers) > 1 ? 's' : '' ?> ready)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="vouchers.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Return to Vouchers
        </a>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
        </button>
    </div>
</div>

<div class="voucher-sheet">
    <div class="voucher-grid">
        <?php foreach ($vouchers as $v): ?>
        <div class="voucher-card">
            <div class="voucher-header">
                <div class="voucher-brand">
                    <i class="bi bi-wifi text-primary fs-5"></i>
                    <span><?= APP_NAME ?> Access Pass</span>
                </div>
                <div class="voucher-batch"><?= htmlspecialchars($v['batch_name']) ?></div>
            </div>

            <div class="cred-box">
                <div class="cred-row">
                    <span class="cred-label">Username:</span>
                    <span class="cred-value text-primary"><?= htmlspecialchars($v['username']) ?></span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Password:</span>
                    <span class="cred-value text-dark"><?= htmlspecialchars($v['password']) ?></span>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                    <?php if (!empty($v['plan_name'])): ?>
                        <span class="plan-pill"><i class="bi bi-speedometer2 me-1"></i><?= htmlspecialchars($v['plan_name']) ?></span>
                    <?php elseif (!empty($v['plan_group'])): ?>
                        <span class="plan-pill"><?= htmlspecialchars($v['plan_group']) ?></span>
                    <?php else: ?>
                        <span class="plan-pill">Prepaid WiFi</span>
                    <?php endif; ?>
                </div>
                <div class="text-end" style="font-size: .75rem; color: #475569;">
                    <?php if (!empty($v['dl_kbps'])): ?>
                        <span><?= formatSpeed((int)$v['dl_kbps']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($v['data_mb'])): ?>
                        • <span><?= formatData((int)$v['data_mb']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="voucher-instructions">
                <span>Connect to WiFi &bull; Enter credentials at login portal</span>
                <span>
                    <?php if (!empty($v['expiration_date'])): ?>
                        Exp: <?= htmlspecialchars($v['expiration_date']) ?>
                    <?php else: ?>
                        Valid upon first use
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>

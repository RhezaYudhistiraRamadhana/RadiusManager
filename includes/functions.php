<?php
/**
 * Shared Utility Functions
 */

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitize($input) {
    if ($input === null) return '';
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

function formatBytes($bytes, $precision = 2) {
    $bytes = (float)($bytes ?? 0);
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow   = floor(log($bytes) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function formatDuration($seconds) {
    $seconds = (int)($seconds ?? 0);
    if ($seconds <= 0) return '0s';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    $parts = [];
    if ($h > 0) $parts[] = "{$h}h";
    if ($m > 0) $parts[] = "{$m}m";
    if ($s > 0 || empty($parts)) $parts[] = "{$s}s";
    return implode(' ', $parts);
}

function paginate($total, $page, $perPage = 20) {
    $perPage = max(1, (int)$perPage);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min((int)$page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'per_page'    => $perPage,
        'total'       => (int)$total,
    ];
}

function paginationLinks($pag, $baseUrl) {
    if ($pag['total_pages'] <= 1) return '';
    $separator = (str_contains($baseUrl, '?')) ? '&' : '?';
    $p = $pag['page'];
    $tp = $pag['total_pages'];

    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    // Previous
    $html .= '<li class="page-item ' . ($p <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . h($baseUrl . $separator . 'page=' . ($p - 1)) . '">&laquo;</a></li>';

    for ($i = max(1, $p - 2); $i <= min($tp, $p + 2); $i++) {
        $html .= '<li class="page-item ' . ($i === $p ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="' . h($baseUrl . $separator . 'page=' . $i) . '">' . $i . '</a></li>';
    }

    // Next
    $html .= '<li class="page-item ' . ($p >= $tp ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . h($baseUrl . $separator . 'page=' . ($p + 1)) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Log administrative activity to rm_audit_log
 */
function auditLog(string $action, string $target = '', string $detail = ''): void {
    if (!dbTableExists('rm_audit_log')) return;
    $operator = $_SESSION['admin_user'] ?? 'system';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        dbQuery(
            "INSERT INTO rm_audit_log (operator, action, target, detail, ip_address)
             VALUES (:op, :action, :target, :detail, :ip)",
            [':op' => $operator, ':action' => $action, ':target' => $target,
             ':detail' => $detail, ':ip' => $ip]
        );
    } catch (Exception $e) {
        // Fail silently to avoid interrupting caller operations
    }
}

/**
 * Synchronize bandwidth and session limit attributes for a group in radgroupreply
 */
function syncPlanAttributes(string $groupname, int $dl_kbps, int $ul_kbps, int $time_hours = 0, int $data_mb = 0): void {
    $db = getDB();
    $managedAttrs = [
        'WISPr-Bandwidth-Max-Down',
        'WISPr-Bandwidth-Max-Up',
        'Mikrotik-Rate-Limit',
        'Session-Timeout',
        'ChilliSpot-Max-Total-Octets'
    ];
    $placeholders = implode(',', array_fill(0, count($managedAttrs), '?'));
    $del = $db->prepare("DELETE FROM radgroupreply WHERE groupname = ? AND attribute IN ($placeholders)");
    $del->execute(array_merge([$groupname], $managedAttrs));

    $ins = $db->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, ?, ':=', ?)");

    if ($dl_kbps > 0) {
        $ins->execute([$groupname, 'WISPr-Bandwidth-Max-Down', (string)($dl_kbps * 1024)]);
    }
    if ($ul_kbps > 0) {
        $ins->execute([$groupname, 'WISPr-Bandwidth-Max-Up', (string)($ul_kbps * 1024)]);
    }
    if ($dl_kbps > 0 && $ul_kbps > 0) {
        $ins->execute([$groupname, 'Mikrotik-Rate-Limit', "{$ul_kbps}k/{$dl_kbps}k"]);
    }
    if ($time_hours > 0) {
        $ins->execute([$groupname, 'Session-Timeout', (string)($time_hours * 3600)]);
    }
    if ($data_mb > 0) {
        $ins->execute([$groupname, 'ChilliSpot-Max-Total-Octets', (string)($data_mb * 1024 * 1024)]);
    }
}

/**
 * Ensure rm_settings table exists
 */
function ensureSettingsTable(): void {
    static $done = false;
    if ($done) return;
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS `rm_settings` (
            `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
            `setting_value` TEXT DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $done = true;
    } catch (Exception $e) {}
}

/**
 * Fetch a persistent system setting from rm_settings table
 */
function getSetting(string $key, mixed $default = null): mixed {
    if (!isset($GLOBALS['rm_settings_cache']) || $GLOBALS['rm_settings_cache'] === null) {
        $GLOBALS['rm_settings_cache'] = [];
        ensureSettingsTable();
        try {
            $rows = dbFetchAll("SELECT setting_key, setting_value FROM rm_settings");
            foreach ($rows as $r) {
                $GLOBALS['rm_settings_cache'][$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {}
    }
    return $GLOBALS['rm_settings_cache'][$key] ?? $default;
}

/**
 * Persist or update a system setting in rm_settings table
 */
function setSetting(string $key, mixed $value): bool {
    ensureSettingsTable();
    try {
        dbQuery(
            "INSERT INTO rm_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
            [$key, (string)$value]
        );
        if (isset($GLOBALS['rm_settings_cache']) && is_array($GLOBALS['rm_settings_cache'])) {
            $GLOBALS['rm_settings_cache'][$key] = (string)$value;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all settings as associative array
 */
function getAllSettings(): array {
    ensureSettingsTable();
    try {
        $rows = dbFetchAll("SELECT setting_key, setting_value FROM rm_settings");
        $res = [];
        foreach ($rows as $r) {
            $res[$r['setting_key']] = $r['setting_value'];
        }
        $GLOBALS['rm_settings_cache'] = $res;
        return $res;
    } catch (Exception $e) {
        return [];
    }
}



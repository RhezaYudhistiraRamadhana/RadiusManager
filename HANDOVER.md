# RadiusManager — Project Handover Document v3

**Version:** 1.9.4 → 2.x  
**Date:** 6 October 2026  
**Status:** Active Development — Phase 3 (Configurable Export System Complete, daloRADIUS Parity Complete, Group Navigation Complete)

---

## Project Overview

**RadiusManager** is a lightweight PHP web application for managing FreeRADIUS — built as a fast, clean replacement for daloRADIUS. As of v1.8.0 through v1.9.2, it has **achieved 100% full feature parity with daloRADIUS** (with modern additions including Ruijie Networks AP integration, an automated PDF/HTML User Guide generator, Subscriber Portal Email OTP password reset with IT email registration notices, full persistent database configuration in `rm_settings`, and disaster recovery schema exports). It is now proceeding through Phase 3: security hardening, notifications, and features that go beyond what daloRADIUS offers.

Originally built by **Claude (v1.0.0)**, significantly improved by **Gemini (v1.1.0 through v1.9.2)**.

**Tech Stack:**
- PHP 8.0+
- Bootstrap 5.3 (CDN)
- Chart.js 4.4 (CDN)
- Bootstrap Icons 1.11 (CDN)
- PDO MySQL / MariaDB
- Apache2 + mod_php on Ubuntu/Debian / XAMPP on Windows

**Production Scale Tested:**
- `radpostauth`: 24,158,410 rows
- `radacct`: 1,016,179 rows
- `radcheck`: 4,287 users
- All page loads: 0.03s – 0.25s

---

## Version History

| Version | Date | By | Summary |
|---|---|---|---|
| 1.0.0 | 2026-09-10 | Claude | Initial build — all core pages, installer, indexes |
| 1.1.0 | 2026-09-18 | Gemini | userinfo, CSRF, CoA, unified architecture, operators login, bug fixes |
| 1.2.0 | 2026-09-21 | Gemini | Production DB support, 75x speedup, PK windowing, smart date fallback, branding |
| 1.2.1 | 2026-09-22 | Gemini | Settings page, 200x users.php speedup, composite indexes |
| 1.3.0 | 2026-09-22 | Gemini | CSV Bulk User Import + Streaming CSV Export |
| 1.4.0 | 2026-09-22 | Gemini | 30-day bandwidth graph per user, framed IP search, Top 5 traffic leaderboard |
| 1.5.0 | 2026-09-22 | Gemini | IP Pool Management, NAS online/offline status probe |
| 1.6.0 | 2026-09-24 | Gemini | Enable/Disable users, Batch Operations, Rate Plans, Static IP, Audit Log |
| 1.7.0 | 2026-09-24 | Gemini | Concurrent sessions graph, dashboard charts, Expiry Warning, Executive Reports |
| 1.8.0 | 2026-09-24 | Gemini | Vouchers/Hotspot, Self-Service Portal, RBAC, REST API |
| 1.9.0 | 2026-09-28 | Gemini | Email OTP password change, IT email notice, Ruijie AP support, PDF User Guide |
| 1.9.1 | 2026-09-30 | Gemini | Priority 1: Brute force lockout, session fixation, SSL installer, error logging |
| 1.9.2 | 2026-10-02 | Gemini | Full daloRADIUS Config parity (rm_settings), Schema/Config/NAS export, Ruijie AP audit |
| 1.9.3 | 2026-10-06 | Gemini | Rate plan subscriber navigation link fix, Group filter dropdown, stateful pagination |
| 1.9.4 | 2026-10-06 | Gemini | Configurable CSV Export modal with field selection, scope filtering, delimiter choice, and multi-dataset downloads |

Full details in `CHANGELOG.md`.

---

## Current File Structure (v1.9.2)

```
radius-manager/
├── config.php                      — DB credentials, app constants, API_KEY, SMTP settings
├── config.sample.php               — Clean configuration template
├── auth.php                        — Bootstrap loader (imports all includes)
├── install.sh                      — Auto-installer for Ubuntu/Debian
├── install.sql                     — Idempotent performance indexes (MySQL 5.7/8.0/MariaDB)
├── CHANGELOG.md                    — Full version history
├── HANDOVER.md                     — This file
├── database/
│   ├── schema_seed.sql             — Dev schema + seed data
│   └── radius.sql                  — Production backup (2.35 GB, gitignored)
├── docs/
│   ├── generate_guide.php          — User & Administrator Guide generator
│   └── RadiusManager_User_Guide.pdf— Printable A4 manual
├── assets/
│   └── img/
│       ├── logo.svg                — Custom vector brand logo
│       └── icon.svg                — Scalable network hub icon
├── includes/
│   ├── db.php                      — PDO singleton + helpers (dbQuery, dbFetch, dbFetchAll, dbCount, dbTableExists, dbHasColumn)
│   ├── auth.php                    — Session, CSRF (csrfToken/csrfField/verifyCsrf), flash, RBAC (requireRole, hasRole, isReadOnly)
│   ├── functions.php               — formatBytes, formatDuration, sanitize, h, paginate, paginationLinks, auditLog
│   ├── mail.php                    — Pure PHP socket SMTP client, email masking, OTP mailer
│   ├── header.php                  — Sidebar nav, topbar with role badge, Bootstrap 5 CSS
│   └── footer.php                  — Bootstrap JS, Chart.js CDN
├── api/
│   ├── index.php                   — API discovery + documentation
│   ├── users.php                   — Users CRUD (GET/POST/PUT/DELETE)
│   ├── sessions.php                — Active sessions + CoA disconnect
│   └── accounting.php              — Accounting history with filters
├── portal/
│   ├── login.php                   — Subscriber self-service login
│   ├── logout.php
│   └── dashboard.php               — Subscriber dashboard (usage, sessions, OTP password change)
├── login.php                       — Admin login (config + operators table)
├── logout.php
├── index.php                       — Redirect to dashboard
├── dashboard.php                   — Stats cards, charts (7-day auth, 14-day bandwidth, concurrent, Top 5 NAS, Top 5 traffic)
├── settings.php                    — Full daloRADIUS configuration menu parity (User, DB, Lang, Log, UI, Msg, Recur, Mail, Maint, Ops, Backup) + persistent rm_settings
├── export.php                      — Streaming exports (users, accounting, auth log, audit, nas) + Schema DDL (.sql) + Config Snapshot (.json)
├── expiry-check.php                — Expiry warning dashboard + CLI cron mode
├── reports.php                     — Executive reports, print stylesheet, CSV export
├── audit.php                       — Admin activity log
├── operators.php                   — Operator management + RBAC
├── users.php                       — User list, batch ops, enable/disable toggle
├── user-add.php                    — Add user + userinfo, CSRF protected
├── user-edit.php                   — Edit user + userinfo + 30-day bandwidth graph + static IP
├── user-import.php                 — CSV bulk import
├── user-delete.php                 — Delete from radcheck, radreply, radusergroup, userinfo
├── user-toggle.php                 — Enable/disable handler (Auth-Type := Reject)
├── user-batch.php                  — Batch operations handler
├── groups.php                      — Group CRUD, check/reply attributes, member list
├── plans.php                       — Rate plan management
├── plan-add.php
├── plan-edit.php
├── plan-delete.php
├── nas.php                         — NAS list, secret toggle, online/offline probe (Ruijie/Mikrotik)
├── nas-add.php
├── nas-edit.php
├── nas-delete.php
├── vouchers.php                    — Voucher inventory dashboard
├── voucher-generate.php            — Batch voucher generator
├── voucher-print.php               — Print-ready voucher cards (A4)
├── voucher-delete.php              — Voucher deletion handler
├── accounting.php                  — Session history, date/user/IP filter, traffic summary
├── sessions.php                    — Active sessions (50/page), CoA kick, IP search, auto-refresh
├── postauth.php                    — Auth log, accept/reject filter, success rate
└── ippool.php                      — IP pool management, manual IP release
```

---

## Database Tables (v1.9.2)

| Table | Purpose | Optional |
|---|---|---|
| `radcheck` | User check attributes (`Cleartext-Password`, `Simultaneous-Use`, `Expiration`, `Auth-Type`) | No |
| `radreply` | User reply attributes (`Framed-IP-Address`, etc.) | No |
| `radusergroup` | User → group mapping | No |
| `radgroupcheck` | Group check attributes | No |
| `radgroupreply` | Group reply attributes (`WISPr-Bandwidth-Max-Down/Up`, `Mikrotik-Rate-Limit`, etc.) | No |
| `radacct` | Accounting / session history | No |
| `nas` | NAS device registry with shared secrets | No |
| `radpostauth` | Post-auth log (24M+ rows) | No |
| `radippool` | IP pool assignments | Yes — `dbTableExists()` |
| `userinfo` | Extended profiles: firstname, lastname/department, email | Yes — `dbTableExists()` |
| `operators` | Admin/operator accounts (bcrypt/MD5/cleartext) + `role` column | Yes |
| `rm_admins` | RadiusManager native admin accounts | Yes — created by install.sql |
| `rm_audit_log` | Admin activity audit trail | Yes — created by install.sql |
| `rm_plans` | Bandwidth/data rate plans | Yes — created by install.sql |
| `rm_vouchers` | Voucher/hotspot tracking | Yes — created by install.sql |
| `rm_login_attempts` | Brute force login tracking | Yes — created by install.sql (v1.9.1) |
| `rm_settings` | Persistent system & daloRADIUS configuration key-value store | Yes — created by install.sql (v1.9.2) |
| `rm_notifications` | Notification config and send log | **Phase 3 — to be created** |
| `rm_notification_settings` | Notification configuration key-value store | **Phase 3 — to be created** |

---

## Architecture Rules

> **Follow these exactly. Do not deviate.**

### 1. Page Bootstrap
```php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Page Title';
$db = getDB();
```

### 2. Database Access
```php
$rows = dbFetchAll("SELECT ...", [...]);
$row  = dbFetch("SELECT ...", [...]);
$n    = dbCount("SELECT COUNT(*) ...");
dbQuery("INSERT INTO ...", [...]);

// Always check optional tables/columns before querying:
if (dbTableExists('rm_notifications')) { ... }
if (dbHasColumn('radpostauth', 'nasipaddress')) { ... }
```

### 3. Output Sanitization
```php
echo sanitize($value);
echo h($value);
```

### 4. CSRF Protection
```php
// Inside every state-altering form:
<?= csrfField() ?>

// At the top of every POST handler:
verifyCsrf();
```

### 5. Flash Messages
```php
$_SESSION['flash'] = ['type' => 'success', 'msg' => 'Done.'];
$_SESSION['flash'] = ['type' => 'danger',  'msg' => 'Error.'];
```

### 6. Page Layout
```php
$page_title = 'My Page';
$extra_js   = '<script>/* optional page-specific JS */</script>';
include __DIR__ . '/includes/header.php';
// ... content ...
include __DIR__ . '/includes/footer.php';
```

### 7. Pagination
```php
$pagination = paginate($total, $perPage, $page);
// use $pagination['offset'] in LIMIT clause
echo paginationLinks($pagination, $queryStringArray);
```

### 8. RBAC Checks
```php
requireRole('superadmin'); // blocks operator and readonly
requireRole('operator');   // blocks readonly only
// no check = all authenticated roles can access (readonly can view)

// Hide write actions from readonly users:
<?php if (!isReadOnly()): ?>
  <button>Add User</button>
<?php endif; ?>
```

### 9. Audit Logging (Required on every write operation)
```php
auditLog('entity.action', $target, 'Human-readable detail');

// Examples:
auditLog('user.create',        $username,  'New RADIUS user created');
auditLog('user.disable',       $username,  'Set Auth-Type := Reject');
auditLog('user.delete',        $username,  'Removed from radcheck, radusergroup, userinfo');
auditLog('voucher.generate',   $batchName, '50 vouchers, plan: 1-Day-10Mbps');
auditLog('nas.delete',         $shortname, 'NAS device removed');
auditLog('plan.create',        $planName,  '10Mbps/5Mbps, 100GB quota');
```

### 10. Performance Rules (CRITICAL — 24M row tables in production)
```php
// ❌ WRONG — prevents index use, causes full table scan
WHERE DATE(acctstarttime) = CURDATE()
ORDER BY authdate DESC      // on radpostauth (24M rows)

// ✅ CORRECT — allows index range scan
WHERE acctstarttime >= :start AND acctstarttime < :end
ORDER BY id DESC            // always use primary key on large tables

// ❌ WRONG — duplicate PDO named parameter causes fatal error
WHERE username LIKE :q OR framedipaddress LIKE :q

// ✅ CORRECT — unique parameter names
WHERE username LIKE :q1 OR framedipaddress LIKE :q2
```

---

## Authentication & RBAC (v1.9.0)

**Login sources (checked in order):**
1. Config admin — `APP_ADMIN` / `APP_PASS` in `config.php`
2. `operators` table — bcrypt / MD5 / cleartext with `lastlogin` timestamp update

**Three roles:**
| Role | Access |
|---|---|
| `superadmin` | Full access — all pages, system settings, RBAC, audit log, NAS management |
| `operator` | Manage users, sessions, vouchers, groups, plans — no NAS, no operators, no audit log |
| `readonly` | View-only — all pages visible but all write buttons hidden |

**Session variables:**
```php
$_SESSION['admin_logged_in']  // bool
$_SESSION['admin_user']       // username string
$_SESSION['admin_name']       // display name
$_SESSION['admin_source']     // 'config' | 'operators'
$_SESSION['admin_role']       // 'superadmin' | 'operator' | 'readonly'
```

**REST API authentication:**
- `Authorization: Bearer <token>` or `X-API-Key: <token>` header
- Token defined as `API_KEY` in `config.php`

---

## What Is Already Done ✅ (v1.9.0 Complete)

| Feature | Page(s) | Version |
|---|---|---|
| Login (config admin + operators table, bcrypt/MD5/cleartext) | `login.php` | 1.1.0 |
| Dashboard (stats, clickable cards, 7-day auth, 14-day bandwidth, concurrent sessions, Top 5) | `dashboard.php` | 1.0.0–1.7.0 |
| User list with 2-step pagination (75x speedup) | `users.php` | 1.0.0–1.2.0 |
| Add / Edit / Delete user + userinfo fields | `user-add/edit/delete.php` | 1.0.0–1.1.0 |
| Enable / Disable user (Auth-Type := Reject) | `user-toggle.php`, `users.php` | 1.6.0 |
| Batch operations (enable, disable, delete, change group, export) | `user-batch.php`, `users.php` | 1.6.0 |
| CSV Bulk User Import | `user-import.php` | 1.3.0 |
| Streaming CSV Export (users, accounting, auth log) | `export.php` | 1.3.0 |
| 30-day bandwidth graph per user | `user-edit.php` | 1.4.0 |
| Static IP assignment per user (Framed-IP-Address) | `user-edit.php` | 1.6.0 |
| Group management + check/reply attributes | `groups.php` | 1.0.0 |
| Rate Plan / Bandwidth Package management | `plans.php` + sub-pages | 1.6.0 |
| NAS CRUD + online/offline probe (Ruijie/Mikrotik) | `nas.php` + sub-pages | 1.0.0–1.9.0 |
| Accounting history + IP/username search | `accounting.php` | 1.0.0–1.4.0 |
| Active sessions (50/page) + CoA kick + IP search | `sessions.php` | 1.0.0–1.5.0 |
| Auth log + success rate + filter | `postauth.php` | 1.0.0 |
| IP Pool management + manual release | `ippool.php` | 1.5.0 |
| Audit log | `audit.php` | 1.6.0 |
| Expiry warning (web dashboard + CLI cron) | `expiry-check.php` | 1.7.0 |
| Executive reports (print + CSV) | `reports.php` | 1.7.0 |
| Voucher / Hotspot system | `vouchers.php` + sub-pages | 1.8.0 |
| End-user self-service portal + Email OTP password reset | `portal/`, `includes/mail.php` | 1.8.0–1.9.0 |
| Comprehensive User & Administrator Guide (PDF/HTML) | `docs/generate_guide.php` | 1.9.0 |
| RBAC (superadmin / operator / readonly) | `operators.php`, all pages | 1.8.0 |
| REST API (users, sessions, accounting) | `api/` | 1.8.0 |
| Full daloRADIUS Configuration Parity + persistent rm_settings | `settings.php`, `rm_settings` | 1.9.2 |
| Disaster Recovery Schema DDL (.sql) & Config Snapshot (.json) | `export.php` | 1.9.2 |
| Ruijie Networks Hardware Audit & Controller Profile | `nas.php`, `radacct` | 1.9.0–1.9.2 |
| CSRF on all forms | All pages | 1.1.0 |
| Performance indexes + PK windowing | `install.sql` | 1.0.0–1.2.0 |
| Auto-installer (Ubuntu/Debian) | `install.sh` | 1.0.0 |
| Custom vector logo + favicon | `assets/img/` | 1.2.0 |
| Login Brute Force Protection (5 attempts/15m lockout) | `rm_login_attempts`, `login.php`, `settings.php` | 1.9.1 |
| Session Fixation Protection (`session_regenerate_id`) | `includes/auth.php`, `portal/auth.php` | 1.9.1 |
| HTTPS / SSL Installer Automation (Let's Encrypt/Certbot) | `install.sh` | 1.9.1 |
| Production Error Logging & Logs `.htaccess` Security | `config.php`, `storage/logs/` | 1.9.1 |

---

## Phase 3 Roadmap — Production Hardening & Beyond daloRADIUS

> Build in the exact order listed below.
> Security items must be completed before any Phase 3 feature goes live.
> Every write operation must continue to call `auditLog()`.

---

### 🔴 Priority 1 — Security Hardening (Fix Before Production) — COMPLETED ✅ (v1.9.1)

---

#### 1A. Login Brute Force Protection

**Files to modify:** `login.php`, `includes/auth.php`
**New DB table:** `rm_login_attempts` (add to `install.sql`)

**Table definition:**
```sql
CREATE TABLE IF NOT EXISTS rm_login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    username     VARCHAR(64)  NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip   (ip_address),
    INDEX idx_time (attempted_at)
);
```

**Logic:**
- Check attempts in last 15 minutes for this IP before processing credentials
- If attempts >= 5 → show lockout message with countdown timer, skip credential check
- On failed login → INSERT into `rm_login_attempts`
- On successful login → DELETE from `rm_login_attempts` WHERE ip_address = current IP
- Superadmin can view and clear lockouts from `settings.php`

```php
// In login.php, before credential check:
$ip      = $_SERVER['REMOTE_ADDR'];
$window  = date('Y-m-d H:i:s', strtotime('-15 minutes'));
$count   = dbCount(
    "SELECT COUNT(*) FROM rm_login_attempts
     WHERE ip_address = ? AND attempted_at > ?",
    [$ip, $window]
);
if ($count >= 5) {
    $error = "Too many failed attempts. Please wait 15 minutes.";
    // Show error only, do not process form
}
```

---

#### 1B. Session Fixation Protection

**File to modify:** `includes/auth.php`

Add immediately after setting `$_SESSION['admin_logged_in'] = true`:
```php
session_regenerate_id(true);
```

Also add to `portal/login.php` after subscriber login success.

---

#### 1C. HTTPS / SSL in Installer

**File to modify:** `install.sh`

Add after Apache configuration block:
```bash
read -rp "Configure HTTPS? (y/n) [n]: " DO_SSL
if [[ "$DO_SSL" == "y" ]]; then
    read -rp "Use Let's Encrypt (l) or self-signed (s)? [s]: " SSL_TYPE
    if [[ "${SSL_TYPE:-s}" == "l" ]]; then
        apt-get install -y certbot python3-certbot-apache
        read -rp "Domain name (e.g. radius.polman.ac.id): " DOMAIN
        certbot --apache -d "$DOMAIN" --non-interactive --agree-tos \
                -m "admin@$DOMAIN"
    else
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout /etc/ssl/private/radiusmanager.key \
            -out /etc/ssl/certs/radiusmanager.crt \
            -subj "/CN=RadiusManager/O=RadiusManager"
        a2enmod ssl
        a2ensite default-ssl
        systemctl reload apache2
    fi
fi
```

---

#### 1D. Error Logging (No Stack Traces in Browser)

**File to modify:** `config.php`
**Directory + file:** `storage/logs/.htaccess`

Add to `config.php`:
```php
define('APP_ENV',  'production'); // 'development' | 'production'
define('LOG_FILE', __DIR__ . '/storage/logs/app.log');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_FILE);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
```

Ensure `storage/logs/` directory contains:
```apache
# storage/logs/.htaccess
Deny from all
```

---

### 🟢 Priority 1.5 — daloRADIUS Configuration Parity & Hardware Audit — COMPLETED ✅ (v1.9.2)

---

#### 1.5A. Persistent System Configuration (`rm_settings`)

**Files modified:** `settings.php`, `install.sql`, `includes/functions.php`, `includes/mail.php`, `includes/header.php`, `export.php`  
**New DB table:** `rm_settings` (defined in `install.sql`)

**Table definition:**
```sql
CREATE TABLE IF NOT EXISTS `rm_settings` (
    `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**Seeded Default Keys:**
- **User Settings:** `user_allow_cleartext` ('yes'), `user_random_chars`, `user_pass_min_len` (8), `user_pass_max_len` (14), `default_user_group` ('Default'), `default_expiry_days` (30).
- **Database Settings:** `db_host` ('localhost'), `db_port` (3306), `db_name` ('radius'), `db_user` ('radius'), `radius_auth_port` (1812), `radius_acct_port` (1813).
- **Language Settings:** `default_lang` ('en'), `charset` ('UTF-8'), `timezone` ('Asia/Jakarta').
- **Logging Settings:** `app_env` ('production'), `log_errors` ('1'), `radius_log_path` ('/var/log/freeradius/radius.log').
- **Interface Settings:** `app_name` ('RadiusManager'), `rows_per_page` (20), `date_format` ('Y-m-d H:i'), `default_theme` ('light').
- **Message Settings:** `welcome_msg_template`, `otp_msg_subject`, `portal_help_notice`.
- **Recurring Tasks:** `clean_stale_sessions_days` (30), `auto_clean_otp_hours` (24).
- **Mail Transport:** `mail_transport` ('smtp'), `smtp_host`, `smtp_port` (587), `smtp_secure` ('tls'), `smtp_user`, `smtp_pass`, `mail_from`, `mail_from_name`.

**Helper Layer (`includes/functions.php`):**
- `ensureSettingsTable()`: Idempotently creates `rm_settings` if not present.
- `getSetting(string $key, mixed $default = null)`: Fetches setting with in-memory request-level cache.
- `setSetting(string $key, mixed $value)`: Persists setting with immediate cache synchronization.
- `getAllSettings()`: Retrieves all persistent keys as an associative array.

#### 1.5B. Dual-Level Configuration Navigation in `settings.php`
- **Top Pill Tabs:** `General`, `Mail`, `Maintenance`, `Operators`, `Backup`, `My Account`.
- **Global Settings Sub-Navigation (Left Sidebar under General):**
  1. `User Settings`: Password constraints, cleartext storage policy, and default profile attributes.
  2. `Database Settings`: DB host/credentials, FreeRADIUS ports (1812/1813), and live UDP socket reachability tester.
  3. `Language Settings`: Language toggle (English / Indonesian), document charset, and server timezone (`Asia/Jakarta`).
  4. `Logging Settings`: Environment mode (production/debug), error logging toggle, and live log tail monitor.
  5. `Interface Settings`: App name branding, pagination rows per page, date/time format, and color themes.
  6. `Message Settings`: New user welcome message template, OTP email subject, and portal help notice.
  7. `Recurring Tasks Settings`: Stale session retention threshold and manual **"Clean Stale Sessions Now"** button.
- **Dedicated System Tabs:**
  - `Mail`: Dynamic Socket SMTP configuration, credentials, and live **"Send Test Verification Email"** tool.
  - `Maintenance`: Server runtime diagnostics, database scale metrics, stale session cleaner, and Brute-Force IP lockout unlocker.
  - `Operators`: Directory of system administrators and database operators with link to `operators.php`.
  - `Backup`: One-click Database Schema DDL download, Configuration Snapshot export, and entity CSVs.
  - `My Account`: Operator password change and personal profile editor.

#### 1.5C. Backup & Disaster Recovery (`export.php`)
- `export.php?type=schema`: Generates full `SHOW CREATE TABLE` DDL for all 15 FreeRADIUS and custom tables.
- `export.php?type=config`: Generates a JSON snapshot of all system configuration keys with sensitive passwords masked.
- `export.php?type=nas`: Generates tabular CSV export of the NAS hardware inventory and shared secrets.

#### 1.5D. Database Hardware & Vendor Audit
- **Audit Scope:** Audited all 1,071,947 rows in `radacct` and all registered devices in `nas`.
- **Controller Architecture:** 291 NAS IP addresses in log history; 86.4% handled by `172.16.0.70` (Ruijie-AP-Core controller).
- **Physical AP Vendors:** Analyzed all 10 distinct MAC OUIs in the logs (`9C2BA6`, `C4B25B`, `C8CD55`, `7085C4`, `105F02`, `10823D`, `58B4BB`, `E05D54`, `9CCE88`, `C470AB`). Verified that **100% belong to Ruijie Networks Co., Ltd.** No other physical hardware vendors exist in the logs.

---

### 🟠 Priority 2 — Notification System

---

#### 2A. Email + WhatsApp Notification Engine

**New files:** `notifications.php`, `includes/notify.php`
**New DB tables:** `rm_notifications`, `rm_notification_settings`

> **Note on Existing Implementation:** As of v1.9.0, pure-PHP socket SMTP delivery (`includes/mail.php`) and base SMTP settings (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `MAIL_FROM`, `MAIL_FROM_NAME`, `DEV_MODE`) are **already implemented and operational** in `config.php` and `includes/mail.php`.
> Therefore, `sendEmail()` in `includes/notify.php` directly delegates to `sendMailMessage()` from `includes/mail.php`.

**New constants to add to `config.php`:**
```php
// WhatsApp via Fonnte
define('WA_ENABLED', false);
define('WA_TOKEN',   '');  // Fonnte API token
define('WA_TARGET',  '');  // Target number e.g. 628123456789

// Alert thresholds
define('ALERT_REJECT_THRESHOLD', 20); // alert if >20 rejects in 5 min
```

**`rm_notifications` table:**
```sql
CREATE TABLE IF NOT EXISTS rm_notifications (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    type      VARCHAR(50)  NOT NULL,
    channel   ENUM('email','whatsapp','both') NOT NULL,
    recipient VARCHAR(128) NOT NULL,
    subject   VARCHAR(255) DEFAULT NULL,
    message   TEXT         NOT NULL,
    status    ENUM('sent','failed') NOT NULL,
    error_msg TEXT         DEFAULT NULL,
    sent_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type    (type),
    INDEX idx_sent_at (sent_at)
);
```

**`rm_notification_settings` table:**
```sql
CREATE TABLE IF NOT EXISTS rm_notification_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(64) NOT NULL UNIQUE,
    setting_value TEXT        DEFAULT NULL
);

INSERT IGNORE INTO rm_notification_settings (setting_key, setting_value) VALUES
('alert_auth_spike',  '1'),
('alert_nas_offline', '1'),
('alert_user_expiry', '1'),
('alert_user_created','0'),
('reject_threshold',  '20'),
('expiry_warn_days',  '7');
```

**`includes/notify.php` core functions:**
```php
function sendEmail(string $to, string $subject, string $body): bool { ... }
function sendWhatsApp(string $message): bool {
    // POST to https://api.fonnte.com/send
    // Header: Authorization: <WA_TOKEN>
    // Body: target=<number>&message=<message>
}
function sendAlert(string $type, string $subject, string $body): void {
    // Send to email if SMTP_ENABLED
    // Send to WhatsApp if WA_ENABLED
    // Log to rm_notifications table
}
```

**Alert types and when they fire:**
| Type | When |
|---|---|
| `auth_spike` | Reject count in last 5 min > `ALERT_REJECT_THRESHOLD` — check from `radpostauth` |
| `nas_offline` | NAS probe in `nas.php` returns offline — fire once, not on every reload |
| `user_expiry` | When `expiry-check.php` runs and finds expiring users |
| `user_created` | After successful `user-add.php` save — if WA/email configured for user |

**`notifications.php` page:**
- Config form: SMTP settings, Fonnte token, WhatsApp number, thresholds
- Toggle each alert type on/off (saves to `rm_notification_settings`)
- "Send test notification" button
- Notification history table (last 100, paginated, ORDER BY id DESC)
- Add to sidebar under **System** section
- Requires `superadmin` role

---

#### 2B. Welcome Message on User Creation

**File to modify:** `user-add.php`

After successful user creation, if `notify.php` is available and user has email in `userinfo`:
```php
// Optional: send welcome message
if (isset($_POST['send_welcome']) && dbTableExists('rm_notifications')) {
    require_once __DIR__ . '/includes/notify.php';
    $msg = "Welcome!\n\nUsername: $username\nPassword: $password\n"
         . "Expiry: " . ($expiry ?: 'No expiry') . "\n"
         . "Plan: " . ($group ?: 'Default');
    sendAlert('user_created', "Your account is ready", $msg);
}
```

Add checkbox on `user-add.php` form:
```html
<div class="form-check mt-3">
    <input class="form-check-input" type="checkbox" name="send_welcome" id="sendWelcome">
    <label class="form-check-label small" for="sendWelcome">
        Send credentials to user via email/WhatsApp
    </label>
</div>
```

---

### 🟡 Priority 3 — Beyond daloRADIUS

---

#### 3A. FreeRADIUS Service Health Check

**File to modify:** `includes/functions.php`, `dashboard.php`, `settings.php`

```php
function checkRadiusService(): string {
    // Try systemctl first (Linux with systemd)
    $out = @shell_exec('systemctl is-active freeradius 2>/dev/null');
    if (trim($out ?? '') === 'active') return 'active';

    // Fallback: check UDP port 1812
    $sock = @fsockopen('udp://127.0.0.1', 1812, $e, $m, 0.5);
    if ($sock) { fclose($sock); return 'active'; }

    return 'inactive';
}
```

**In `dashboard.php` topbar (modify `includes/header.php`):**
```html
<?php $radiusStatus = checkRadiusService(); ?>
<span class="badge <?= $radiusStatus === 'active' ? 'bg-success' : 'bg-danger' ?>">
    <i class="bi bi-circle-fill me-1" style="font-size:.4rem"></i>
    FreeRADIUS: <?= $radiusStatus === 'active' ? 'Running' : 'Stopped' ?>
</span>
```

Cache the result for 60 seconds in `$_SESSION['radius_status_cache']` to avoid shelling out on every page load.

---

#### 3B. Dark Mode Toggle

**Files to modify:** `includes/header.php`, `includes/footer.php`

**CSS variables in `header.php`:**
```css
:root {
    --body-bg:    #f1f5f9;
    --card-bg:    #ffffff;
    --border-col: #e2e8f0;
    --text-main:  #1e293b;
    --text-muted: #64748b;
}
[data-theme="dark"] {
    --body-bg:    #0f172a;
    --card-bg:    #1e293b;
    --border-col: #334155;
    --text-main:  #e2e8f0;
    --text-muted: #94a3b8;
}
body { background: var(--body-bg); color: var(--text-main); }
.card { background: var(--card-bg); border-color: var(--border-col); }
```

**Toggle button in topbar:**
```html
<button id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Toggle dark mode">
    <i class="bi bi-moon-stars" id="themeIcon"></i>
</button>
```

**JS in `footer.php`:**
```javascript
// Apply saved theme on load
const savedTheme = localStorage.getItem('rm_theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);
document.getElementById('themeIcon').className =
    savedTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';

// Toggle on click
document.getElementById('themeToggle').addEventListener('click', () => {
    const cur  = document.documentElement.getAttribute('data-theme');
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('rm_theme', next);
    document.getElementById('themeIcon').className =
        next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
});
```

---

#### 3C. Docker Deployment Support

**New files:** `Dockerfile`, `docker-compose.yml`, `.dockerignore`

**`Dockerfile`:**
```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/radius-manager/
RUN chown -R www-data:www-data /var/www/html/radius-manager \
    && mkdir -p /var/www/html/radius-manager/logs \
    && chmod 755 /var/www/html/radius-manager/logs
```

**`docker-compose.yml`:**
```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8080:80"
    environment:
      DB_HOST: db
      DB_NAME: radius
      DB_USER: radius
      DB_PASS: radiuspass
    depends_on:
      db:
        condition: service_healthy
    volumes:
      - ./logs:/var/www/html/radius-manager/logs

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE:      radius
      MYSQL_USER:          radius
      MYSQL_PASSWORD:      radiuspass
      MYSQL_ROOT_PASSWORD: rootpass
    volumes:
      - dbdata:/var/lib/mysql
      - ./database/schema_seed.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./install.sql:/docker-entrypoint-initdb.d/02-indexes.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  dbdata:
```

**`.dockerignore`:**
```
database/radius.sql
logs/
.git/
*.md
```

---

#### 3D. Indonesian / English Language Support (i18n)

**New directory:** `lang/`
**New files:** `lang/en.php`, `lang/id.php`
**Files to modify:** `includes/functions.php`, `includes/header.php`, all page labels

**`lang/en.php`:**
```php
<?php return [
    'dashboard'        => 'Dashboard',
    'users'            => 'Users',
    'groups'           => 'Groups',
    'nas_devices'      => 'NAS Devices',
    'active_sessions'  => 'Active Sessions',
    'accounting'       => 'Accounting',
    'auth_log'         => 'Auth Log',
    'reports'          => 'Reports',
    'vouchers'         => 'Vouchers & Hotspot',
    'plans'            => 'Rate Plans',
    'audit_log'        => 'Audit Log',
    'operators'        => 'Operators & RBAC',
    'settings'         => 'Settings',
    'logout'           => 'Logout',
    'add_user'         => 'Add User',
    'search'           => 'Search',
    'total_users'      => 'Total Users',
    'active_sessions_stat' => 'Active Sessions',
    'nas_count'        => 'NAS Devices',
    'auth_today'       => 'Auth Today',
    'upload_today'     => 'Upload Today',
    'download_today'   => 'Download Today',
    'username'         => 'Username',
    'password'         => 'Password',
    'group'            => 'Group',
    'status'           => 'Status',
    'actions'          => 'Actions',
    'save'             => 'Save Changes',
    'cancel'           => 'Cancel',
    'delete'           => 'Delete',
    'online'           => 'Online',
    'offline'          => 'Offline',
    'disabled'         => 'Disabled',
];
```

**`lang/id.php`:**
```php
<?php return [
    'dashboard'        => 'Dasbor',
    'users'            => 'Pengguna',
    'groups'           => 'Grup',
    'nas_devices'      => 'Perangkat NAS',
    'active_sessions'  => 'Sesi Aktif',
    'accounting'       => 'Akuntansi',
    'auth_log'         => 'Log Autentikasi',
    'reports'          => 'Laporan',
    'vouchers'         => 'Voucher & Hotspot',
    'plans'            => 'Paket Bandwidth',
    'audit_log'        => 'Log Aktivitas',
    'operators'        => 'Operator & RBAC',
    'settings'         => 'Pengaturan',
    'logout'           => 'Keluar',
    'add_user'         => 'Tambah Pengguna',
    'search'           => 'Cari',
    'total_users'      => 'Total Pengguna',
    'active_sessions_stat' => 'Sesi Aktif',
    'nas_count'        => 'Perangkat NAS',
    'auth_today'       => 'Auth Hari Ini',
    'upload_today'     => 'Upload Hari Ini',
    'download_today'   => 'Download Hari Ini',
    'username'         => 'Nama Pengguna',
    'password'         => 'Kata Sandi',
    'group'            => 'Grup',
    'status'           => 'Status',
    'actions'          => 'Aksi',
    'save'             => 'Simpan Perubahan',
    'cancel'           => 'Batal',
    'delete'           => 'Hapus',
    'online'           => 'Online',
    'offline'          => 'Offline',
    'disabled'         => 'Dinonaktifkan',
];
```

**Helper in `includes/functions.php`:**
```php
function __t(string $key): string {
    static $strings = null;
    if ($strings === null) {
        $lang    = $_SESSION['rm_lang'] ?? 'en';
        $file    = __DIR__ . "/../lang/{$lang}.php";
        $strings = file_exists($file) ? require $file : [];
    }
    return $strings[$key] ?? $key;
}
```

**Language toggle in topbar (`includes/header.php`):**
```html
<a href="?setlang=id" class="btn btn-sm btn-outline-secondary <?= ($_SESSION['rm_lang']??'en')==='id'?'active':'' ?>">ID</a>
<a href="?setlang=en" class="btn btn-sm btn-outline-secondary <?= ($_SESSION['rm_lang']??'en')==='en'?'active':'' ?>">EN</a>
```

Handle in `auth.php` bootstrap:
```php
if (isset($_GET['setlang']) && in_array($_GET['setlang'], ['en','id'])) {
    $_SESSION['rm_lang'] = $_GET['setlang'];
}
```

**Usage in all pages:**
```php
<th><?= __t('username') ?></th>
<button><?= __t('add_user') ?></button>
```

---

#### 3E. Two-Factor Authentication (2FA) for Superadmin

**New files:** `2fa-setup.php`, `2fa-verify.php`
**Files to modify:** `login.php`, `includes/auth.php`, `install.sql`

**Add columns to operators table (add to `install.sql`):**
```sql
ALTER TABLE operators
    ADD COLUMN IF NOT EXISTS totp_secret  VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1)  NOT NULL DEFAULT 0;
ALTER TABLE rm_admins
    ADD COLUMN IF NOT EXISTS totp_secret  VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1)  NOT NULL DEFAULT 0;
```

**Login flow with 2FA:**
```
1. User submits username + password
2. Credentials valid → check if totp_enabled = 1
3. If yes → set $_SESSION['pending_2fa'] = true, redirect to 2fa-verify.php
4. If no  → login complete, set admin_logged_in = true
```

**`2fa-setup.php`:**
- Generate random 16-char base32 secret using `str_pad(base_convert(bin2hex(random_bytes(10)), 16, 32), 16, 'A')`
- Show QR code via Google Charts URL: `https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=otpauth://totp/RadiusManager:$username?secret=$secret&issuer=RadiusManager`
- Ask user to verify with a 6-digit code before saving
- On verified → UPDATE operators SET totp_secret=?, totp_enabled=1

**`2fa-verify.php`:**
- Show 6-digit input form
- Validate using TOTP algorithm (manual implementation — no library needed):
```php
function verifyTotp(string $secret, int $code): bool {
    $time  = floor(time() / 30);
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    // Decode base32 secret, HMAC-SHA1 with time, extract 6 digits
    // Check current window and ±1 window for clock drift
    foreach ([-1, 0, 1] as $offset) {
        $t   = pack('N*', 0) . pack('N*', $time + $offset);
        $key = // base32 decode $secret
        $hash = hash_hmac('sha1', $t, $key, true);
        $offset2 = ord($hash[19]) & 0xf;
        $otp = ((ord($hash[$offset2]) & 0x7f) << 24
               | (ord($hash[$offset2+1]) & 0xff) << 16
               | (ord($hash[$offset2+2]) & 0xff) << 8
               | (ord($hash[$offset2+3]) & 0xff)) % 1000000;
        if ($otp === $code) return true;
    }
    return false;
}
```
- On 3 failures → clear `$_SESSION['pending_2fa']`, redirect to login with error
- On success → set `$_SESSION['admin_logged_in'] = true`, redirect to dashboard

---

#### 3F. Global Search

**New file:** `search.php`
**File to modify:** `includes/header.php` (add search box to topbar)

**`search.php` — returns JSON:**
```php
header('Content-Type: application/json');
requireLogin();
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['users'=>[],'nas'=>[],'sessions'=>[]]); exit; }

$results = [
    'users' => dbFetchAll(
        "SELECT rc.username, ui.firstname AS name,
                (SELECT COUNT(*) FROM radacct ra
                 WHERE ra.username=rc.username AND ra.acctstoptime IS NULL) online
         FROM radcheck rc
         LEFT JOIN userinfo ui ON ui.username=rc.username
         WHERE rc.username LIKE ? GROUP BY rc.username LIMIT 8",
        ["%$q%"]),
    'nas' => dbFetchAll(
        "SELECT shortname, nasname AS ip FROM nas
         WHERE shortname LIKE ? OR nasname LIKE ? LIMIT 5",
        ["%$q%", "%$q%"]),
    'sessions' => dbFetchAll(
        "SELECT username, framedipaddress, nasipaddress
         FROM radacct WHERE acctstoptime IS NULL
         AND (username LIKE ? OR framedipaddress LIKE ?) LIMIT 5",
        ["%$q%", "%$q%"]),
];
echo json_encode($results);
```

**Search box in `includes/header.php` topbar:**
```html
<div class="position-relative ms-3" style="width:240px">
    <input type="text" id="globalSearch" class="form-control form-control-sm"
           placeholder="Search users, NAS, sessions..." autocomplete="off">
    <div id="searchResults" class="position-absolute bg-white border rounded shadow-sm w-100"
         style="top:100%;z-index:9999;display:none;max-height:400px;overflow-y:auto">
    </div>
</div>
```

**JS (add to footer.php or as inline script):**
```javascript
const input = document.getElementById('globalSearch');
const box   = document.getElementById('searchResults');
let timer;
input?.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { box.style.display = 'none'; return; }
    timer = setTimeout(async () => {
        const res  = await fetch(`search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        let html = '';
        if (data.users?.length) {
            html += '<div class="px-3 py-1 text-muted small fw-bold border-bottom">Users</div>';
            data.users.forEach(u => {
                html += `<a href="user-edit.php?username=${encodeURIComponent(u.username)}"
                    class="d-block px-3 py-2 text-decoration-none hover-bg">
                    <i class="bi bi-person me-2 text-primary"></i>${u.username}
                    ${u.name ? `<span class="text-muted small ms-1">${u.name}</span>` : ''}
                    ${u.online > 0 ? '<span class="badge bg-success ms-1">Online</span>' : ''}
                </a>`;
            });
        }
        if (data.nas?.length) {
            html += '<div class="px-3 py-1 text-muted small fw-bold border-bottom border-top">NAS</div>';
            data.nas.forEach(n => {
                html += `<a href="nas.php" class="d-block px-3 py-2 text-decoration-none">
                    <i class="bi bi-hdd-network me-2 text-warning"></i>${n.shortname}
                    <span class="text-muted small ms-1">${n.ip}</span>
                </a>`;
            });
        }
        if (!html) html = '<div class="px-3 py-3 text-muted text-center small">No results found</div>';
        box.innerHTML = html;
        box.style.display = 'block';
    }, 300);
});
document.addEventListener('click', e => {
    if (!input?.contains(e.target)) box.style.display = 'none';
});
// Keyboard shortcut: press / to focus search
document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault(); input?.focus();
    }
});
```

---

## New SQL to Add to `install.sql` for Phase 3

```sql
-- Brute force protection (Priority 1A)
CREATE TABLE IF NOT EXISTS rm_login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    username     VARCHAR(64)  NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip   (ip_address),
    INDEX idx_time (attempted_at)
);

-- Notification log (Priority 2A)
CREATE TABLE IF NOT EXISTS rm_notifications (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    type      VARCHAR(50)  NOT NULL,
    channel   ENUM('email','whatsapp','both') NOT NULL,
    recipient VARCHAR(128) NOT NULL,
    subject   VARCHAR(255) DEFAULT NULL,
    message   TEXT         NOT NULL,
    status    ENUM('sent','failed') NOT NULL,
    error_msg TEXT         DEFAULT NULL,
    sent_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type    (type),
    INDEX idx_sent_at (sent_at)
);

-- Notification settings (Priority 2A)
CREATE TABLE IF NOT EXISTS rm_notification_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(64) NOT NULL UNIQUE,
    setting_value TEXT        DEFAULT NULL
);
INSERT IGNORE INTO rm_notification_settings (setting_key, setting_value) VALUES
('alert_auth_spike',  '1'), ('alert_nas_offline', '1'),
('alert_user_expiry', '1'), ('alert_user_created','0'),
('reject_threshold',  '20'),('expiry_warn_days',  '7');

-- 2FA support (Priority 3E)
ALTER TABLE operators ADD COLUMN IF NOT EXISTS
    totp_secret  VARCHAR(64) DEFAULT NULL;
ALTER TABLE operators ADD COLUMN IF NOT EXISTS
    totp_enabled TINYINT(1)  NOT NULL DEFAULT 0;
ALTER TABLE rm_admins ADD COLUMN IF NOT EXISTS
    totp_secret  VARCHAR(64) DEFAULT NULL;
ALTER TABLE rm_admins ADD COLUMN IF NOT EXISTS
    totp_enabled TINYINT(1)  NOT NULL DEFAULT 0;
```

---

## Sidebar Navigation Updates for Phase 3

Add to `includes/header.php`:

```php
// Under System section (requires superadmin):
<?php if (hasRole('superadmin')): ?>
<a href="notifications.php" class="sidebar-link <?= $cur==='notifications'?'active':'' ?>">
    <i class="bi bi-bell"></i> <?= __t('notifications') ?>
</a>
<?php endif; ?>
```

Dark mode toggle and global search go in the **topbar**, not the sidebar.

---

## Production Environment Notes

- **FreeRADIUS DB:** MySQL, database name `radius`
- **Production NAS:** `eduroam-itb`, `RadiusPolman`
- **Operator account:** `administrator` (Set via secure environment vault / hashed in `operators` table)
- **`radpostauth`** does NOT have `nasipaddress` — always use `dbHasColumn()`
- **`userinfo`** table exists — `dbTableExists()` returns true
- **Target page load:** under 300ms on production scale
- **Never** ORDER BY unindexed column on `radpostauth` (24M rows) — always use `ORDER BY id DESC`

---

## Local Development Setup (Windows + XAMPP)

1. Start Apache + MySQL in XAMPP Control Panel
2. Open `http://localhost/phpmyadmin`
3. Create database `radius` (collation: `utf8mb4_general_ci`)
4. Import `database/schema_seed.sql`
5. Copy project to `C:\xampp\htdocs\radiusmanager\`
6. Edit `config.php`: `DB_USER=root`, `DB_PASS=''`
7. Open `http://localhost/radiusmanager/radius-manager/`
8. Login: `admin` / `admin123`

---

## Default Development Credentials

| Source | Username | Password | Notes |
|---|---|---|---|
| Config (`config.php`) | `admin` | `admin123` | Default application superadmin |
| Seed Data (`schema_seed.sql`) | `administrator` | `Admin#2026!` | Sample operator account for development |
| Production DB | `administrator` | *Managed in Vault* | Change immediately during deployment |

---

## Build Order (Phase 3)

```
SECURITY — build first, before anything else goes live:
1.  rm_login_attempts table + brute force logic   → login.php + install.sql      (1A)
2.  session_regenerate_id after login             → includes/auth.php             (1B)
3.  HTTPS option in installer                     → install.sh                    (1C)
4.  APP_ENV + error logging + logs/.htaccess      → config.php                    (1D)

NOTIFICATIONS — build next:
5.  includes/notify.php (sendEmail, sendWhatsApp, sendAlert)                      (2A)
6.  rm_notifications + rm_notification_settings tables + notifications.php         (2A)
7.  Welcome message checkbox on user-add.php                                      (2B)

BEYOND daloRADIUS — build last:
8.  checkRadiusService() + topbar badge           → includes/functions.php        (3A)
9.  Dark mode CSS vars + localStorage toggle      → header.php + footer.php       (3B)
10. Dockerfile + docker-compose.yml               → project root                  (3C)
11. lang/en.php + lang/id.php + __t() + toggle   → lang/ + includes/functions.php(3D)
12. 2fa-setup.php + 2fa-verify.php + totp columns→ new files + install.sql        (3E)
13. search.php + topbar search box + JS           → new file + header.php         (3F)
```

---

*Build one file at a time. Confirm it works before moving to the next.*
*Increment version in CHANGELOG.md after each group of related changes.*
*Keep HANDOVER.md updated after completing each priority group.*


# RadiusManager — Project Handover Document v2

**Version:** 1.5.0 → Next  
**Date:** 24 September 2026  
**Status:** Active Development — Phase 2 (daloRADIUS Feature Parity)

---

## Project Overview

**RadiusManager** is a lightweight PHP web application for managing FreeRADIUS — built as a fast, clean replacement for daloRADIUS.

Originally built by **Claude (v1.0.0)**, significantly improved by **Gemini (v1.1.0 through v1.5.0)**.

**Goal for this phase:** Achieve full feature parity with daloRADIUS.

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
- All page loads: 0.03s – 0.10s

---

## Version History

| Version | Date | By | Summary |
|---|---|---|---|
| 1.0.0 | 2026-09-10 | Claude | Initial build — all core pages, installer, indexes |
| 1.1.0 | 2026-09-18 | Gemini | userinfo integration, CSRF, CoA, unified architecture, operators login |
| 1.2.0 | 2026-09-21 | Gemini | Production DB support, 75x query speedup, PK windowing, smart date fallback, custom vector branding |
| 1.2.1 | 2026-09-22 | Gemini | Settings page, 200x users.php speedup, composite indexes |
| 1.3.0 | 2026-09-22 | Gemini | CSV Bulk User Import (user-import.php) + Multi-Module Streaming Export (export.php) |
| 1.4.0 | 2026-09-22 | Gemini | 30-day bandwidth graph, framed IP search, dashboard Top 5 traffic leaderboard |
| 1.5.0 | 2026-09-22 | Gemini | IP Pool Management (ippool.php), NAS Online/Offline Status Probing |

Full details in `CHANGELOG.md`.

---

## Current File Structure (v1.5.0)

```
radius-manager/
├── config.php                  — Pure config: DB credentials, app constants
├── auth.php                    — Bootstrap loader: imports all includes
├── install.sh                  — Auto-installer for Ubuntu/Debian
├── install.sql                 — Idempotent performance indexes (MySQL 5.7/8.0/MariaDB)
├── CHANGELOG.md                — Full version history
├── HANDOVER.md                 — This file
├── database/
│   ├── schema_seed.sql         — Lightweight local dev schema + seed data
│   └── radius.sql              — Production FreeRADIUS database backup (2.35 GB)
├── assets/
│   └── img/
│       ├── logo.svg            — Custom vector brand logo & favicon
│       └── icon.svg            — Scalable network hub icon
├── includes/
│   ├── db.php                  — PDO singleton + helpers
│   ├── auth.php                — Session management, CSRF, flash messages
│   ├── functions.php           — formatBytes, formatDuration, sanitize, h, paginate, paginationLinks
│   ├── header.php              — Sidebar nav, topbar, Bootstrap 5 CSS
│   └── footer.php              — Bootstrap JS, Chart.js CDN
├── login.php                   — Config admin + operators table (bcrypt/MD5/cleartext)
├── logout.php
├── index.php                   — Redirect to dashboard
├── dashboard.php               — Clickable stat cards, 7-day auth chart, failed logins, active sessions, Top 5 traffic
├── settings.php                — Password management & diagnostics
├── export.php                  — Streaming CSV export (Users, Accounting, Auth Log)
├── users.php                   — 2-step paginated fetch, userinfo JOIN, enable/disable toggle
├── user-add.php                — Add user + userinfo fields, CSRF protected
├── user-edit.php               — Edit user + userinfo, recent sessions, 30-day bandwidth graph
├── user-import.php             — CSV bulk user import
├── user-delete.php             — Removes radcheck, radreply, radusergroup, userinfo
├── groups.php                  — Group CRUD, check/reply attributes, member list
├── nas.php                     — NAS list, secret toggle, online/offline status probe
├── nas-add.php
├── nas-edit.php
├── nas-delete.php
├── accounting.php              — Session history, date/user/IP filter, traffic summary
├── sessions.php                — Active sessions, CoA kick, IP search, auto-refresh
├── postauth.php                — Auth log, accept/reject filter, success rate
└── ippool.php                  — IP pool management, manual IP release
```

---

## Database Tables

| Table | Purpose | Optional |
|---|---|---|
| `radcheck` | User check attributes (`Cleartext-Password`, `Simultaneous-Use`, `Expiration`, `Auth-Type`) | No |
| `radreply` | User reply attributes (`Framed-IP-Address`, etc.) | No |
| `radusergroup` | User → group mapping | No |
| `radgroupcheck` | Group check attributes | No |
| `radgroupreply` | Group reply attributes (`WISPr-Bandwidth-Max-Down/Up`, etc.) | No |
| `radacct` | Accounting / session history | No |
| `nas` | NAS device registry with shared secrets | No |
| `radpostauth` | Post-auth log (24M+ rows) | No |
| `userinfo` | Extended profiles: firstname, lastname/department, email | Yes — detected via `dbTableExists()` |
| `operators` | Admin/operator accounts with bcrypt/MD5/cleartext | Yes — detected dynamically |
| `rm_admins` | RadiusManager-native admin accounts | Yes — created by install.sql |
| `rm_audit_log` | Admin activity audit trail | Yes — to be created in Phase 2 |
| `rm_plans` | Bandwidth/data rate plans | Yes — to be created in Phase 2 |
| `radippool` | IP pool assignments | Yes — detected via `dbTableExists()` |

---

## Architecture Rules

> **Follow these exactly when adding new pages or features.**

### 1. Page Bootstrap

Every protected page must start with:

```php
require_once __DIR__ . '/auth.php';
requireLogin();
$page_title = 'Your Page Title';
$db = getDB();
```

### 2. Database Access

```php
$db   = getDB();
$rows = dbFetchAll("SELECT ...", [...]);
$row  = dbFetch("SELECT ...", [...]);
$n    = dbCount("SELECT COUNT(*) ...");
dbQuery("INSERT INTO ...", [...]);

// Always check optional tables/columns first:
if (dbTableExists('rm_audit_log')) { ... }
if (dbHasColumn('radpostauth', 'nasipaddress')) { ... }
```

### 3. Output / Sanitization

```php
echo sanitize($value);
echo h($value);
```

### 4. CSRF Protection

```php
// Inside every state-altering form:
<?= csrfField() ?>

// On every POST handler:
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
$extra_js   = '<script>/* optional page JS */</script>';
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

### 8. Performance Rules (CRITICAL)

```php
// ❌ WRONG — full table scan
WHERE DATE(acctstarttime) = CURDATE()
ORDER BY authdate DESC  // on 24M-row table

// ✅ CORRECT — index range scan
WHERE acctstarttime >= :start AND acctstarttime < :end
ORDER BY id DESC

// ❌ WRONG — duplicate PDO parameter
WHERE (username LIKE :q OR framedipaddress LIKE :q)

// ✅ CORRECT
WHERE (username LIKE :q1 OR framedipaddress LIKE :q2)
```

### 9. Audit Logging (NEW — Required for all write operations in Phase 2)

Every page that creates, updates, or deletes data must call:

```php
auditLog('action', 'target', 'detail');
// Example:
auditLog('user.disable', $username, 'Set Auth-Type := Reject');
auditLog('user.delete', $username, 'Removed from radcheck, radusergroup, userinfo');
auditLog('voucher.create', $batchName, '50 vouchers generated, plan: 1-Day-10Mbps');
```

`auditLog()` must be added to `includes/functions.php`. It inserts into `rm_audit_log` if the table exists, silently skips if not.

---

## Authentication

Login supports two sources:
1. **Config admin** — `APP_ADMIN` / `APP_PASS` from `config.php`
2. **Operators table** — bcrypt / MD5 / cleartext passwords

Session variables:
```php
$_SESSION['admin_logged_in']  // bool
$_SESSION['admin_user']       // username
$_SESSION['admin_name']       // display name
$_SESSION['admin_source']     // 'config' or 'operators'
$_SESSION['admin_role']       // 'superadmin' | 'operator' | 'readonly' (Phase 2)
```

---

## What Is Already Done ✅

| Feature | Page | Version |
|---|---|---|
| Login (dual auth source) | `login.php` | 1.0.0 |
| Dashboard with clickable stats + Top 5 | `dashboard.php` | 1.0.0 / 1.4.0 |
| User list (2-step pagination, 75x optimized) | `users.php` | 1.0.0 / 1.2.1 |
| Add / Edit / Delete user + userinfo | `user-add/edit/delete.php` | 1.0.0 / 1.1.0 |
| CSV Bulk User Import | `user-import.php` | 1.3.0 |
| Streaming CSV Export | `export.php` | 1.3.0 |
| 30-Day Bandwidth Graph per user | `user-edit.php` | 1.4.0 |
| Group management + attributes | `groups.php` | 1.0.0 |
| NAS CRUD + online/offline probe | `nas.php` + sub-pages | 1.0.0 / 1.5.0 |
| Accounting history + IP search | `accounting.php` | 1.0.0 / 1.4.0 |
| Active sessions + CoA kick + IP search | `sessions.php` | 1.0.0 / 1.4.0 |
| Auth log with filter + success rate | `postauth.php` | 1.0.0 |
| IP Pool Management | `ippool.php` | 1.5.0 |
| Settings + diagnostics | `settings.php` | 1.2.1 |
| CSRF on all forms | All pages | 1.1.0 |
| Performance indexes | `install.sql` | 1.0.0 / 1.2.1 |
| Auto-installer | `install.sh` | 1.0.0 |
| Custom vector logo & favicon | `assets/img/` | 1.2.0 |

---

## Phase 2 Roadmap — daloRADIUS Feature Parity

> Build these features **one file at a time**, in priority order.
> Follow all architecture rules above exactly.
> Every write operation must call `auditLog()`.

---

### 🔴 Priority A — Must Have (Build First)

---

#### A1. Enable / Disable Users

**File to modify:** `users.php`, `user-edit.php`  
**New file:** `user-toggle.php`

**What it does:**
- Adds a toggle button (Enable / Disable) next to each user on the users list
- Disabling inserts `Auth-Type := Reject` into `radcheck`
- Enabling removes that attribute
- Disabled users show a visual badge on the user list

**Implementation:**
```php
// Disable: INSERT into radcheck
INSERT INTO radcheck (username, attribute, op, value)
VALUES (:username, 'Auth-Type', ':=', 'Reject')

// Enable: DELETE from radcheck
DELETE FROM radcheck WHERE username=:username AND attribute='Auth-Type'
```

**UI:**
- `users.php`: add a green/red toggle icon button in the Actions column
- Show `[DISABLED]` badge in red next to disabled usernames
- `user-toggle.php`: handles POST, CSRF protected, calls `auditLog()`, redirects back

---

#### A2. Batch Operations on Users

**File to modify:** `users.php`  
**New file:** `user-batch.php`

**What it does:**
- Adds checkboxes to each row on the user list
- "Select All" checkbox in the header
- Batch action dropdown at the bottom: Enable / Disable / Delete / Change Group / Export Selected
- Submits selected usernames as `username[]` array

**Implementation:**
- `user-batch.php` receives `POST['action']` and `POST['usernames'][]`
- Loops through each username and applies the action
- Shows a results summary: X succeeded / Y failed
- CSRF protected
- Calls `auditLog()` for each action

---

#### A3. Rate Plan / Bandwidth Package Management

**New files:** `plans.php`, `plan-add.php`, `plan-edit.php`, `plan-delete.php`  
**New DB table:** `rm_plans` (local app table, not FreeRADIUS)

**What it does:**
- Create named plans: e.g. "10Mbps-100GB", "Unlimited-5Mbps", "1-Day-Trial"
- Each plan maps to a set of RADIUS group reply attributes
- Assigning a plan to a user = assigning them to that group in `radusergroup`
- Plans are stored as group attributes in `radgroupreply`

**`rm_plans` table:**
```sql
CREATE TABLE rm_plans (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    groupname   VARCHAR(64)  NOT NULL,
    description TEXT,
    dl_kbps     INT DEFAULT 0,
    ul_kbps     INT DEFAULT 0,
    data_mb     INT DEFAULT 0,
    time_hours  INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**`radgroupreply` attributes to insert per plan:**
```
WISPr-Bandwidth-Max-Down  :=  <dl_kbps * 1000>
WISPr-Bandwidth-Max-Up    :=  <ul_kbps * 1000>
Max-All-Session           :=  <time_hours * 3600>   (if time-limited)
```

**`plans.php` UI:**
- Table of all plans with: Name, Download, Upload, Data Limit, Time Limit, Users Count, Actions
- "Assign Plan" button opens a modal to select a user and assign the plan

---

#### A4. Static IP Assignment per User

**File to modify:** `user-edit.php`

**What it does:**
- Adds a "Static IP Address" field to the user edit form
- Saves `Framed-IP-Address` to `radreply` for that user
- Shows current assigned IP (if any)
- Clearing the field removes the `Framed-IP-Address` from `radreply`

**Implementation:**
```php
// Load current static IP
SELECT value FROM radreply
WHERE username=:username AND attribute='Framed-IP-Address'

// Save:
DELETE FROM radreply WHERE username=:username AND attribute='Framed-IP-Address';
if ($staticIp) {
    INSERT INTO radreply (username, attribute, op, value)
    VALUES (:username, 'Framed-IP-Address', ':=', :ip)
}
```

---

#### A5. Audit / Activity Log

**New file:** `audit.php`  
**New DB table:** `rm_audit_log`  
**Modify:** `includes/functions.php` — add `auditLog()` function

**`rm_audit_log` table (add to `install.sql`):**
```sql
CREATE TABLE IF NOT EXISTS rm_audit_log (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    operator   VARCHAR(64)  NOT NULL,
    action     VARCHAR(64)  NOT NULL,
    target     VARCHAR(128) DEFAULT NULL,
    detail     TEXT         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operator  (operator),
    INDEX idx_action    (action),
    INDEX idx_created   (created_at)
);
```

**`auditLog()` function in `includes/functions.php`:**
```php
function auditLog(string $action, string $target = '', string $detail = ''): void {
    if (!dbTableExists('rm_audit_log')) return;
    $operator = $_SESSION['admin_user'] ?? 'system';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    dbQuery(
        "INSERT INTO rm_audit_log (operator, action, target, detail, ip_address)
         VALUES (:op, :action, :target, :detail, :ip)",
        [':op'=>$operator, ':action'=>$action, ':target'=>$target,
         ':detail'=>$detail, ':ip'=>$ip]
    );
}
```

**`audit.php` page:**
- Table showing: Date/Time, Operator, Action, Target, Detail, IP Address
- Filter by operator, action type, date range
- Paginated (50/page, ORDER BY id DESC)
- Export to CSV button
- Add to sidebar nav under System section

---

### 🟠 Priority B — Reporting & Notifications (Build Second)

---

#### B1. Concurrent Sessions Graph

**File to modify:** `dashboard.php` or new `reports.php`

**What it does:**
- Line chart showing number of concurrent online users at each hour of the day
- Query `radacct` to count overlapping sessions per hour
- Shows today's curve vs yesterday as comparison

**Query approach:**
```sql
-- For each hour slot, count sessions that were active during that hour
SELECT HOUR(acctstarttime) AS hour, COUNT(*) AS concurrent
FROM radacct
WHERE acctstarttime >= :today AND acctstarttime < :tomorrow
GROUP BY HOUR(acctstarttime)
ORDER BY hour
```

---

#### B2. More Dashboard Charts

**File to modify:** `dashboard.php`

**Add these charts:**
- Sessions over last 30 days (line chart) — replace the current 7-day bar chart
- Top 5 NAS devices by traffic (bar chart) — query `radacct GROUP BY nasipaddress`
- Bandwidth trend: daily upload vs download for last 14 days (dual-line chart)

---

#### B3. Expiry Warning System

**New file:** `expiry-check.php` (can be called by cron or from Settings page)

**What it does:**
- Scans `radcheck` for users with `Expiration` attribute within next X days
- Lists them on screen (for manual action)
- Optional: sends email via PHPMailer if SMTP is configured in `config.php`

**New `config.php` constants to add:**
```php
define('SMTP_HOST',     '');
define('SMTP_PORT',     587);
define('SMTP_USER',     '');
define('SMTP_PASS',     '');
define('SMTP_FROM',     '');
define('EXPIRY_WARN_DAYS', 7);
```

---

#### B4. Scheduled / Printable Reports

**New file:** `reports.php`

**What it does:**
- Monthly summary report: total sessions, total traffic, top 10 users, top NAS
- Printable HTML layout with `@media print` CSS
- Export to CSV button
- Date range selector (default: current month)

---

### 🟡 Priority C — Advanced Features (Build Third)

---

#### C1. Hotspot / Voucher System

**New files:** `vouchers.php`, `voucher-generate.php`, `voucher-print.php`  
**New DB table:** `rm_vouchers`

**What it does:**
- Generate batches of random username/password vouchers
- Each voucher is linked to a plan (from `rm_plans`)
- Vouchers are pre-provisioned into `radcheck` and `radusergroup`
- Print-ready voucher cards (4 per page, A4)
- Track used / unused voucher status

**`rm_vouchers` table:**
```sql
CREATE TABLE IF NOT EXISTS rm_vouchers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    batch_name  VARCHAR(100) NOT NULL,
    username    VARCHAR(64)  NOT NULL UNIQUE,
    password    VARCHAR(64)  NOT NULL,
    plan_id     INT          DEFAULT NULL,
    status      ENUM('unused','active','expired') DEFAULT 'unused',
    created_by  VARCHAR(64)  DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    used_at     TIMESTAMP    DEFAULT NULL,
    INDEX idx_batch  (batch_name),
    INDEX idx_status (status)
);
```

**`voucher-generate.php`:**
- Select plan, quantity (1–200), prefix, validity days
- Generate random alphanumeric username/password pairs
- Insert into `radcheck` (password + expiry), `radusergroup`, `rm_vouchers`
- CSRF protected, audit logged

**`voucher-print.php`:**
- Renders selected batch as printable cards
- Each card shows: SSID/network name, Username, Password, Plan name, Expiry
- `@media print` CSS for clean printing

---

#### C2. User Self-Service Portal

**New directory:** `portal/`  
**New files:** `portal/index.php`, `portal/login.php`, `portal/logout.php`, `portal/dashboard.php`

**What it does:**
- Completely separate login page for end users (not admins)
- Users log in with their own RADIUS username/password
- Authenticates by checking `radcheck` directly (Cleartext-Password)
- Shows their own usage: last sessions, traffic used, account expiry, group/plan
- Cannot see or modify other users

**portal/dashboard.php shows:**
- Account status (active / disabled / expired)
- Account expiry date
- Plan/group name
- Total traffic used this month
- Last 5 sessions (date, duration, traffic, IP)
- Change password form (updates `radcheck`, CSRF protected)

---

#### C3. Role-Based Access Control (RBAC)

**Files to modify:** `includes/auth.php`, all existing pages  
**File to modify:** `settings.php` or new `operators.php`

**Three roles:**

| Role | Permissions |
|---|---|
| `superadmin` | Full access — all pages, system settings, RBAC management |
| `operator` | Manage users, sessions, vouchers — no NAS, no system settings, no audit log |
| `readonly` | View only — all pages visible but no create/edit/delete buttons shown |

**Implementation:**
- Add `role` column to `operators` table: `ENUM('superadmin','operator','readonly') DEFAULT 'operator'`
- Add `requireRole('superadmin')` helper in `includes/auth.php`
- Add role checks at the top of restricted pages:
```php
requireRole('superadmin'); // only superadmin can access
requireRole('operator');   // operator and above
// no check = all logged-in roles allowed (readonly can view)
```
- Hide create/edit/delete buttons for `readonly` role using:
```php
<?php if ($_SESSION['admin_role'] !== 'readonly'): ?>
  <button ...>Add User</button>
<?php endif; ?>
```

---

#### C4. RESTful JSON API

**New directory:** `api/`  
**New files:** `api/index.php`, `api/users.php`, `api/sessions.php`, `api/auth.php`

**Authentication:** API key in `Authorization: Bearer <key>` header  
**Key stored in:** `config.php` as `define('API_KEY', 'your-secret-key')`

**Endpoints:**

| Method | Endpoint | Action |
|---|---|---|
| `GET` | `/api/users` | List users (paginated) |
| `GET` | `/api/users/:username` | Get single user details |
| `POST` | `/api/users` | Create user |
| `PUT` | `/api/users/:username` | Update user password/group |
| `DELETE` | `/api/users/:username` | Delete user |
| `GET` | `/api/sessions` | List active sessions |
| `GET` | `/api/accounting` | Session history (date filter) |
| `POST` | `/api/sessions/:id/disconnect` | CoA kick session |

**Response format:**
```json
{
  "success": true,
  "data": { ... },
  "meta": { "total": 100, "page": 1, "per_page": 25 }
}
```

---

## Sidebar Navigation Updates

Add these items to `includes/header.php` as features are built:

```php
// Under RADIUS section:
<a href="plans.php">    Rate Plans        </a>   // A3
<a href="vouchers.php"> Vouchers/Hotspot  </a>   // C1

// Under Reporting section:
<a href="reports.php">  Reports           </a>   // B4

// Under System section:
<a href="audit.php">    Audit Log         </a>   // A5
<a href="operators.php">Operators & RBAC  </a>   // C3
```

---

## New DB Tables to Create (add to `install.sql`)

```sql
-- Audit log (A5)
CREATE TABLE IF NOT EXISTS rm_audit_log ( ... );

-- Rate plans (A3)
CREATE TABLE IF NOT EXISTS rm_plans ( ... );

-- Vouchers (C1)
CREATE TABLE IF NOT EXISTS rm_vouchers ( ... );

-- RBAC: add role column to operators (C3)
ALTER TABLE operators ADD COLUMN IF NOT EXISTS
  role ENUM('superadmin','operator','readonly') NOT NULL DEFAULT 'operator';
```

---

## Production Environment Notes

- **FreeRADIUS DB:** MySQL, database name `radius`
- **Production NAS devices:** `eduroam-itb`, `RadiusPolman`
- **Operator account:** `administrator` / `4dm1nNamloP`
- **`radpostauth`** does NOT have `nasipaddress` column — always use `dbHasColumn()`
- **`userinfo`** table exists — `dbTableExists()` returns true
- **Target page load:** under 300ms on production scale
- **Never** run `COUNT(*)` or `ORDER BY` on unindexed columns on `radpostauth` (24M rows)

---

## Local Development Setup (Windows + XAMPP)

1. Start Apache + MySQL in XAMPP Control Panel
2. Open `http://localhost/phpmyadmin`
3. Create database `radius` (collation: `utf8mb4_general_ci`)
4. Import `database/schema_seed.sql` (dev) or `database/radius.sql` (production)
5. Copy project to `C:\xampp\htdocs\radiusmanager\`
6. Edit `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'radius');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
7. Open `http://localhost/radiusmanager/radius-manager/`
8. Login: `admin` / `admin123`

---

## Default Credentials

| Source | Username | Password |
|---|---|---|
| Config (config.php) | `admin` | `admin123` |
| Operators table (production) | `administrator` | `4dm1nNamloP` |

---

## Build Order Recommendation

Build in this exact order for fastest visible progress:

1. `user-toggle.php` + modify `users.php` — **A1 Enable/Disable** (easiest win)
2. `user-batch.php` + modify `users.php` — **A2 Batch Operations**
3. `audit.php` + `auditLog()` in functions.php — **A5 Audit Log** (needed by everything after)
4. `plans.php` + sub-pages — **A3 Rate Plans**
5. Modify `user-edit.php` — **A4 Static IP**
6. Modify `dashboard.php` — **B2 More Charts**
7. `reports.php` — **B4 Reports**
8. `expiry-check.php` — **B3 Expiry Warnings**
9. `dashboard.php` concurrent graph — **B1**
10. `vouchers.php` + sub-pages — **C1 Vouchers**
11. `portal/` directory — **C2 Self-Service Portal**
12. RBAC across all pages — **C3**
13. `api/` directory — **C4 REST API**

---

*Please build one file at a time. After each file, confirm it works before moving to the next.*  
*Update `CHANGELOG.md` with every version increment.*  
*Keep `HANDOVER.md` in sync after completing each priority group.*

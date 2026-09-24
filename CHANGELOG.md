# Changelog

All notable changes to the **RadiusManager** project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.0] - 2026-09-24

### Added
- **Concurrent Sessions Hourly Graph (`dashboard.php`) [Priority B1]**:
  - 24-hour hourly session profile comparison graph on Dashboard.
  - Compares today's session trajectory against yesterday's hourly pattern.
  - Integrated 120-second session-level cache (`$_SESSION['dash_b_data']`) for sub-10ms response time on subsequent page loads.
- **Advanced Dashboard Bandwidth & NAS Charts (`dashboard.php`) [Priority B2]**:
  - 14-day bandwidth volume trend stacked line/bar chart displaying daily upload vs download traffic in MB.
  - Top 5 NAS Devices horizontal bar chart comparing aggregate bandwidth consumption across access points.
  - Fully responsive Chart.js visual cards with clean legends and hover tooltips.
- **Account Expiry Warning System (`expiry-check.php`, `config.php`) [Priority B3]**:
  - Configurable warning window via `EXPIRY_WARN_DAYS` (default 7 days).
  - Dual-mode operation:
    - **Web Interface**: Interactive dashboard displaying categorized alerts (Expired, Expiring Soon, Monitored Active). One-click quick extension buttons (+7d, +30d, +90d), disable account action, and direct user profile links.
    - **CLI / Cron Execution**: Headless execution (`php expiry-check.php --cli`) suitable for automated nightly cron jobs. Logs audits directly to `rm_audit_log`.
  - Added "Expiry Warnings" navigation link under RADIUS section in sidebar (`includes/header.php`).
- **Printable Executive Reports & Data Analytics (`reports.php`) [Priority B4]**:
  - Comprehensive reporting engine with dynamic date range selector and quick presets ("This Month", "Last Month", "Last 30 Days", "This Year").
  - Summary KPI cards: Total Sessions, Unique Users, Total Bandwidth (Upload/Download breakdown), and Average Session Duration.
  - Top 10 Consumers table with batch profile resolution from `userinfo`, session counts, and bandwidth totals.
  - Bandwidth consumption breakdown by user group (`radusergroup`).
  - Access Point distribution table summarizing traffic and session counts across all NAS devices.
  - Executive A4 Print stylesheet (`@media print`) stripping navigation and topbar for clean PDF/paper reporting.
  - Streaming CSV export (`reports.php?export=csv`) for external spreadsheet analysis.
  - Added "Reports" navigation link under Reporting section in sidebar (`includes/header.php`).

---

## [1.6.0] - 2026-09-24

### Added
- **User Enable / Disable (`user-toggle.php`, `users.php`, `user-edit.php`)**:
  - Implemented Priority A1 from Handover Document v2: soft-disable user accounts via standard FreeRADIUS `Auth-Type := Reject`.
  - Disabling immediately halts authentication without mutating passwords or user profile data.
  - Enabling removes `Auth-Type := Reject` from `radcheck`.
  - Added visual `[DISABLED]` badges and status pills across `users.php` and `user-edit.php`.
  - Integrated one-click Enable/Disable action buttons with CSRF protection and audit logging.
- **User Batch Operations (`user-batch.php`, `users.php`)**:
  - Implemented Priority A2 from Handover Document v2: multi-select table checkboxes with "Select All" master toggle.
  - Floating sticky action toolbar (`#batchActionBar`) showing live selection count and action selector.
  - Supports bulk Enable (`enable`), bulk Disable (`disable`), bulk Group Reassignment (`change_group`), bulk CSV Export (`export`), and bulk Permanent Deletion (`delete`).
  - Batch group reassignment executes in a single transactional query with `auditLog` recording.
- **Rate Plans & Bandwidth Package Management (`plans.php`, `plan-add.php`, `plan-edit.php`, `plan-delete.php`)**:
  - Implemented Priority A3 from Handover Document v2: full rate plan management engine backed by `rm_plans` table.
  - Automatic bidirectional RADIUS synchronization with `radgroupreply`:
    - `WISPr-Bandwidth-Max-Down` (calculated in bps)
    - `WISPr-Bandwidth-Max-Up` (calculated in bps)
    - `Mikrotik-Rate-Limit` (standard `rx/tx` e.g. `5120k/10240k`)
    - `Session-Timeout` (in seconds if session limit defined)
    - `ChilliSpot-Max-Total-Octets` (in bytes if data quota defined)
  - Rate Plans page displays speed tiers, data quotas, session validity, and subscriber counts per plan.
  - Added "Rate Plans" link under RADIUS section in sidebar navigation (`includes/header.php`).
- **Static IP Assignment per User (`user-edit.php`, `user-add.php`, `users.php`)**:
  - Implemented Priority A4 from Handover Document v2: per-user static IP provisioning using `Framed-IP-Address` in `radreply`.
  - Form validation with `FILTER_VALIDATE_IP` (IPv4) on user creation and edit.
  - Displays assigned static IP address with network icon in user list on `users.php`.
  - Transactional update/removal and audit logging.
- **Administrator Activity & Audit Trail (`audit.php`, `rm_audit_log`, `export.php`)**:
  - Implemented Priority A5 from Handover Document v2: centralized audit log engine capturing operator, action, target, detail, and IP address.
  - Dedicated Audit Log Viewer page (`audit.php`) with filters for operator, action type, keyword, date range, and pagination.
  - Full CSV export support via `export.php?type=audit`.
  - Integrated "Audit Log" link under System section in sidebar navigation (`includes/header.php`).
- **Database Schema Updates**:
  - `install.sql` and `database/schema_seed.sql`: Added `rm_audit_log` and `rm_plans` table definitions with indexes.

---

## [1.5.0] - 2026-09-22

### Added
- **IP Pool Management (`ippool.php`)**:
  - Implemented Priority 3 feature from roadmap: comprehensive IP pool browser and lease management engine.
  - Dynamic table detection via `dbTableExists('radippool')` with fallback installation guide card.
  - Overview stat cards tracking Total IPs, Active Leases, Available/Free IPs, and Total Configured Pools.
  - SARGable search (by IP or username), pool filter dropdown, and lease status filter (Active vs Available).
  - Numerical IP address sorting using MySQL `INET_ATON(framedipaddress)` for correct IPv4 ordering.
  - Batch profile resolution from `userinfo` for active leaseholders in sub-millisecond query time.
  - Manual IP lease release action (`UPDATE radippool SET expiry_time = NOW() - INTERVAL 1 SECOND, username = ''`) with CSRF token verification.
  - Provisioning modal supporting both single IP addresses and batch IP ranges (up to 512 addresses per submission).
  - Integrated conditional "IP Pools" navigation link in sidebar (`includes/header.php`).
- **NAS Online/Offline Status Probing (`nas.php`)**:
  - Implemented Priority 3 feature from roadmap: automated live reachability checking for registered NAS devices.
  - Special wildcard subnet detection (e.g. `0.0.0.0/0`) displaying a distinct `Subnet` badge instead of false offline alerts.
  - Fast ICMP probing (350ms timeout) with fallback port 1812 socket probe (`fsockopen`).
  - 60-second session caching (`$_SESSION['nas_status_cache']`), keeping warm page loads under 110ms.
  - Visual status column with pulsing green `Online` badge (with round-trip latency tooltip), red `Offline` badge, and gray `Subnet` badge.
  - "Check Status" button in header allowing administrators to bypass cache and re-probe on demand.
- **Database Schema Enhancements**:
  - `install.sql`: Added `idx_username` index definition for `radippool`.
  - `database/schema_seed.sql`: Added `radippool` table structure and sample seed rows for local dev.

---

## [1.4.0] - 2026-09-22

### Added
- **Interactive 30-Day Bandwidth Graph per User (`user-edit.php`)**:
  - Implemented Priority 2 feature: Chart.js dual line time-series graph displaying daily upload and download trends.
  - SARGable index-covered query anchored to user's latest active session date (sub-40ms execution on 1.01M records).
  - Continuous 30-day calendar zero-filling with dual-color palette (Upload: blue `#2563eb`, Download: green `#10b981`), smooth curves, and human-readable byte tooltips.
  - Displays 30-day cumulative total traffic badge in the card header.
- **Search by Framed IP Address (`accounting.php` & `sessions.php`)**:
  - Implemented Priority 2 feature: dedicated secondary search input to trace which user was assigned a specific IP address.
  - `accounting.php`: Added `ip` search parameter with `framedipaddress LIKE :ip` condition, preserved in pagination and CSV exports.
  - `sessions.php`: Separated search into "Username / MAC" and "Framed IP", preserving both across active session pagination.
  - `export.php`: Extended accounting CSV export to respect active `ip` filter parameter.
- **Top 5 Traffic Users Leaderboard (`dashboard.php`)**:
  - Implemented Priority 2 feature: dynamic top 5 bandwidth consumers card on the dashboard.
  - SARGable index range scan anchored to the active traffic date, returning top consumers with session counts and download/upload split.
  - Batch resolves profile names from `userinfo` in 0.5ms.
  - Responsive 2-column dashboard layout pairing Active Sessions (`col-lg-7`) with Top 5 Traffic Users (`col-lg-5`) with rank badges and visual progress bars.

### Optimized
- **Dashboard High-Performance Optimization (`dashboard.php`)**:
  - Replaced filesort `ORDER BY acctstarttime DESC LIMIT 8` on 2,092 active sessions with clustered primary key index scan `ORDER BY radacctid DESC LIMIT 8`, reducing query latency from 532.5ms to 1.47ms (360x speedup).
  - Added 120-second session-level cache (`$_SESSION['dash_chart_data']`) for the 7-day authentication trend analytics, eliminating repetitive multi-million row scans over the 24.15M-row `radpostauth` table.
  - Derived the recent authentication stat card metric directly from the 7-day trend series, eliminating a redundant 29ms full-day aggregation query.
  - Total warm dashboard render latency reduced from ~1,500ms to ~100ms.
- **Database Schema Indexes (`install.sql`)**:
  - Added composite index `idx_stop_start (acctstoptime, acctstarttime)` on `radacct` to accelerate compound queries filtering for active sessions sorted by start time.


---

## [1.3.0] - 2026-09-22

### Added
- **CSV Bulk User Import (`user-import.php`)**:
  - Implemented Priority 1 feature from roadmap: automated bulk-provisioning of RADIUS accounts and profiles from CSV uploads.
  - Multi-delimiter auto-detection (commas, semicolons, tabs) and automatic column header matching.
  - Flexible duplicate-handling strategies (`skip` existing users, `update` password/groups/profile, or `error`).
  - Batch transactional execution (chunked in 100-row transactions) with pre-indexed user existence verification.
  - Generates downloadable CSV template (`radius_users_template.csv`) with UTF-8 BOM encoding for Excel compatibility.
  - Interactive import results dashboard reporting counts and row-by-row audit logs with status badges.
- **Multi-Module Streaming CSV Export Engine (`export.php`)**:
  - Implemented Priority 1 feature from roadmap: high-performance unbuffered CSV export engine.
  - **User List Export (`type=users`)**: Exports all usernames, passwords, groups, profiles, and live online statuses (respecting active search filters).
  - **Accounting History Export (`type=accounting`)**: Streams session logs with formatted durations and byte metrics, strictly respecting active date ranges and username queries.
  - **Authentication Log Export (`type=postauth`)**: Uses index-ordered scanning to stream up to 30,000 log records from the 24.15M-row database without memory exhaustion.
  - Includes UTF-8 BOM across all generated files for seamless Microsoft Excel compatibility.
- **UI & Navigation Enhancements**:
  - Added "Import CSV" and "Export CSV" buttons to `users.php`.
  - Added "Export CSV" buttons with active filter preservation to `accounting.php` and `postauth.php`.
  - Updated active navigation highlighting in `includes/header.php` for `user-import.php`.

---

## [1.2.1] - 2026-09-22

### Added
- **Settings & Administration Page (`settings.php`)**:
  - Implemented Priority 1 feature from project handover: full UI-based password management for both database `operators` (using modern bcrypt with expanded `VARCHAR(255)` storage) and fallback `config.php` admin (stored persistently in `rm_admins` table).
  - Current password verification and validation on change.
  - Interactive show/hide eye toggles on all password fields.
  - System diagnostics widget showing real-time PHP version, MariaDB/MySQL version, database host, port, session timeout, and pagination settings.
  - Instant metadata-based capacity monitoring (reporting 24.15M auth records, 1.01M accounting records, 4.28K users, and NAS counts in under 2ms).
  - Directory listing of registered operators and native admins with last login times.
  - Added "Settings" navigation link to the sidebar under "System" and made the topbar user badge clickable.
- **Custom Vector Branding & Modern Scrollbars**:
  - Created standalone SVG brand logo (`assets/img/logo.svg` & `icon.svg`) featuring a FreeRADIUS gateway hub with LED indicators and concentric broadcast waves.
  - Replaced browser OS scrollbars with modern, slim, rounded pill scrollbars across the global application and dark sidebar.

### Changed & Reorganized
- **Project Structure Consolidation**:
  - Moved `database/`, `CHANGELOG.md`, `HANDOVER.md`, and comprehensive `README.md` directly into the `radius-manager/` directory.
  - Adjusted all relative paths in documentation and scripts.
- **Fixed SQL Parameter Reuse in `sessions.php`**:
  - Resolved `SQLSTATE[HY093]: Invalid parameter number` by utilizing distinct parameter markers (`:q1`, `:q2`, `:q3`) for native PDO compatibility.
- **200x User List Performance Speedup (`users.php`)**:
  - Eliminated expensive correlated `radacct` subquery from the main 20-user fetch query that was forcing MariaDB to evaluate 1.01M accounting rows inside a multi-table `GROUP BY` (dropping query latency from 2,345 ms down to 11 ms).
  - Replaced it with an indexed batch lookup: `SELECT DISTINCT username FROM radacct WHERE username IN (...) AND acctstoptime IS NULL` and added composite index `idx_user_stoptime(username, acctstoptime)`.
  - Full `users.php` page load time reduced from ~2.5 seconds down to **90 ms**!

---

## [1.2.0] - 2026-09-21

### Added
- **Full Production Database Connection (`database/radius.sql`)**:
  - Successfully imported and connected 100% of the real 2.35 GB production database (`database/radius.sql` — 2,351,584,461 bytes) without altering the original dump file.
  - Production dataset loaded: **24,158,410** authentication logs in `radpostauth`, **1,016,179** accounting sessions in `radacct`, **4,287** users in `radcheck`, **4,284** profiles in `userinfo`, **4,041** group memberships in `radusergroup`, **2** production NAS devices (`eduroam-itb`, `RadiusPolman`), and **1** operator account (`administrator`).
- **Interactive Dashboard Navigation**:
  - Converted all stat cards (`Total Users`, `Active Sessions`, `NAS Devices`, `Auth Today / Recent Auth`, `Upload Today`, `Download Today`) into clickable navigation cards linking directly to their respective management modules (`users.php`, `sessions.php`, `nas.php`, `postauth.php`, `accounting.php`).
  - Added CSS micro-interactions: card lift on hover (`transform: translateY(-2px)`), dynamic shadow, and indicator chevron icons.
  - Made recent failed auth table rows and active session table rows clickable, linking directly to filtered views for the specific user.
- **Server-Side Pagination for Active Sessions**:
  - `sessions.php`: Added pagination (50 sessions per page) with total count display and navigation links to gracefully handle 2,092 concurrent active sessions without bloating browser DOM with 3 MB of HTML payload.
- **Smart Date Fallback for Historical Production Dumps**:
  - `dashboard.php`, `accounting.php`, and `postauth.php`: Added automatic date detection so that when connected to historical dumps (where latest activity predates the current calendar date), the system anchors to the latest active dates rather than displaying empty zeros.

### Changed & Optimized
- **High-Scale Performance Indexing on 24M+ Records**:
  - Created composite B-tree index `idx_authdate` on `radpostauth(authdate)` and `idx_username` on `radpostauth(username(64))` across the 24.1 million rows.
  - Date filtering and counting queries on `radpostauth` dropped from 25–30 second timeouts to sub-100 millisecond range scans.
- **2-Step Paginated User Fetch (`users.php`)**:
  - Replaced heavy full-table multi-join query (`radcheck` x `userinfo` x `radusergroup` x `radacct`) that evaluated subqueries across all 4,287 users on every load (11.56 seconds) with an optimized 2-step paginated fetch:
    1. Paginate 20 usernames first matching search query and offset (0.005s).
    2. Fetch joined details only for those 20 usernames via `WHERE rc.username IN (...)` (0.104s).
  - Reduced `users.php` page load time from **11.56s to 0.159s (~75x speedup)**.
- **Primary Key Windowing on `radpostauth`**:
  - `dashboard.php`: Switched recent failed logins query from unindexed `ORDER BY authdate DESC` (which timed out) to `ORDER BY id DESC LIMIT 6` on primary key (executes in 1.4 milliseconds).
  - `dashboard.php`: 7-day authentication chart now windows using indexed ID boundaries (`WHERE id >= max_id - 200,000`), generating the 7-day volume breakdown in **137 milliseconds** on 24.1 million rows.
  - `postauth.php`: Switched record list ordering from `ORDER BY authdate DESC` to `ORDER BY id DESC LIMIT :limit OFFSET :offset` for instant pagination.

### Fixed
- **Prepared Statement Parameter Re-use in Search**:
  - Fixed PDO exception `Invalid parameter number` under native prepared statements by using positional placeholders for multi-column search (`rc.username LIKE ? OR ui.firstname LIKE ? ...`).
- **Dashboard & Postauth 30-Second HTTP Timeouts**:
  - Resolved all execution bottlenecks on 24M+ row queries, bringing every page response time to between **0.10s and 0.25s**.

---

## [1.1.0] - 2026-09-18

### Added
- **`database/schema_seed.sql`**: A lightweight standalone database schema and seed dataset mirroring production structures (`radcheck`, `radreply`, `radusergroup`, `radgroupcheck`, `radgroupreply`, `nas`, `radacct`, `radpostauth`, `userinfo`, `operators`), allowing rapid local testing and development without loading the 2.35 GB production dump (`radius.sql`).
- **`userinfo` Table Integration**:
  - `users.php`: Now performs a `LEFT JOIN` with the `userinfo` table to display student/staff full names (`firstname`) and department/faculty badges (`department` / `lastname`) alongside account usernames.
  - Search filter in `users.php` now searches across usernames, real names, departments, and email addresses.
  - `user-add.php` & `user-edit.php`: Added fields to view and update Full Name, Department/Class, and Email address stored directly in `userinfo`.
  - `user-delete.php`: Automatically purges corresponding profile records from `userinfo` when a user is deleted.
- **Multi-Source Authentication**:
  - `includes/auth.php` & `login.php`: Supports logging in via config credentials (`admin` / `admin123`) **and** existing FreeRADIUS database operator accounts from the `operators` table (e.g., `administrator` / `4dm1nNamloP`), supporting Bcrypt, MD5, and cleartext legacy formats with automatic `lastlogin` timestamps.
  - Display full admin/operator name in the top navigation bar.
- **CSRF Token Security**:
  - Added cryptographic CSRF token generation (`csrfToken()`, `csrfField()`) and enforcement (`verifyCsrf()`) across all state-altering forms (User Add/Edit/Delete, NAS Add/Edit/Delete, Group Create/Delete, Attribute Add/Delete, and CoA Disconnect).
- **Database Helper Methods**:
  - `includes/db.php`: Added `dbTableExists()` and `dbHasColumn()` with per-request static caching for dynamic schema inspection.
- **Enhanced CoA / Kick Session Command**:
  - `sessions.php`: Added dynamic NAS lookup to generate the exact FreeRADIUS `radclient` Disconnect-Request CoA command with the router's registered shared secret.

### Changed
- **Unified Application Architecture**:
  - `config.php`: Streamlined into a pure configuration file; removed conflicting inline `getDB()` declaration.
  - `includes/db.php`: Centralized PDO singleton connection with UTF-8 character encoding, exception mode, and query helpers (`dbQuery`, `dbFetch`, `dbFetchAll`, `dbCount`).
  - `includes/auth.php`: Unified session lifetime checks, flash messaging helpers, and authentication routines.
  - `includes/functions.php`: Centralized formatting (`formatBytes`, `formatDuration`), sanitization (`sanitize`, `h`), and pagination helpers (`paginate`, `paginationLinks`).
  - `auth.php`: Serves as the primary bootstrap loader that imports `config.php`, `includes/db.php`, `includes/auth.php`, and `includes/functions.php`.
- **Idempotent Database Index Installer (`install.sql`)**:
  - Replaced MariaDB-specific `ADD INDEX IF NOT EXISTS` syntax with safe stored procedures (`rm_add_index_if_missing`) compatible with MySQL 5.7, MySQL 8.0, and MariaDB 10.3+.
  - Added indexes for `radpostauth(authdate)`, `radcheck(attribute)`, `radusergroup(groupname)`, and `userinfo(username)`.
- **Auto-Installer Script (`install.sh`)**:
  - Updated to output clean, modular `config.php` requiring `includes/db.php`.

### Fixed
- **`radpostauth` Missing Column Crash (`dashboard.php` & `postauth.php`)**:
  - Legacy schemas (like the production dump `radius.sql`) do not include the `nasipaddress` column in `radpostauth`. Previous code crashed with a fatal PDO exception (`Unknown column 'nasipaddress'`). Added dynamic column detection via `dbHasColumn()` to seamlessly query `NULL AS nasipaddress` when absent.
- **PDO Native Prepared Statement LIMIT/OFFSET Syntax Error**:
  - In `users.php`, `accounting.php`, and `postauth.php`, passing string-bound variables into `LIMIT :limit OFFSET :offset` with `ATTR_EMULATE_PREPARES => false` triggered MySQL fatal syntax error 1064. Fixed by casting pagination variables to explicit integers.
- **Non-SARGable Query Performance on Large Tables (18M+ & 48M+ records)**:
  - `dashboard.php`: Replaced `WHERE DATE(acctstarttime) = CURDATE()` with `WHERE acctstarttime >= CURDATE() AND acctstarttime < CURDATE() + INTERVAL 1 DAY`, allowing MySQL to perform index range scans on `radacct(acctstarttime)`.
  - `accounting.php`: Replaced `WHERE DATE(acctstarttime) BETWEEN :from AND :to` with `WHERE acctstarttime >= :from_dt AND acctstarttime <= :to_dt`, enabling `idx_starttime` index utilization.
  - `postauth.php`: Replaced `WHERE DATE(authdate) >= :from` with `WHERE authdate >= :from_dt`, enabling index lookup on `radpostauth(authdate)`.
- **Groups Incomplete Listing & Redundant Code**:
  - `groups.php`: Unified group discovery across `radgroupcheck`, `radgroupreply`, and `radusergroup`, preventing groups assigned in `radusergroup` from being hidden.
  - Removed duplicate redundant query on line 76 of `groups.php`.
- **User Listing Group Duplication**:
  - `users.php`: Replaced duplicate user row emission with `GROUP_CONCAT(DISTINCT rug.groupname)` and grouped strictly by `rc.username`.
- **Multi-Password Attribute Support**:
  - Updated `users.php` and `user-edit.php` to handle both `Cleartext-Password` and `User-Password` RADIUS check attributes.

---

## [1.0.0] - 2026-09-10

### Initial Release
- Basic FreeRADIUS management UI (Dashboard, Users, Groups, NAS, Accounting, Sessions, Auth Log).
- Auto-installer bash script for Ubuntu/Debian.
- Initial performance indexes script.


# RadiusManager

[![Version](https://img.shields.io/badge/version-1.9.2-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)](https://www.php.net/)
[![FreeRADIUS](https://img.shields.io/badge/FreeRADIUS-2.x%20%7C%203.x-orange.svg)](https://freeradius.org/)
[![Database](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-Optimized-4479A1.svg)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A modern, fast, and lightweight PHP web administration panel for **FreeRADIUS**, designed as a complete, high-performance replacement for **daloRADIUS**. Engineered specifically to handle high-density production enterprise networks with millions of accounting and authentication records in sub-second response times.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Database Setup](#database-setup)
  - [Option A: Connect to an Existing FreeRADIUS Database](#option-a-connect-to-an-existing-freeradius-database)
  - [Option B: Quick Start with Sample Database](#option-b-quick-start-with-sample-database)
  - [Applying Performance Indexes & Schema](#applying-performance-indexes--schema)
- [Application Configuration](#application-configuration)
- [Running the Application](#running-the-application)
  - [1. Using Apache (Ubuntu/Debian)](#1-using-apache-ubuntudebian)
  - [2. Using XAMPP / Laragon (Windows)](#2-using-xampp--laragon-windows)
  - [3. Automated Ubuntu/Debian Installer](#3-automated-ubuntudebian-installer)
  - [4. PHP Built-in Server (Local Quick Dev)](#4-php-built-in-server-local-quick-dev)
- [Authentication & Role-Based Access Control](#authentication--role-based-access-control)
- [Feature Walkthrough](#feature-walkthrough)
  - [1. Dashboard Analytics & Capacity Trends](#1-dashboard-analytics--capacity-trends)
  - [2. User & Subscriber Management (`users.php`)](#2-user--subscriber-management-usersphp)
  - [3. Hotspot & Voucher System (`vouchers.php`)](#3-hotspot--voucher-system-vouchersphp)
  - [4. Subscriber Self-Service Portal (`portal/`)](#4-subscriber-self-service-portal-portal)
  - [5. Group Policies & Bandwidth Control (`groups.php`, `plans.php`)](#5-group-policies--bandwidth-control-groupsphp-plansphp)
  - [6. NAS Network Hardware & Ruijie AP Integration (`nas.php`)](#6-nas-network-hardware--ruijie-ap-integration-nasphp)
  - [7. Real-Time Sessions & Disconnect (`sessions.php`)](#7-real-time-sessions--disconnect-sessionsphp)
  - [8. Accounting & Executive Reporting (`accounting.php`, `reports.php`)](#8-accounting--executive-reporting-accountingphp-reportsphp)
  - [9. Full daloRADIUS Configuration Parity (`settings.php`)](#9-full-daloradius-configuration-parity-settingsphp)
  - [10. RESTful JSON API Engine (`api/`)](#10-restful-json-api-engine-api)
  - [11. Backup & Disaster Recovery (`export.php`)](#11-backup--disaster-recovery-exportphp)
- [Database Schema Reference](#database-schema-reference)
- [Security Features](#security-features)
- [Documentation & User Manual](#documentation--user-manual)
- [Changelog](#changelog)
- [License](#license)

---

## Overview

RadiusManager connects directly to standard FreeRADIUS SQL databases (MySQL / MariaDB). It enables network administrators to effortlessly manage RADIUS subscribers, user profiles (`userinfo`), rate plans (`rm_plans`), bandwidth control attributes (`radgroupreply`), network access servers (`nas`), live active user sessions (`radacct`), post-authentication event logs (`radpostauth`), and complete system configuration backed by persistent database storage (`rm_settings`).

---

## Key Features

- **Full daloRADIUS Parity**: Complete replacement for daloRADIUS with 100% configuration menu parity (User, Database, Language, Logging, Interface, Message, Recurring Tasks, Mail, Maintenance, Operators, Backup) in a modern Bootstrap 5 UI.
- **Persistent Database Configuration (`rm_settings`)**: Decouples runtime system policies from hardcoded PHP files, allowing dynamic administrative tuning via web interface.
- **Enterprise-Scale Performance**: Formulated with index-friendly SARGable queries and primary key windowing that scale smoothly over **24M+ authentication records** and **1M+ accounting sessions** with sub-250ms page loads.
- **Hotspot Voucher System**: Batch generate 1–200 voucher credentials with configurable prefixes and password rules, plus print-ready A4 voucher cards.
- **Subscriber Self-Service Portal (`portal/`)**: Dedicated responsive portal where end-users monitor active sessions, monthly data transfer quota, and update passwords via **One-Time Password (OTP) verification email**.
- **Role-Based Access Control (RBAC)**: 3-tier hierarchical permission model (`superadmin` > `operator` > `readonly`) with granular mutation locks.
- **Ruijie Networks AP Integration**: First-class support for Ruijie wireless access point hardware, central controllers (`172.16.0.70`), and vendor-specific dictionary attributes.
- **Automated Security Hardening**: Login brute-force IP lockout protection, session fixation prevention (`session_regenerate_id`), CSRF verification on all forms, and production error suppression.
- **Disaster Recovery**: One-click Database Schema DDL (`.sql`) export, Configuration Snapshot (`.json`) download, and streaming CSV entity archives.
- **Zero Schema Lock-In**: Works on standard FreeRADIUS schemas without breaking native radiusd daemons.

---

## Project Structure

```
radius-manager/
├── api/                           — RESTful JSON API (index, users, sessions, accounting)
├── assets/                        — Vector logos, icons, and frontend assets
├── database/
│   ├── schema_seed.sql            — Lightweight standalone seed schema for quick testing
│   └── radius.sql                 — Production database dump (2.35 GB, gitignored)
├── docs/
│   ├── generate_guide.php         — User & Administrator Manual compiler script
│   ├── RadiusManager_User_Guide.html — Printable full user guide (HTML)
│   └── RadiusManager_User_Guide.pdf  — Formatted A4 printable PDF manual
├── includes/
│   ├── auth.php                   — Session guard, CSRF, flash notifications, RBAC helpers
│   ├── db.php                     — PDO singleton & schema inspection helpers
│   ├── functions.php              — Utilities, formatting, pagination, auditLog, rm_settings helpers
│   ├── header.php                 — Sidebar layout, topbar with role badges, CSS assets
│   ├── footer.php                 — Layout scripts, Chart.js CDN, modals
│   └── mail.php                   — Pure-PHP Socket SMTP client, email masking, OTP mailer
├── portal/                        — End-User Self-Service Portal
│   ├── auth.php                   — Subscriber authentication bootstrap
│   ├── dashboard.php              — User dashboard, data quota, session monitor, OTP password reset
│   ├── login.php                  — Subscriber credential login against radcheck
│   └── logout.php                 — Subscriber logout
├── storage/
│   └── logs/                      — app.log, mail.log, and .htaccess web guard
├── accounting.php                 — Session accounting history with fast date filtering
├── audit.php                      — Administrative activity audit trail
├── auth.php                       — Unified system bootstrap loader
├── CHANGELOG.md                   — Comprehensive version and release history
├── config.php                     — Core database credentials and system constants
├── config.sample.php              — Clean configuration template
├── dashboard.php                  — KPI stat cards, concurrent session graphs, traffic charts
├── expiry-check.php               — Subscriber expiry warning dashboard + CLI cron job
├── export.php                     — Streaming exports (users, accounting, postauth, audit, nas, config, schema)
├── groups.php                     — Group check & reply attributes editor
├── HANDOVER.md                    — Developer and team handover specification
├── index.php                      — Root redirector to dashboard
├── install.sh                     — Automated deployment script for Ubuntu/Debian
├── install.sql                    — Performance indexes, safe migration procedures & rm_settings
├── ippool.php                     — RADIUS dynamic IP address pool management
├── login.php                      — Administrative multi-source login with brute-force lockout
├── logout.php                     — Administrative session termination
├── nas.php                        — Network Access Server hardware registry & live ping probe
├── nas-add.php / nas-edit.php     — NAS registration & shared secret management
├── operators.php                  — Operator user management & RBAC administration
├── plan-add.php / plan-edit.php   — Bandwidth and rate plan management
├── plans.php                      — Speed profiles and quota plan catalog
├── postauth.php                   — Authentication attempt logs & success rate analytics
├── README.md                      — Project documentation
├── reports.php                    — Executive PDF/print reports & CSV exports
├── sessions.php                   — Real-time active sessions with CoA disconnect helper
├── settings.php                   — Full daloRADIUS configuration suite & diagnostics
├── user-add.php / user-edit.php   — Subscriber creation, limits & userinfo editor
├── user-batch.php                 — Bulk user operations (assign groups, extend expiry, delete)
├── user-import.php                — CSV bulk user import wizard
├── user-toggle.php                — Instant user enable/disable switch
├── users.php                      — Complete subscriber directory with live status
└── vouchers.php                   — Hotspot voucher inventory & batch generation
```

---

## Requirements

- **PHP**: 8.0 or higher
  - Required extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`
  - Optional: `curl` (for external webhooks)
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_php`), Nginx with PHP-FPM, or local XAMPP
- **FreeRADIUS**: 2.x, 3.0.x, or 3.2.x with SQL module enabled (`rlm_sql`)

---

## Database Setup

### Option A: Connect to an Existing FreeRADIUS Database
If you already have a running FreeRADIUS server:
1. Ensure MySQL/MariaDB service is active.
2. Note your database credentials (`host`, `port`, `database name`, `user`, `password`).
3. Apply performance indexes from `install.sql`.
4. Proceed to [Application Configuration](#application-configuration).

### Option B: Quick Start with Sample Database
For local development or testing without a production dump:
```bash
mysql -u root -p < database/schema_seed.sql
```
This initializes the `radius` database with tables and sample users, groups, NAS devices, accounting sessions, and an operator account.

### Applying Performance Indexes & Schema
Apply optimized SARGable indexes and system tables:
```bash
mysql -u root -p radius < install.sql
```
> [!NOTE]
> `install.sql` is completely idempotent. It safely verifies whether indexes or columns already exist before creation, supporting MySQL 5.7, 8.0, and MariaDB.

---

## Application Configuration

Copy `config.sample.php` to `config.php` and configure your credentials:

```php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'radius');          // FreeRADIUS database name
define('DB_USER',     'radius');          // MySQL username
define('DB_PASS',     'your_password');   // MySQL password
define('DB_PORT',     '3306');

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'RadiusManager');
define('APP_VERSION',    '1.9.2');
define('APP_ADMIN',      'admin');        // Default admin username in config
define('APP_PASS',       password_hash('admin123', PASSWORD_DEFAULT));
define('ROWS_PER_PAGE',  20);             // Default pagination rows
define('EXPIRY_WARN_DAYS', 7);            // Days before expiry to trigger warning notice
define('API_KEY',        'your_api_secret_key'); // REST API Bearer Authentication Key

// ─── Session Lifetime ─────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);         // Inactivity timeout (seconds)

// ─── Environment & Logging ────────────────────────────────────────────────
define('APP_ENV',  'production');         // 'production' | 'development'
define('LOG_FILE', __DIR__ . '/storage/logs/app.log');
```

---

## Running the Application

### 1. Using Apache (Ubuntu/Debian)
```bash
sudo cp -r radius-manager /var/www/html/radiusmanager
sudo chown -R www-data:www-data /var/www/html/radiusmanager/storage
sudo systemctl restart apache2
```
Access at: `http://your-server-ip/radiusmanager/`

### 2. Using XAMPP / Laragon (Windows)
1. Place repository in `C:\xampp\htdocs\radiusmanager\radius-manager\`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Access at: `http://localhost/radiusmanager/radius-manager/`.

### 3. Automated Ubuntu/Debian Installer
```bash
sudo chmod +x install.sh
sudo ./install.sh
```
The installer automatically sets up dependencies, Apache virtual hosts, Let's Encrypt / self-signed SSL certificates, and applies database indexes.

### 4. PHP Built-in Server (Local Quick Dev)
```bash
php -S localhost:8000
```
Access at: `http://localhost:8000/login.php`

---

## Authentication & Role-Based Access Control

### 1. Administrative Login (`login.php`)
Supports dual authentication sources:
- **Config Admin**: Uses `APP_ADMIN` and `APP_PASS` defined in `config.php`.
- **Database Operators**: Authenticates against `operators` table (Bcrypt, MD5, and plain text with automatic Bcrypt upgrade).
- **Brute Force Lockout**: Maximum 5 failed attempts in 15 minutes locks the IP automatically.

### 2. Role Permissions (RBAC)
| Role | Capabilities | Restrictions |
|---|---|---|
| `superadmin` | Complete system control, NAS hardware, audit logs, IP unlocking, configuration | None |
| `operator` | Add, edit, toggle users, vouchers, groups, plans, export reports | Cannot modify system settings, NAS secrets, or operators |
| `readonly` | View dashboards, statistics, session logs, user lists | All mutation forms and API write endpoints are blocked |

---

## Feature Walkthrough

### 1. Dashboard Analytics & Capacity Trends
- Real-time KPI counters (active users, live sessions, registered NAS, auths today).
- 24-hour concurrent session hourly profile graph comparing today vs. yesterday.
- 14-day bandwidth volume trend stacked line/bar chart (Upload vs. Download MB).
- Top 5 NAS Devices traffic chart & Top 5 Bandwidth Consumers leaderboard.

### 2. User & Subscriber Management (`users.php`)
- Comprehensive list integrating `radcheck` credentials with `userinfo` real names and departments.
- Single-click enable/disable toggle without deleting account credentials.
- Built-in secure password generator utilizing configured random character sets.
- Bulk operations: batch group assignment, batch expiry extension, batch delete.
- CSV Bulk Import wizard (`user-import.php`) supporting thousands of accounts in a single transaction.

### 3. Hotspot & Voucher System (`vouchers.php`)
- Dedicated hotspot voucher inventory tracking unused, active, and expired cards.
- Batch voucher generator (`voucher-generate.php`) creating 1–200 accounts with custom prefixes.
- Print-ready A4 voucher sheets (`voucher-print.php`) with cutting borders, SSID branding, credentials, and plan instructions.

### 4. Subscriber Self-Service Portal (`portal/`)
- Independent subscriber login validating against `radcheck`.
- Real-time usage indicators: monthly data consumption progress bar, session state, plan details.
- **Email OTP Password Reset**: Sends a 6-digit verification code to the registered email address in `userinfo.email`. Accounts without registered emails are directed to contact IT support.

### 5. Group Policies & Bandwidth Control (`groups.php`, `plans.php`)
- Bandwidth management via MikroTik rate limits (`Mikrotik-Rate-Limit := 10M/10M`), ChilliSpot octets, and WISPr bandwidth reply attributes.
- Simplicity-driven Rate Plan catalog (`plans.php`) allowing speed profiles to be assigned in one click.

### 6. NAS Network Hardware & Ruijie AP Integration (`nas.php`)
- Network Access Server registry with real-time socket reachability probe.
- First-class support for **Ruijie Networks Wireless Access Points** and central controller (`172.16.0.70`).
- Pre-configured dictionary attributes for Ruijie APs, Cisco, MikroTik, Ubiquiti, and Ruckus.

### 7. Real-Time Sessions & Disconnect (`sessions.php`)
- Live monitor of active sessions (`acctstoptime IS NULL`) auto-refreshing every 30 seconds.
- Integrated Change of Authorization (CoA) disconnect helper generating exact `radclient` commands.

### 8. Accounting & Executive Reporting (`accounting.php`, `reports.php`)
- Search millions of past sessions with date-range filters and index-optimized pagination.
- Executive printable management reports with visual charts, traffic summaries, and CSV exports.

### 9. Full daloRADIUS Configuration Parity (`settings.php`)
Modernized daloRADIUS configuration suite organized into dual-level navigation:
- **Global Settings (Left Sub-Navigation)**:
  1. `User Settings`: Cleartext password storage policy, allowed random charset, min/max password length, default group, and expiry period.
  2. `Database Settings`: DB credentials, FreeRADIUS Auth (1812) / Acct (1813) ports, and live UDP socket reachability tester.
  3. `Language Settings`: Language toggle (English / Indonesian), document charset, and server timezone (`Asia/Jakarta`).
  4. `Logging Settings`: Environment mode (production/debug), error logging toggle, and live log tail monitor.
  5. `Interface Settings`: App name branding, pagination rows per page, date/time format, and color themes.
  6. `Message Settings`: New user welcome message template, OTP email subject, and portal help notice.
  7. `Recurring Tasks Settings`: Stale session retention threshold and manual **"Clean Stale Sessions Now"** button.
- **System Tabs (Top Navigation)**:
  - `Mail`: Dynamic Socket SMTP configuration, credentials, and live **"Send Test Verification Email"** tool.
  - `Maintenance`: Server runtime diagnostics, database scale metrics, stale session cleaner, and Brute-Force IP lockout unlocker.
  - `Operators`: Directory of system administrators and database operators.
  - `Backup`: One-click Database Schema DDL download, Configuration Snapshot export, and entity CSVs.
  - `My Account`: Operator password change and personal profile editor.

### 10. RESTful JSON API Engine (`api/`)
- Authenticate via `Authorization: Bearer <API_KEY>` or `X-API-Key`.
- `/api/users.php`: Full CRUD operations on subscribers.
- `/api/sessions.php`: List active sessions and trigger disconnects.
- `/api/accounting.php`: Fetch historical session accounting data.

### 11. Backup & Disaster Recovery (`export.php`)
- Streaming CSV exports for Users, Accounting, Authentication Logs, Audit Trails, and NAS Hardware.
- Instant **Database Schema DDL (.sql)** generation covering all FreeRADIUS and custom tables.
- **Configuration Snapshot (.json)** exporting all persistent settings with passwords masked.

---

## Database Schema Reference

| Table | Primary Columns | Purpose |
|---|---|---|
| `radcheck` | `id, username, attribute, op, value` | Subscriber check attributes (passwords, expiration, simultaneous-use) |
| `radreply` | `id, username, attribute, op, value` | Individual user reply attributes |
| `radusergroup` | `id, username, groupname, priority` | Subscriber-to-group policy mappings |
| `radgroupcheck` | `id, groupname, attribute, op, value` | Group check constraints |
| `radgroupreply` | `id, groupname, attribute, op, value` | Group reply attributes (rate limits, VLAN, session timeout) |
| `nas` | `id, nasname, shortname, type, ports, secret, description` | Network Access Server hardware registry |
| `radacct` | `radacctid, acctsessionid, username, nasipaddress, acctstarttime, acctstoptime, acctinputoctets, acctoutputoctets, framedipaddress, callingstationid` | Session accounting and bandwidth tracking |
| `radpostauth` | `id, username, pass, reply, authdate` | Authentication attempt logging |
| `userinfo` | `id, username, firstname, lastname, email, department` | Real-name profile and contact details |
| `operators` | `id, username, password, firstname, lastname, role` | Administrative operator accounts |
| `rm_settings` | `setting_key, setting_value, updated_at` | Persistent system & daloRADIUS compatibility configuration |
| `rm_vouchers` | `id, batch_name, username, password, plan_id, status` | Hotspot voucher inventory |
| `rm_plans` | `id, name, speed_down, speed_up, price, validity_days` | Bandwidth profiles and rate plans |
| `rm_login_attempts`| `id, ip_address, username, attempted_at` | Login brute-force security tracking |
| `rm_audit_log` | `id, created_at, operator, action, target, detail, ip_address` | Administrative activity audit trail |

---

## Security Features

1. **Brute Force Protection**: Automatically blocks IP addresses after 5 failed attempts in 15 minutes.
2. **Session Fixation Defense**: Executes `session_regenerate_id(true)` immediately upon credential verification.
3. **Cross-Site Request Forgery (CSRF)**: Cryptographically secure tokens checked on all POST forms via `hash_equals()`.
4. **SQL Injection Defense**: 100% prepared statements with parameterized PDO queries.
5. **Cross-Site Scripting (XSS)**: Strict escaping of all output via `htmlspecialchars()`.
6. **Directory Traversal Guard**: `.htaccess` protection preventing web browser access to `storage/logs/`.

---

## Documentation & User Manual

- Complete printable manual available in HTML and A4 PDF format in `docs/`:
  - [RadiusManager_User_Guide.html](docs/RadiusManager_User_Guide.html)
  - [RadiusManager_User_Guide.pdf](docs/RadiusManager_User_Guide.pdf)
- Compile or update the guide at any time by executing:
  ```bash
  php docs/generate_guide.php
  ```

---

## Changelog

Detailed release history and patch records are documented in [CHANGELOG.md](CHANGELOG.md).

---

## License

This project is licensed under the **MIT License**.

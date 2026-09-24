# RadiusManager

A modern, fast, and lightweight PHP web administration panel for **FreeRADIUS**, designed as an efficient and clean replacement for daloRADIUS. Built to handle production FreeRADIUS setups with millions of accounting and authentication records.

---

## Table of Contents

- [Overview](#overview)
- [Project Structure](#project-structure)
- [Key Features](#key-features)
- [Requirements](#requirements)
- [Database Setup](#database-setup)
  - [Option A: Connect to an Existing FreeRADIUS Database](#option-a-connect-to-an-existing-freeradius-database)
  - [Option B: Quick Start with Sample Database](#option-b-quick-start-with-sample-database)
  - [Applying Performance Indexes](#applying-performance-indexes)
- [Application Configuration](#application-configuration)
- [Running the Application](#running-the-application)
  - [1. Using PHP Built-in Server (Local Testing)](#1-using-php-built-in-server-local-testing)
  - [2. Using Apache (Ubuntu/Debian)](#2-using-apache-ubuntudebian)
  - [3. Using XAMPP / Laragon (Windows)](#3-using-xampp--laragon-windows)
  - [4. Automated Ubuntu Installer](#4-automated-ubuntu-installer)
- [Authentication & Default Logins](#authentication--default-logins)
- [Feature Walkthrough](#feature-walkthrough)
  - [1. Dashboard](#1-dashboard)
  - [2. User Management (`users.php`)](#2-user-management-usersphp)
  - [3. Group Management (`groups.php`)](#3-group-management-groupsphp)
  - [4. NAS Devices (`nas.php`)](#4-nas-devices-nasphp)
  - [5. Accounting & History (`accounting.php`)](#5-accounting--history-accountingphp)
  - [6. Active Sessions & Disconnect (`sessions.php`)](#6-active-sessions--disconnect-sessionsphp)
  - [7. Authentication Log (`postauth.php`)](#7-authentication-log-postauthphp)
- [Database Schema Reference](#database-schema-reference)
- [Security Features](#security-features)
- [Troubleshooting & FAQ](#troubleshooting--faq)
- [Changelog](#changelog)
- [License](#license)

---

## Overview

RadiusManager connects directly to standard FreeRADIUS SQL databases (MySQL / MariaDB). It enables network administrators to effortlessly manage RADIUS users, user profiles (`userinfo`), bandwidth policies (`radgroupreply`), network access servers (`nas`), live user sessions (`radacct`), and post-authentication logs (`radpostauth`).

---

## Project Structure

```
radius-manager/
├── database/
│   ├── radius.sql             — Original production database dump (2.35 GB)
│   └── schema_seed.sql        — Lightweight standalone seed schema for quick testing
├── assets/
│   └── img/
│       ├── logo.svg           — Custom vector brand logo & favicon
│       └── icon.svg           — Scalable network hub icon
├── includes/
│   ├── db.php                 — PDO singleton & schema inspection helpers
│   ├── auth.php               — Authentication, session timeout & CSRF protection
│   ├── functions.php          — Formatting, sanitization & pagination helpers
│   ├── header.php             — Layout sidebar & responsive navigation
│   └── footer.php             — Layout scripts (Bootstrap JS, Chart.js)
├── config.php                 — Database connection & app configuration
├── auth.php                   — Unified bootstrap loader
├── index.php                  — Root redirector
├── login.php                  — Secure multi-source login
├── logout.php                 — Session termination
├── dashboard.php              — Metrics, live sessions, traffic today & 7-day chart
├── users.php                  — User list with userinfo integration & search
├── user-add.php               — Create user, password, limits & userinfo profile
├── user-edit.php              — Edit password, expiry, simultaneous logins & groups
├── user-delete.php            — Delete user and associated records
├── groups.php                 — Manage check & reply attributes (bandwidth, VLAN)
├── nas.php                    — Registered NAS / routers / APs
├── nas-add.php                — Register new NAS device
├── nas-edit.php               — Edit NAS IP, secret, type, ports
├── nas-delete.php             — Delete NAS device
├── accounting.php             — Session history with optimized date ranges
├── sessions.php               — Real-time active sessions with CoA kick helper
├── postauth.php               — Authentication attempts log & success rates
├── install.sh                 — Automated Ubuntu/Debian deployment script
├── install.sql                — Performance indexes & safe migration procedures
├── CHANGELOG.md               — Detailed release history
├── HANDOVER.md                — Cross-agent / team handover specification
└── README.md                  — Application documentation
```

---

## Key Features

- **No Schema Lock-In**: Works on standard FreeRADIUS schemas, existing daloRADIUS databases, and custom RADIUS tables without destructive alterations.
- **Large-Scale Query Optimization**: Formulated with index-friendly SARGable queries that seamlessly scale over millions of accounting and auth log rows.
- **Student / Employee Profiles**: Seamlessly integrates with the `userinfo` table to display real names, departments, and emails alongside raw usernames.
- **Multi-Source Authentication**: Log in with configuration credentials or existing database accounts from the `operators` table.
- **Security-First**: Built-in cryptographic CSRF tokens on every form, strict input sanitization, and session timeout guards.
- **Responsive Modern UI**: Fast, clean Bootstrap 5 interface with dark sidebar, live badges, password toggles, and Chart.js analytics.

---

## Requirements

- **PHP**: 8.0 or higher
  - Required extensions: `pdo`, `pdo_mysql`, `mbstring`
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ (with `mod_php` or `php-fpm`), Nginx, or PHP CLI built-in server.
- **FreeRADIUS**: 2.x, 3.0.x, or 3.2.x with SQL module enabled.

---

## Database Setup

### Option A: Connect to an Existing FreeRADIUS Database
If you already have a running FreeRADIUS database (e.g. from the provided `database/radius.sql` or your server's live `radius` database):
1. Ensure the MySQL service is running.
2. Note your database credentials (host, port, database name, username, and password).
3. Proceed directly to [Application Configuration](#application-configuration).

### Option B: Quick Start with Sample Database
For local development or testing without importing a multi-gigabyte dump, load the lightweight seed schema (`database/schema_seed.sql`):

```bash
# Using MySQL / MariaDB command line:
mysql -u root -p < database/schema_seed.sql
```

This creates the `radius` database with complete schemas and sample data for:
- 3 RADIUS users (`206412005`, `201403003`, `224312005`) with names and departments
- 2 NAS devices (Cisco AP controller and Eduroam gateway)
- 3 Policy groups (`Pegawai`, `Mhs2024`, `Pegawai-Eduroam`)
- Sample active and past accounting sessions
- Sample authentication logs
- 1 Operator account (`administrator` / `4dm1nNamloP`)

### Applying Performance Indexes
For production databases with high traffic, apply the optimized indexes from `install.sql`:

```bash
mysql -u root -p radius < install.sql
```

> [!NOTE]
> `install.sql` uses safe, idempotent stored procedures. It checks whether an index exists before creating it, avoiding errors across MySQL 5.7, 8.0, and MariaDB.

---

## Application Configuration

Open [radius-manager/config.php](file:///d:/radiusmanager/radius-manager/config.php) and configure your database connection:

```php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     'localhost');     // Database server hostname or IP
define('DB_NAME',     'radius');        // FreeRADIUS database name
define('DB_USER',     'radius');        // MySQL username (e.g. root or radius)
define('DB_PASS',     'your_password'); // MySQL password
define('DB_PORT',     '3306');          // MySQL port

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'RadiusManager');
define('APP_VERSION',    '1.1.0');
define('APP_ADMIN',      'admin');        // Fallback admin username
define('APP_PASS',       password_hash('admin123', PASSWORD_DEFAULT)); // Password hash
define('ROWS_PER_PAGE',  20);             // Default pagination rows

// ─── Session Lifetime ─────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);         // Session timeout (1 hour in seconds)
```

---

## Running the Application

### 1. Using PHP Built-in Server (Local Testing)
The fastest way to run and test RadiusManager locally:

```bash
cd radius-manager
php -S localhost:8000
```
Open your browser and navigate to: **`http://localhost:8000`**

### 2. Using Apache (Ubuntu/Debian)
1. Deploy the `radius-manager` directory to `/var/www/html/radiusmanager`.
2. Ensure correct permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/radiusmanager
   sudo chmod -R 755 /var/www/html/radiusmanager
   ```
3. Enable Apache rewrite module and restart:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```
4. Access the web panel at: **`http://<your-server-ip>/radiusmanager`**

### 3. Using XAMPP / Laragon (Windows)
1. Copy the `radius-manager` folder into your web root:
   - **XAMPP**: `C:\xampp\htdocs\radiusmanager`
   - **Laragon**: `C:\laragon\www\radiusmanager`
2. Start Apache and MySQL from your control panel.
3. Open: **`http://localhost/radiusmanager`**

### 4. Automated Ubuntu Installer
Run the provided installer script as root:
```bash
cd radius-manager
sudo bash install.sh
```

---

## Authentication & Default Logins

RadiusManager supports **dual-source authentication**:

### Account 1: Configuration Admin
- **Username**: `admin`
- **Password**: `admin123`
- Defined in `config.php`. To change the password, generate a new hash:
  ```bash
  php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
  ```
  and replace `APP_PASS` in `config.php`.

### Account 2: Database Operators
If your FreeRADIUS database has an `operators` table (such as in `database/radius.sql` or `database/schema_seed.sql`):
- **Username**: `administrator`
- **Password**: `4dm1nNamloP`
- Authenticates directly against the database, supporting Bcrypt, MD5, and plain text formats with automatic `lastlogin` tracking.

---

## Feature Walkthrough

### 1. Dashboard
- **Live Metrics**: Real-time counter of total users, active connections, registered NAS devices, and today's authentication count.
- **Daily Bandwidth**: Aggregated daily upload and download octets.
- **7-Day Chart**: Bar chart illustrating authentication activity over the past week.
- **Failed Logins**: Fast overview of recent rejected login attempts (`Access-Reject`).
- **Live Sessions Preview**: Snapshot of recent active connections with client IP and MAC.

### 2. User Management (`users.php`)
- **Combined User View**: Lists users with their raw username, decrypted password display toggle, group assignment, online/offline badge, and profile details (`userinfo.firstname` and `department`).
- **Search**: Comprehensive search by username, full name, department, or email.
- **Add User (`user-add.php`)**:
  - Sets username and password (with built-in secure random password generator).
  - Optional profile info: Full Name, Department/Class, Email (saved to `userinfo`).
  - RADIUS constraints: Simultaneous-Use limit, Expiration date, and Group mapping.
- **Edit User (`user-edit.php`)**:
  - Update password, group assignment, expiry date, and profile details.
  - Displays user's recent accounting history directly on the edit page.
- **Delete User (`user-delete.php`)**:
  - Safely deletes user credentials, reply attributes, group assignments, and userinfo records inside a database transaction.

### 3. Group Management (`groups.php`)
- **Group Profiles**: Manage shared user profiles (e.g. `Pegawai`, `Mahasiswa`).
- **Attributes Editor**:
  - **Check Attributes (`radgroupcheck`)**: Enforce check conditions (e.g. `Auth-Type := Local`, `Simultaneous-Use := 1`).
  - **Reply Attributes (`radgroupreply`)**: Push configuration to NAS routers (e.g. `Mikrotik-Rate-Limit := 10M/10M`, `Framed-Pool`, `Filter-Id`).
- **Member Listing**: View all users assigned to the selected group with quick navigation links.

### 4. NAS Devices (`nas.php`)
- **Device Registry**: Manage routers, switches, access point controllers, and gateways (Cisco, MikroTik, Ubiquiti, Ruckus, Huawei, ZTE).
- **Shared Secrets**: Secure toggle to reveal or hide the pre-shared secret key.
- **Ports & Descriptions**: Register standard authentication ports (1812 / 1813) and physical location details.

### 5. Accounting & History (`accounting.php`)
- **Session Records**: Complete audit trail of past network sessions (`radacct`).
- **Date Range Filters**: Filter sessions by start/stop dates with index-optimized queries.
- **Summary Cards**: Displays total sessions, upload traffic, download traffic, and accumulated online time.
- **Termination Reason**: Shows disconnect causes (e.g. `User-Request`, `Lost-Carrier`, `Session-Timeout`).

### 6. Active Sessions & Disconnect (`sessions.php`)
- **Real-Time Monitoring**: Automatically lists all active sessions (`acctstoptime IS NULL`) and auto-refreshes every 30 seconds.
- **Client Metadata**: Inspects Framed IP, NAS IP, MAC address (`callingstationid`), and accumulated session octets.
- **Kick / Disconnect Helper**:
  - Generates the exact CoA (Change of Authorization) disconnect command for FreeRADIUS `radclient`:
    ```bash
    echo "User-Name=john,Acct-Session-Id=sess_001" | radclient -x 172.16.0.70:3799 disconnect 'your_nas_secret'
    ```

### 7. Authentication Log (`postauth.php`)
- **Live Auth Auditing**: Detailed log of all access requests (`radpostauth`).
- **Filtering**: View All, Accepted only (`Access-Accept`), or Rejected only (`Access-Reject`).
- **Success Rate Calculator**: Computes percentage success rates for the selected period.
- **Legacy Compatibility**: Automatically detects whether `nasipaddress` exists in your `radpostauth` table to prevent SQL errors.

---

## Database Schema Reference

| Table | Primary Columns | Purpose |
|---|---|---|
| `radcheck` | `id, username, attribute, op, value` | User authentication check attributes (passwords, expiration, simultaneous-use) |
| `radreply` | `id, username, attribute, op, value` | Individual user reply attributes |
| `radusergroup` | `id, username, groupname, priority` | User-to-group policy assignments |
| `radgroupcheck` | `id, groupname, attribute, op, value` | Group check constraints |
| `radgroupreply` | `id, groupname, attribute, op, value` | Group reply attributes (rate limits, VLAN, session timeout) |
| `nas` | `id, nasname, shortname, type, ports, secret, description` | Network Access Server client registry |
| `radacct` | `radacctid, acctsessionid, username, nasipaddress, acctstarttime, acctstoptime, acctinputoctets, acctoutputoctets, framedipaddress, callingstationid` | Session accounting and bandwidth tracking |
| `radpostauth` | `id, username, pass, reply, authdate` | Authentication attempt logging |
| `userinfo` | `id, username, firstname, lastname, email, department` | Real-name profile and contact information |
| `operators` | `id, username, password, firstname, lastname` | System administrator accounts |

---

## Security Features

1. **Cross-Site Request Forgery (CSRF)**: Every mutation form incorporates a cryptographically secure token validated with `hash_equals()`.
2. **SQL Injection Prevention**: All queries utilize PDO prepared statements with strict parameter binding.
3. **Cross-Site Scripting (XSS)**: All user-supplied data is escaped with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` before rendering.
4. **Session Timeout Protection**: Inactive sessions expire after `SESSION_LIFETIME` seconds (default: 3600 seconds / 1 hour).
5. **Safe Database Probing**: Database schema introspection queries utilize `information_schema` with request-level caching.

---

## Troubleshooting & FAQ

### Q: "Database connection failed"
- Verify that MySQL / MariaDB service is running:
  ```bash
  sudo systemctl status mysql
  ```
- Check `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` in `config.php`.
- Test connecting via CLI:
  ```bash
  mysql -h localhost -u radius -p radius
  ```

### Q: "Unknown column 'nasipaddress' in 'field list'"
- This error occurs in older, unpatched applications when querying `radpostauth`.
- **In RadiusManager v1.1.0+ this is fixed automatically**; the application detects if `nasipaddress` exists and safely adjusts its query without crashing.

### Q: "Query timeout on dashboard or accounting"
- If you have millions of records in `radacct` or `radpostauth`, make sure you have applied the performance indexes from `install.sql`:
  ```bash
  mysql -u root -p radius < radius-manager/install.sql
  ```

### Q: "How do I change the default admin password?"
- In `config.php`, replace the value of `APP_PASS` with a newly generated password hash:
  ```bash
  php -r "echo password_hash('my_secret_password', PASSWORD_DEFAULT);"
  ```

---

## Changelog

For a complete list of changes and version history, see [CHANGELOG.md](file:///d:/radiusmanager/CHANGELOG.md).

---

## License

This project is licensed under the **MIT License**.

